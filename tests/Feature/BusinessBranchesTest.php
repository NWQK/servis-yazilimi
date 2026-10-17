<?php
namespace Tests\Feature;

use App\Models\{BusinessGroup, User, Vehicle, Service, Invoice, Item, Tax, Subscription};
use App\Services\{BusinessGroups, BranchContext, DemoCleanup};
use Illuminate\Support\Facades\{DB, Gate, Hash, Mail, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessBranchesTest extends TestCase
{
    private User $admin;
    private User $a;
    private User $b;
    private BusinessGroup $group;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:',
            'session.driver'=>'array','cache.default'=>'array',
            'recovery.write_lock_path'=>storage_path('framework/testing/branches-'.Str::uuid())]);
        DB::purge('sqlite');
        $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        \Illuminate\Support\Facades\Schema::table('invoice_payments',function($table){
            foreach(['transaction_id','receipt','payment_type','payment_status'] as $column) $table->string($column)->nullable();
        });
        Mail::fake(); Storage::fake('local');
        $this->admin=User::create(['name'=>'Admin','email'=>'admin@branches.test','type'=>'super admin','password'=>Hash::make('password')]);
        $this->a=User::create(['name'=>'Sakarya','email'=>'sakarya@branches.test','type'=>'owner','parent_id'=>$this->admin->id,'password'=>Hash::make('password')]);
        $this->b=User::create(['name'=>'İstanbul','email'=>'istanbul@branches.test','type'=>'owner','parent_id'=>$this->admin->id,'password'=>Hash::make('password')]);
        $role=\Spatie\Permission\Models\Role::firstOrCreate(['name'=>'owner','guard_name'=>'web','parent_id'=>1]);
        foreach(['manage tax','create tax','edit tax','delete tax','create client','manage client',
            'create vehicle','manage vehicle','manage general settings','manage company settings',
            'manage service','create service','manage item','create item','manage employee','manage unit',
            'manage invoice','show invoice','create invoice','edit invoice','create invoice payment',
            'manage account settings','manage password settings','manage 2FA settings'] as $ability) {
            $role->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name'=>$ability,'guard_name'=>'web']));
        }
        $this->a->assignRole($role); $this->b->assignRole($role);
        $this->actingAs($this->admin)->withoutMiddleware(\App\Http\Middleware\XSS::class);
        $this->group=app(BusinessGroups::class)->create(['name'=>'Bike and More','manager_name'=>'Merkez',
            'email'=>'merkez@branches.test','password'=>'long-password-123']);
        $this->manager=$this->group->manager;
        $this->attach($this->a);
        $this->attach($this->b);
    }
    private function attach(User $owner): void
    {
        $this->actingAs($this->admin);
        app(BusinessGroups::class)->membership($this->group->id,$owner->id,$this->group->fresh()->version,true);
    }
    private function token(User $owner): string
    {
        return BranchContext::token($this->group->fresh(),$owner->fresh(),$this->manager);
    }
    private function url(string $route, User $owner, array $params=[]): string
    {
        return route($route,$params+['_branch'=>$this->token($owner)]);
    }
    public function test_only_super_admin_can_create_centres_or_change_membership(): void
    {
        foreach([$this->a,$this->manager] as $user) {
            $this->actingAs($user)->get(route('branches.index'))->assertForbidden();
            $this->post(route('branches.store'),[])->assertForbidden();
            $this->post(route('branches.membership',$this->group->id),[])->assertForbidden();
            $this->post(route('branches.status',$this->group->id),[])->assertForbidden();
        }
        $this->assertSame(1,BusinessGroup::count());
    }
    public function test_central_account_has_no_demo_and_portal_only_lists_its_own_shops(): void
    {
        $this->assertSame('branch_manager',$this->manager->type);
        $this->assertNull($this->manager->subscription);
        $this->assertNull($this->manager->demo_started_at);
        $other=User::create(['name'=>'Gizli işletme','type'=>'owner','email'=>'hidden@test.test','password'=>'x','parent_id'=>1]);
        $this->actingAs($this->manager)->get(route('branches.portal'))->assertOk()->assertSee('Sakarya')->assertSee('İstanbul')->assertDontSee('Gizli işletme');
        $this->get(route('dashboard'))->assertRedirect(route('branches.portal'));
        $this->get(route('branches.portal'))->assertHeader('Cache-Control','no-store, private');
    }
    public function test_attach_and_detach_preserve_all_tenant_records_and_credentials(): void
    {
        $vehicle=Vehicle::create(['parent_id'=>$this->a->id,'license_plate'=>'54 KEEP']);
        Service::create(['parent_id'=>$this->a->id,'vehicle'=>$vehicle->id,'service_id'=>1]);
        Invoice::create(['parent_id'=>$this->a->id,'invoice_id'=>1]);
        Item::create(['parent_id'=>$this->a->id,'title'=>'Yağ','quantity'=>12]);
        $tables=['users','vehicles','services','invoices','items','settings','stock_movements','invoice_payments'];
        $before=collect($tables)->mapWithKeys(fn($table)=>[$table=>DB::table($table)->orderBy('id')->get()->toJson()]);
        $this->actingAs($this->admin);
        $this->post(route('branches.membership',$this->group->id),['owner_id'=>$this->a->id,'version'=>$this->group->fresh()->version,'action'=>'detach','confirm'=>1])->assertSessionHas('success');
        $this->attach($this->a);
        foreach($before as $table=>$json) $this->assertSame($json,DB::table($table)->orderBy('id')->get()->toJson(),$table);
        $this->assertSame($this->a->id,Vehicle::find($vehicle->id)->parent_id);
    }
    public function test_two_tabs_write_to_their_explicit_branch_without_shared_selection(): void
    {
        $a=$this->url('tax.store',$this->a);
        $b=$this->url('tax.store',$this->b);
        $this->actingAs($this->manager);
        $this->post($b,['title'=>'B vergisi','rate'=>20])->assertSessionHas('success');
        $this->post($a,['title'=>'A vergisi','rate'=>10])->assertSessionHas('success');
        $this->assertDatabaseHas('taxes',['parent_id'=>$this->a->id,'title'=>'A vergisi']);
        $this->assertDatabaseHas('taxes',['parent_id'=>$this->b->id,'title'=>'B vergisi']);
        $this->assertDatabaseMissing('taxes',['parent_id'=>$this->a->id,'title'=>'B vergisi']);
        $this->assertSame($this->manager->id,auth()->id());
        $this->assertNull(BranchContext::current());
    }
    public function test_manager_cannot_mutate_another_branches_resource(): void
    {
        $tax=new Tax; $tax->title='A'; $tax->rate=20; $tax->parent_id=$this->a->id; $tax->save();
        $this->actingAs($this->manager)->put($this->url('tax.update',$this->b,['tax'=>$tax->id]),['title'=>'Wrong','rate'=>1])->assertForbidden();
        $this->assertSame('A',$tax->fresh()->title);
    }
    public function test_detach_reattach_invalidates_old_tabs_even_for_same_owner(): void
    {
        $url=$this->url('tax.store',$this->a);
        $this->actingAs($this->admin);
        app(BusinessGroups::class)->membership($this->group->id,$this->a->id,$this->group->fresh()->version,false);
        $this->actingAs($this->manager)->post($url,['title'=>'Old','rate'=>1])->assertStatus(409);
        $this->attach($this->a);
        $this->actingAs($this->manager)->post($url,['title'=>'Still old','rate'=>1])->assertStatus(409);
        $this->post($this->url('tax.store',$this->a),['title'=>'Fresh','rate'=>1])->assertSessionHas('success');
        $this->assertSame(['Fresh'],Tax::pluck('title')->all());
    }
    public function test_forged_missing_conflicting_and_other_actor_contexts_are_rejected(): void
    {
        $url=$this->url('tax.store',$this->a);
        $this->actingAs($this->manager)->post(route('tax.store'),['title'=>'Missing','rate'=>1])->assertStatus(409);
        $this->post(route('tax.store',['_branch'=>'fake']),['title'=>'Fake','rate'=>1])->assertStatus(409);
        $this->post($url,['_branch'=>$this->token($this->b),'title'=>'Conflict','rate'=>1])->assertStatus(409);
        $this->actingAs($this->admin);
        $other=app(BusinessGroups::class)->create(['name'=>'Other','manager_name'=>'Other manager','email'=>'other@test.test','password'=>'long-password']);
        $this->actingAs($other->manager)->post($url,['title'=>'Other','rate'=>1])->assertStatus(409);
        $this->assertSame(0,Tax::count());
    }
    public function test_admin_platform_and_legacy_subscription_endpoints_stay_blocked(): void
    {
        $token=$this->token($this->a);
        $this->actingAs($this->manager);
        foreach(['backups.index','users.index','subscriptions.index','communication-settings.index'] as $route)
            $this->get(route($route,['_branch'=>$token]))->assertForbidden();
    }
    public function test_branch_recovery_version_and_suspension_are_enforced(): void
    {
        $url=$this->url('tax.store',$this->a);
        DB::table('users')->where('id',$this->a->id)->increment('recovery_version');
        $this->actingAs($this->manager)->post($url,['title'=>'Old','rate'=>1])->assertStatus(409);
        DB::table('users')->where('id',$this->a->id)->update(['subscription_suspended_at'=>now()]);
        $this->post($this->url('tax.store',$this->a),['title'=>'Suspended','rate'=>1])->assertForbidden();
        $this->assertSame(0,Tax::count());
    }
    public function test_manager_can_use_branch_settings_without_changing_owner_login_identity(): void
    {
        $this->actingAs($this->manager)->get($this->url('setting.index',$this->a))->assertOk()->assertSee('Seçili şube');
        $this->get(route('setting.index'))->assertOk()->assertSee('Şube merkez hesabı');
        $this->assertSame('sakarya@branches.test',$this->a->fresh()->email);
        $this->assertSame($this->manager->id,auth()->id());
    }
    public function test_double_attach_and_stale_admin_forms_do_not_move_or_detach_shops(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('branches.membership',$this->group->id),['owner_id'=>$this->a->id,'version'=>$this->group->fresh()->version,'action'=>'attach','confirm'=>1])->assertSessionHasErrors('owner_id');
        $this->post(route('branches.membership',$this->group->id),['owner_id'=>$this->a->id,'version'=>1,'action'=>'detach','confirm'=>1])->assertStatus(409);
        $this->assertDatabaseHas('business_branches',['owner_id'=>$this->a->id,'group_id'=>$this->group->id]);
    }
    public function test_deactivated_group_revokes_manager_but_not_shop_owner(): void
    {
        $url=$this->url('tax.store',$this->a);
        $this->actingAs($this->admin);
        app(BusinessGroups::class)->status($this->group->id,$this->group->fresh()->version,false);
        $this->actingAs($this->manager)->post($url,['title'=>'No','rate'=>1])->assertStatus(409);
        $this->get(route('branches.portal'))->assertForbidden();
        $this->actingAs($this->a)->post(route('tax.store'),['title'=>'Own shop','rate'=>1])->assertSessionHas('success');
        $this->assertDatabaseHas('taxes',['parent_id'=>$this->a->id,'title'=>'Own shop']);
    }
    public function test_customer_links_do_not_receive_private_branch_context(): void
    {
        $this->actingAs($this->manager);
        $this->get($this->url('client.simple.create',$this->a))->assertOk()->assertSee('_branch=');
        $this->assertStringNotContainsString('_branch',route('vehicle-portal.show',str_repeat('a',64)));
        $this->assertStringNotContainsString('_branch',route('branches.portal'));
    }

    public function test_invoice_operations_remain_scoped_and_record_actual_manager_as_actor(): void
    {
        $customer=User::create(['name'=>'Müşteri','type'=>'client','password'=>'x','parent_id'=>$this->a->id]);
        $service=Service::create(['parent_id'=>$this->a->id,'client'=>$customer->id,'external_labor_amount'=>1000]);
        $invoice=Invoice::create(['parent_id'=>$this->a->id,'client'=>$customer->id,'service'=>$service->id,'external_labor_amount'=>1000,'invoice_id'=>1,'status'=>0]);
        $this->actingAs($this->manager);
        $page=$this->get($this->url('invoice.payment',$this->a,['id'=>$invoice->id]))->assertOk();
        preg_match('/name="operation_token"[^>]*value="([^"]+)"/',$page->getContent(),$match);
        if (!$match) preg_match('/value="([^"]+)"[^>]*name="operation_token"/',$page->getContent(),$match);
        $this->assertNotEmpty($match,'The payment form must carry an actor/tenant-bound operation token.');
        $data=['operation_token'=>html_entity_decode($match[1]),'invoice_revision'=>0,'amount'=>100,'payment_date'=>now()->toDateString()];
        $this->post($this->url('invoice.payment.store',$this->b,['id'=>$invoice->id]),$data)->assertSessionHasErrors('operation_token');
        $this->assertSame(0,DB::table('invoice_payments')->count());
        $this->post($this->url('invoice.payment.store',$this->a,['id'=>$invoice->id]),$data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('invoice_changes',['parent_id'=>$this->a->id,'actor_id'=>$this->manager->id,'action'=>'invoice.payment.store']);
        $this->assertEquals(100,$invoice->payments()->sum('amount'));
        $this->post($this->url('invoice.payment.store',$this->a,['id'=>$invoice->id]),$data)->assertSessionHasNoErrors();
        $this->assertEquals(100,$invoice->payments()->sum('amount'));
    }

    public function test_detached_shop_staff_never_gain_access_to_a_sibling_branch(): void
    {
        $tax=new Tax; $tax->title='İstanbul'; $tax->rate=20; $tax->parent_id=$this->b->id; $tax->save();
        $staff=User::create(['name'=>'Sakarya personel','type'=>'employee','email'=>'staff@test.test','password'=>'x','parent_id'=>$this->a->id]);
        $staff->assignRole($this->a->roles->first());
        $this->actingAs($staff)->put($this->url('tax.update',$this->b,['tax'=>$tax->id]),['title'=>'Wrong','rate'=>1])->assertForbidden();
        $this->assertSame('İstanbul',$tax->fresh()->title);
        $this->assertSame($this->a->id,$staff->fresh()->parent_id);
    }

    public function test_context_does_not_grant_permissions_removed_from_shop_owner(): void
    {
        $this->a->roles->first()->revokePermissionTo('create tax');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($this->manager)->post($this->url('tax.store',$this->a),['title'=>'Not allowed','rate'=>1])->assertSessionHas('error');
        $this->assertSame(0,Tax::count());
    }

    public function test_group_shops_are_not_automatically_purged_as_abandoned_demos(): void
    {
        DB::table('users')->where('id',$this->a->id)->update(['subscription'=>Subscription::where('vehicle_limit',50)->value('id'),
            'demo_expires_at'=>now()->subDays(40),'last_business_login_at'=>now()->subDays(60),'paid_subscription_at'=>null]);
        $this->assertFalse(app(DemoCleanup::class)->candidates()->whereKey($this->a->id)->exists());
        $this->assertFalse(app(DemoCleanup::class)->delete($this->a->id));
        $this->assertNotNull($this->a->fresh());
    }

    public function test_public_registration_ignores_branch_account_fields(): void
    {
        auth()->logout();
        $this->post('/register',['name'=>'Demo business','email'=>'newdemo@test.test','password'=>'demo-password-123',
            'password_confirmation'=>'demo-password-123','type'=>'branch_manager','group_id'=>$this->group->id])->assertSessionHasNoErrors();
        $user=User::where('email','newdemo@test.test')->firstOrFail();
        $this->assertSame('owner',$user->type);
        $this->assertNotNull($user->demo_expires_at);
        $this->assertSame(1,BusinessGroup::count());
        $this->assertDatabaseMissing('business_branches',['owner_id'=>$user->id]);
    }

    public function test_super_admin_form_renders_and_linked_account_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin)->get(route('branches.index'))->assertOk()->assertSee('Şubeyi ayır')->assertSee('Merkez hesabı oluştur');
        $this->actingAs($this->a)->delete(route('setting.account.delete'))->assertStatus(409);
        $this->assertNotNull($this->a->fresh());
    }
}
