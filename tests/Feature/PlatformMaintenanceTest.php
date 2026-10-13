<?php
namespace Tests\Feature;

use App\Models\{User, AdminAuditLog};
use App\Services\{PlatformMaintenance, TermsAndConditions};
use Illuminate\Support\Facades\{Auth, DB, Gate};
use Tests\TestCase;

class PlatformMaintenanceTest extends TestCase
{
    private User $admin;
    private User $owner;
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array']);
        DB::purge('sqlite');
        $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        $this->admin=User::create(['name'=>'Admin','email'=>'maintenance-admin@example.test','password'=>'x','type'=>'super admin']);
        $this->owner=User::create(['name'=>'Shop','email'=>'maintenance-owner@example.test','password'=>'x','type'=>'owner']);
        Gate::before(fn($user)=>true);
        DB::table('settings')->insert(['parent_id'=>$this->admin->id,'name'=>'pricing_feature','value'=>'off']);
        $this->actingAs($this->admin)->withoutMiddleware(\App\Http\Middleware\XSS::class);
    }
    public function test_admin_can_enable_and_disable_without_losing_access(): void
    {
        $this->assertFalse(PlatformMaintenance::enabled());
        $this->post(route('maintenance.save'),['enabled'=>1])->assertSessionHas('success');
        $this->assertTrue(PlatformMaintenance::enabled());
        $this->get(route('maintenance.index'))->assertOk()->assertSee('Bakım modu açık');
        $this->get(route('backups.index'))->assertOk();
        $this->assertSame('maintenance.status',AdminAuditLog::firstOrFail()->action);
        $this->post(route('maintenance.save'),['enabled'=>0])->assertSessionHas('success');
        $this->assertFalse(PlatformMaintenance::enabled());
        $this->actingAs($this->owner)->get('/client')->assertOk();
    }
    public function test_panels_customer_links_booking_and_writes_are_blocked_but_public_information_stays_open(): void
    {
        PlatformMaintenance::set(true);
        $this->actingAs($this->owner)->get('/client')->assertStatus(503)->assertSee('Birazdan yeniden buradayız.')->assertHeader('Retry-After','300');
        $this->post(route('client.simple.store'),['name'=>'Blocked','phone_number'=>'5551234567'])->assertStatus(503);
        $this->assertSame(0,User::where('type','client')->count());
        $this->getJson('/appointments/notifications')->assertStatus(503)->assertJsonPath('message','Sistem bakımda. Lütfen kısa bir süre sonra tekrar deneyin.');
        $this->get('/tanitim')->assertOk();
        $this->get(route('page',TermsAndConditions::SLUG))->assertOk();
        Auth::logout();
        $this->get('/tanitim')->assertOk();
        $this->get('/randevu')->assertStatus(503);
        $this->get('/q/'.str_repeat('a',64))->assertStatus(503);
        $this->get('/forgot-password')->assertOk();
        $this->post('/login',[])->assertSessionHasErrors(['email','password']);
    }
    public function test_non_admin_cannot_change_mode_and_no_query_parameter_bypass_exists(): void
    {
        $this->actingAs($this->owner)->get(route('maintenance.index'))->assertForbidden();
        $this->post(route('maintenance.save'),['enabled'=>1])->assertForbidden();
        $this->assertFalse(PlatformMaintenance::enabled());
        PlatformMaintenance::set(true);
        $this->get('/client?maintenance=0&bypass=1')->assertStatus(503);
        $this->post(route('maintenance.save'),['enabled'=>0])->assertStatus(503);
        $this->assertTrue(PlatformMaintenance::enabled());
    }
}
