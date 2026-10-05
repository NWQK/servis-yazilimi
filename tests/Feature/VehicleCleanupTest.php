<?php
namespace Tests\Feature;
use App\Models\{User,Vehicle,Service,Invoice,InvoicePayment,VehicleQrCode,Subscription,Item};
use App\Services\{VehicleCleanup,VehicleCapacity};
use Illuminate\Support\Facades\{DB,Gate};
use Carbon\Carbon;
use Tests\TestCase;

class VehicleCleanupTest extends TestCase
{
    private $owner;
    private $client;
    private $cleanup;
    protected function setUp(): void
    {
        parent::setUp(); config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array']); DB::purge('sqlite');
        $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00','Europe/Istanbul'));
        $this->owner=User::create(['name'=>'Servis','email'=>'cleanup-owner@example.test','password'=>'x','type'=>'owner']);
        $this->client=User::create(['name'=>'Temizlik Müşterisi','email'=>'cleanup-client@example.test','password'=>'x','type'=>'client','parent_id'=>$this->owner->id,'is_active'=>1]);
        $this->actingAs($this->owner)->withoutMiddleware(\App\Http\Middleware\XSS::class);
        Gate::before(fn($user)=>$user->type==='owner' ? true : false);
        $this->cleanup=app(VehicleCleanup::class);
    }
    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }
    private function vehicle(string $date='2025-01-01',?User $client=null): Vehicle
    {
        $client=$client ?? $this->client;
        $vehicle=Vehicle::create(['parent_id'=>$client->parent_id,'client'=>$client->id,'license_plate'=>'34 CL '.$client->id,'vehicle_id'=>Vehicle::withTrashed()->count()+1]);
        DB::table('vehicles')->where('id',$vehicle->id)->update(['created_at'=>$date.' 08:00:00','updated_at'=>$date.' 08:00:00']); return $vehicle->fresh();
    }
    private function service(Vehicle $vehicle,string $date='2025-01-01',string $status='completed'): Service
    {
        $service=Service::create(['parent_id'=>$vehicle->parent_id,'client'=>$vehicle->client,'vehicle'=>$vehicle->id,'status'=>$status,'service_date'=>$date]);
        DB::table('services')->where('id',$service->id)->update(['created_at'=>$date.' 08:00:00','updated_at'=>$date.' 08:00:00']); return $service->fresh();
    }
    public function test_inactivity_windows_and_recent_financial_activity_are_correct()
    {
        $old=$this->vehicle(); $middle=$this->vehicle('2026-05-01'); $recent=$this->vehicle('2026-09-01');
        $this->service($old); $this->service($old);
        $this->assertSame([$old->id],$this->cleanup->query($this->owner->id,['months'=>'6'])->pluck('vehicles.id')->all());
        $this->assertSame(2,$this->cleanup->query($this->owner->id,['months'=>'3'])->count());
        $this->assertSame(1,$this->cleanup->query($this->owner->id,['months'=>'12'])->count());
        $row=$this->cleanup->query($this->owner->id,['months'=>'6'])->first(); $this->assertSame(2,$row->services_count);
        $s=Service::where('vehicle',$old->id)->first();
        $invoice=Invoice::create(['parent_id'=>$this->owner->id,'client'=>$this->client->id,'service'=>$s->id,'invoice_date'=>'2025-01-01']);
        DB::table('invoices')->where('id',$invoice->id)->update(['created_at'=>'2025-01-01 08:00:00','updated_at'=>'2025-01-01 08:00:00']);
        DB::table('invoice_payments')->insert(['parent_id'=>$this->owner->id,'invoice_id'=>$invoice->id,'payment_date'=>'2026-10-04','amount'=>100,'created_at'=>'2026-10-04 08:00:00','updated_at'=>'2026-10-04 08:00:00']);
        $this->assertSame(0,$this->cleanup->query($this->owner->id,['months'=>'6'])->count());
        $this->get(route('vehicle-cleanup.index',['months'=>'all']))->assertOk()->assertSee('Toplam servis')->assertSee('Son işlem')->assertSee('Temizlik Müşterisi');
        $this->assertSame(2,$this->cleanup->query($this->owner->id,['months'=>'all','never_serviced'=>1])->count());
    }
    public function test_bulk_deletion_archives_customer_but_preserves_invoices_payments_services_and_qr()
    {
        $v=$this->vehicle(); $service=$this->service($v);
        $invoice=Invoice::create(['parent_id'=>$this->owner->id,'client'=>$this->client->id,'service'=>$service->id,'invoice_date'=>'2025-01-01','external_labor_amount'=>200,'status'=>2]);
        DB::table('invoices')->where('id',$invoice->id)->update(['created_at'=>'2025-01-01 08:00:00','updated_at'=>'2025-01-01 08:00:00']);
        DB::table('invoice_payments')->insert(['parent_id'=>$this->owner->id,'invoice_id'=>$invoice->id,'amount'=>200,'created_at'=>'2025-01-01 08:00:00','updated_at'=>'2025-01-01 08:00:00']);
        $qr=VehicleQrCode::create(['parent_id'=>$this->owner->id,'vehicle_id'=>$v->id,'token'=>str_repeat('e',64),'assigned_at'=>now()]);
        $this->delete(route('vehicle-cleanup.destroy'),['months'=>'6','ids'=>[$v->id]])->assertSessionHasErrors('confirm');
        $this->delete(route('vehicle-cleanup.destroy'),['months'=>'6','ids'=>[$v->id],'confirm'=>1])->assertSessionHasNoErrors();
        $this->assertSame(0,Vehicle::count()); $this->assertSame(1,Vehicle::onlyTrashed()->count());
        $this->assertNotNull($this->client->fresh()->client_archived_at);
        $this->assertSame(1,Invoice::count()); $this->assertSame(1,InvoicePayment::count()); $this->assertSame(1,Service::count());
        $this->assertEquals(200,$invoice->fresh()->getInvoiceAllTotalAmount());
        $this->assertEquals(200,$invoice->payments()->sum('amount'));
        $this->assertEquals($v->id,$service->fresh()->vehicles->id);
        $this->assertEquals($this->client->name,$invoice->fresh()->clients->name);
        $this->get(route('invoice.show',encrypt($invoice->id)))->assertOk()->assertSee($this->client->name);
        $this->get(route('invoice.edit',encrypt($invoice->id)))->assertOk()->assertSee($this->client->name);
        $this->get(route('invoice.create'))->assertOk()->assertDontSee($this->client->name);
        $this->get(route('client.index'))->assertOk()->assertDontSee($this->client->email);
        $this->assertSame(0,VehicleQrCode::available()->where('id',$qr->id)->count());
        $this->get('/q/'.$qr->token)->assertNotFound();
        $this->get(route('vehicle-cleanup.index',['view'=>'trash','months'=>'all']))->assertOk()->assertSee('Seçilenleri geri getir');
        $this->post(route('vehicle-cleanup.restore'),['ids'=>[$v->id],'confirm'=>1])->assertSessionHasNoErrors();
        $this->assertSame(1,Vehicle::count()); $this->assertNull($this->client->fresh()->client_archived_at); $this->assertEquals(1,$this->client->fresh()->is_active);
        $this->get('/q/'.$qr->token)->assertOk();
    }
    public function test_customer_with_another_active_vehicle_is_retained_and_single_delete_uses_same_policy()
    {
        $first=$this->vehicle(); $second=$this->vehicle();
        $this->delete(route('vehicle.destroy',$first->id))->assertSessionHasNoErrors();
        $this->assertNull($this->client->fresh()->client_archived_at);
        $this->assertSame(1,Vehicle::count());
        $this->delete(route('vehicle.destroy',$second->id))->assertSessionHasNoErrors();
        $this->assertNotNull($this->client->fresh()->client_archived_at);
    }
    public function test_open_services_and_stale_filter_prevent_whole_batch_deletion()
    {
        $first=$this->vehicle(); $second=$this->vehicle(); $this->service($second,'2025-01-01','in_progress');
        $this->delete(route('vehicle-cleanup.destroy'),['months'=>'6','ids'=>[$first->id,$second->id],'confirm'=>1])->assertSessionHasErrors('ids');
        $this->assertSame(2,Vehicle::count());
        Service::where('vehicle',$second->id)->update(['status'=>'completed']);
        $this->delete(route('vehicle-cleanup.destroy'),['months'=>'6','ids'=>[$first->id,$second->id],'confirm'=>1])->assertSessionHasErrors('ids');
        $this->assertSame(2,Vehicle::count()); $this->assertNull($this->client->fresh()->client_archived_at);
    }
    public function test_tenant_permissions_and_capacity_are_enforced()
    {
        $v=$this->vehicle(); $other=User::create(['name'=>'Other','email'=>'other-cleanup@example.test','password'=>'x','type'=>'owner']);
        $this->actingAs($other)->delete(route('vehicle-cleanup.destroy'),['months'=>'all','ids'=>[$v->id],'confirm'=>1])->assertSessionHasErrors('ids');
        $this->actingAs($this->client)->get(route('vehicle-cleanup.index'))->assertForbidden();
        $this->actingAs($this->owner); $this->cleanup->remove($this->owner->id,[$v->id]);
        $this->owner->subscription=Subscription::where('vehicle_limit',50)->value('id'); $this->owner->save();
        DB::table('vehicles')->insert(array_fill(0,50,['parent_id'=>$this->owner->id]));
        $this->post(route('vehicle-cleanup.restore'),['ids'=>[$v->id],'confirm'=>1])->assertSessionHasErrors('vehicle_limit');
        $this->assertSame(50,Vehicle::count()); $this->assertSame(1,Vehicle::onlyTrashed()->count());
        $this->assertNotNull($this->client->fresh()->client_archived_at);
    }
}
