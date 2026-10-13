<?php
namespace Tests\Feature;

use App\Models\{Client, Invoice, User, Vehicle};
use Illuminate\Support\Facades\{Auth, DB, Gate, Hash, Http, Mail, Password};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerIdentityTest extends TestCase
{
    private User $owner;
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array']);
        DB::purge('sqlite');
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        Mail::fake(); Http::fake();
        $this->owner = User::create(['name'=>'Shop','email'=>'identity-owner@example.test','password'=>Hash::make('Password123!'),'type'=>'owner','lang'=>'tr']);
        Gate::before(fn($user)=>$user->type === 'owner' ? true : null);
        DB::table('settings')->insert(['parent_id'=>$this->owner->id,'name'=>'pricing_feature','value'=>'off']);
        $this->actingAs($this->owner)->withoutMiddleware(\App\Http\Middleware\XSS::class);
    }
    private function customer(string $email): User
    {
        return User::create(['name'=>'Customer','email'=>$email,'phone_number'=>'5551234567','password'=>Hash::make('CustomerPassword123!'),'type'=>'client','parent_id'=>$this->owner->id]);
    }

    public function test_shared_email_and_phone_are_allowed_in_same_and_different_shops(): void
    {
        $data=['name'=>'Customer','email'=>$this->owner->email,'phone_number'=>'5551234567'];
        for($i=0;$i<2;$i++) $this->post(route('client.simple.store'),$data)->assertSessionHasNoErrors()->assertRedirect(route('client.index'));
        $other=User::create(['name'=>'Second shop','email'=>'identity-second@example.test','password'=>'x','type'=>'owner']);
        $this->actingAs($other)->post(route('client.simple.store'),$data)->assertSessionHasNoErrors();
        $this->assertSame(3,User::where('type','client')->where('contact_email',$this->owner->email)->whereNull('email')->count());
        $this->assertSame(3,User::where('type','client')->where('phone_number','5551234567')->count());
        $this->get('/client')->assertOk()->assertSee($this->owner->email);
        $this->assertSame(1,Client::where('parent_id',$other->id)->count());
        $this->assertSame(2,Client::where('parent_id',$this->owner->id)->count());
    }

    public function test_contact_edit_and_clear_preserve_vehicle_and_invoice_links(): void
    {
        $customer=$this->customer('contact@example.test');
        Client::create(['user_id'=>$customer->id,'parent_id'=>$this->owner->id,'client_id'=>1]);
        $vehicle=Vehicle::create(['parent_id'=>$this->owner->id,'client'=>$customer->id]);
        $invoice=Invoice::create(['parent_id'=>$this->owner->id,'client'=>$customer->id]);
        $data=['name'=>'Updated','email'=>$this->owner->email,'phone_number'=>'5551234567'];
        $this->put(route('client.simple.update',$customer->id),$data)->assertSessionHasNoErrors();
        $this->assertSame($this->owner->email,$customer->fresh()->email);
        $this->assertNull($customer->fresh()->getRawOriginal('email'));
        $this->assertSame($customer->id,(int)$vehicle->fresh()->client);
        $this->assertSame($customer->id,(int)$invoice->fresh()->client);
        $this->put(route('client.simple.update',$customer->id),array_replace($data,['email'=>'']))->assertSessionHasNoErrors();
        $this->assertNull($customer->fresh()->email);
        $this->assertNull($customer->fresh()->contact_email);
    }

    public function test_customer_email_does_not_block_public_owner_registration(): void
    {
        $customer=$this->customer('new-shop@example.test');
        Role::firstOrCreate(['name'=>'owner','guard_name'=>'web','parent_id'=>0]);
        DB::table('settings')->insert([
            ['parent_id'=>1,'name'=>'owner_email_verification','value'=>'off'],
            ['parent_id'=>1,'name'=>'google_recaptcha','value'=>'off'],
            ['parent_id'=>1,'name'=>'EMAIL_ENABLED','value'=>'0'],
        ]);
        Auth::logout();
        $this->post('/register',['name'=>'New shop','email'=>$customer->email,'password'=>'NewPassword123!','password_confirmation'=>'NewPassword123!'])->assertSessionHasNoErrors()->assertRedirect();
        $account=User::where('type','owner')->where('email','new-shop@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($account);
        $this->assertSame('client',$customer->fresh()->type);
        $this->assertSame($this->owner->id,$customer->fresh()->parent_id);
        $this->assertSame('new-shop@example.test',$customer->fresh()->contact_email);
        Auth::logout();
        $this->post('/register',['name'=>'Duplicate shop','email'=>$account->email,'password'=>'NewPassword123!','password_confirmation'=>'NewPassword123!'])->assertSessionHasErrors('email');
    }

    public function test_authentication_and_password_broker_only_find_actual_accounts(): void
    {
        $customer=$this->customer('shared-login@example.test');
        $account=User::create(['name'=>'Login shop','email'=>$customer->email,'password'=>Hash::make('LoginPassword123!'),'type'=>'owner']);
        Auth::logout();
        $provider=Auth::getProvider();
        $this->assertSame($account->id,$provider->retrieveByCredentials(['email'=>$customer->email])->id);
        $this->assertNull($provider->retrieveById($customer->id));
        $this->assertNull($provider->retrieveByToken($customer->id,'old-remember-token'));
        $this->assertFalse(Auth::attempt(['email'=>$customer->email,'password'=>'CustomerPassword123!']));
        $this->assertTrue(Auth::attempt(['email'=>$customer->email,'password'=>'LoginPassword123!']));
        Auth::logout();
        $token=null;
        $status=Password::sendResetLink(['email'=>$customer->email], function($user,$issued) use($account,&$token){ $this->assertSame($account->id,$user->id); $token=$issued; });
        $this->assertSame(Password::RESET_LINK_SENT,$status);
        $status=Password::reset(['email'=>$customer->email,'token'=>$token,'password'=>'ChangedPassword123!','password_confirmation'=>'ChangedPassword123!'],function($user,$password){$user->password=Hash::make($password);$user->save();});
        $this->assertSame(Password::PASSWORD_RESET,$status);
        $this->assertTrue(Hash::check('ChangedPassword123!',$account->fresh()->password));
        $this->assertTrue(Hash::check('CustomerPassword123!',$customer->fresh()->password));
        $onlyCustomer=$this->customer('contact-only@example.test');
        $this->assertSame(Password::INVALID_USER,Password::sendResetLink(['email'=>$onlyCustomer->email]));
    }

    public function test_migration_preserves_legacy_contact_and_ids_and_invalidates_customer_tokens(): void
    {
        $migration=require database_path('migrations/2026_10_13_000003_separate_customer_contact_emails.php');
        $migration->down();
        $id=DB::table('users')->insertGetId(['name'=>'Legacy contact','email'=>'legacy-contact@example.test','type'=>'client','parent_id'=>$this->owner->id,'password'=>Hash::make('Legacy123!'),'remember_token'=>'old-token']);
        $vehicle=Vehicle::create(['parent_id'=>$this->owner->id,'client'=>$id]);
        $invoice=Invoice::create(['parent_id'=>$this->owner->id,'client'=>$id]);
        DB::table('password_resets')->insert(['email'=>'legacy-contact@example.test','token'=>'old-reset-token','created_at'=>now()]);
        $migration->up();
        $customer=User::findOrFail($id);
        $this->assertSame('legacy-contact@example.test',$customer->email);
        $this->assertNull($customer->getRawOriginal('email'));
        $this->assertNull($customer->remember_token);
        $this->assertSame($id,(int)$vehicle->fresh()->client);
        $this->assertSame($id,(int)$invoice->fresh()->client);
        $this->assertSame(0,DB::table('password_resets')->where('email',$customer->email)->count());
        $this->assertSame($this->owner->email,$this->owner->fresh()->email);
        $this->assertNull(Auth::getProvider()->retrieveByCredentials(['email'=>$customer->email]));
        $migration->up();
        $this->assertSame('legacy-contact@example.test',$customer->fresh()->email);
    }

    public function test_rollback_with_shared_emails_stops_without_losing_contacts(): void
    {
        $customer=$this->customer($this->owner->email);
        $migration=require database_path('migrations/2026_10_13_000003_separate_customer_contact_emails.php');
        try { $migration->down(); $this->fail('Conflicting rollback must stop.'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('Veri kaybını',$e->getMessage()); }
        $this->assertSame($this->owner->email,$customer->fresh()->contact_email);
        $this->assertNull($customer->fresh()->getRawOriginal('email'));
        $this->assertSame($this->owner->email,$this->owner->fresh()->email);
    }
}
