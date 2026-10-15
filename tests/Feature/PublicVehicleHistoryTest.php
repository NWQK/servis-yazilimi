<?php
namespace Tests\Feature;
use App\Models\{User,Vehicle,Service,VehicleType,VehicleBrand};
use App\Services\{PublicVehicleHistory,ChassisNumber,VehicleQrPool};
use Illuminate\Support\Facades\{DB,Gate};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PublicVehicleHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array']);DB::purge('sqlite');
        $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        User::create(['name'=>'Admin','email'=>'admin@vin.test','password'=>'x','type'=>'super admin']);
        Gate::before(fn()=>true);$this->withoutMiddleware(\App\Http\Middleware\XSS::class);
    }
    private function vehicle(string $shop,string $vin='WVWZZZ1JZXW000001'): Vehicle
    {
        $owner=User::create(['name'=>$shop,'email'=>$shop.'@vin.test','password'=>'x','type'=>'owner','parent_id'=>1]);
        DB::table('settings')->insert(['parent_id'=>$owner->id,'name'=>'company_name','value'=>$shop]);
        return Vehicle::create(['parent_id'=>$owner->id,'chassis_no'=>$vin,'license_plate'=>'34 SECRET','mileage'=>120000]);
    }
    private function service(Vehicle $vehicle,string $status='completed'): Service
    {
        $service=Service::create(['parent_id'=>$vehicle->parent_id,'vehicle'=>$vehicle->id,'status'=>$status,'service_date'=>'2026-10-10','notes'=>'PRIVATE-SERVICE-NOTE','external_labor_amount'=>98765]);
        $type=DB::table('service_types')->insertGetId(['parent_id'=>$vehicle->parent_id,'type'=>'Yağ ve filtre değişimi']);
        DB::table('service_items')->insert(['parent_id'=>$vehicle->parent_id,'service_id'=>$service->id,'type_id'=>$type,'rate'=>12345,'note'=>'PRIVATE-LINE-NOTE']);
        return $service;
    }
    private function search(string $vin='WVWZZZ1JZXW000001')
    {
        $this->post(route('vehicle-history.search'),['chassis_no'=>$vin])->assertRedirect(route('vehicle-history.index'));
        return $this->get(route('vehicle-history.index'));
    }
    public function test_public_lookup_combines_only_shared_completed_summaries_without_private_data(): void
    {
        $a=$this->vehicle('ShopA');$b=$this->vehicle('ShopB');$c=$this->vehicle('PrivateShop');
        foreach([$a,$b,$c] as $v)$this->service($v);
        $this->service($a,'in_progress');
        app(PublicVehicleHistory::class)->share($a,true);app(PublicVehicleHistory::class)->share($b,true);
        $r=$this->search('wvw zzz1jz-xw000001');$r->assertOk()->assertSee('ShopA')->assertSee('ShopB')->assertDontSee('PrivateShop')
            ->assertSee('2 servis kaydı')->assertSee('Yağ ve filtre değişimi')->assertSee('120.000 km')
            ->assertDontSee('PRIVATE-SERVICE-NOTE')->assertDontSee('PRIVATE-LINE-NOTE')->assertDontSee('98765')
            ->assertDontSee('12345')->assertDontSee('34 SECRET')->assertDontSee('WVWZZZ1JZXW000001')
            ->assertSee('%100 doğrulanmış değildir')->assertHeader('Cache-Control','no-store, private');
        $this->assertSame($a->parent_id,$a->fresh()->parent_id);$this->assertSame($b->parent_id,$b->fresh()->parent_id);
    }
    public function test_sharing_revocation_identity_changes_and_deletion_remove_results_immediately(): void
    {
        $v=$this->vehicle('ShopA');$this->service($v);$history=app(PublicVehicleHistory::class);
        $history->share($v,true);$this->search()->assertSee('1 servis kaydı');
        $history->share($v,false);$this->search()->assertSee('0 servis kaydı');
        $history->share($v,true);$v->update(['chassis_no'=>'WVWZZZ1JZXW000002']);
        $this->assertFalse($history->isPublic($v));$this->search()->assertSee('0 servis kaydı');
        $history->share($v,true);$this->search('WVWZZZ1JZXW000002')->assertSee('1 servis kaydı');
        $v->delete();$this->search('WVWZZZ1JZXW000002')->assertSee('0 servis kaydı');
        $v->restore();$this->assertFalse($history->isPublic($v));
    }
    public function test_mileage_is_a_service_snapshot_and_legacy_unknown_mileage_is_not_invented(): void
    {
        $v=$this->vehicle('ShopA');$this->service($v);$v->update(['mileage'=>180000]);
        app(PublicVehicleHistory::class)->share($v,true);
        $this->search()->assertSee('120.000 km')->assertDontSee('180.000 km');
        DB::table('services')->where('vehicle',$v->id)->update(['mileage_at_service'=>null]);
        $this->search()->assertSee('Belirtilmedi')->assertDontSee('180.000 km');
    }
    public function test_new_vehicle_requires_vin_and_existing_record_change_requires_confirmation(): void
    {
        $v=$this->vehicle('ShopA');$owner=User::find($v->parent_id);$this->actingAs($owner);
        $this->post(route('vehicle.store'),[])->assertSessionHasErrors('chassis_no');
        $this->assertSame('ABC12345',ChassisNumber::normalize(' abc-123 45 '));
        $this->assertTrue(ChassisNumber::valid('C123'));
        $this->assertFalse(ChassisNumber::valid('WVWZZZ1JZXW00000I'));
        $request=Request::create('/vehicle','PUT',['chassis_no'=>'WVWZZZ1JZXW000002']);
        try {ChassisNumber::validateForm($request,$v);$this->fail('Changed VIN must require confirmation');}
        catch(ValidationException $e){$this->assertArrayHasKey('chassis_change_confirm',$e->errors());}
        $request->merge(['chassis_change_confirm'=>1]);ChassisNumber::validateForm($request,$v);
        $this->assertSame('WVWZZZ1JZXW000002',$request->chassis_no);
    }
    public function test_same_vin_allowed_across_shops_but_duplicate_within_owner_rolls_back(): void
    {
        $v=$this->vehicle('ShopA');$this->vehicle('ShopB');$owner=User::find($v->parent_id);$this->actingAs($owner);
        $before=Vehicle::count();
        try {app(VehicleQrPool::class)->createVehicle($owner->id,null,fn()=>Vehicle::create(['parent_id'=>$owner->id,'chassis_no'=>$v->chassis_no]));$this->fail('Duplicate should fail');}
        catch(ValidationException $e){$this->assertArrayHasKey('chassis_no',$e->errors());}
        $this->assertSame($before,Vehicle::count());
        $this->assertSame(2,DB::table('vehicle_history_index')->count());
    }
    public function test_sharing_endpoint_is_scoped_to_own_shop_and_old_records_start_private(): void
    {
        $a=$this->vehicle('ShopA');$b=$this->vehicle('ShopB');$this->assertFalse(app(PublicVehicleHistory::class)->isPublic($a));
        $this->actingAs(User::find($a->parent_id))->post(route('vehicle.history-sharing',$b),['history_public'=>1])->assertForbidden();
        $this->post(route('vehicle.history-sharing',$a),['history_public'=>1])->assertSessionHasNoErrors();
        $this->assertTrue(app(PublicVehicleHistory::class)->isPublic($a));
    }
    public function test_search_rejects_invalid_input_and_is_rate_limited_without_putting_vin_in_url(): void
    {
        $this->post(route('vehicle-history.search'),['chassis_no'=>'<script>'])->assertSessionHasErrors('chassis_no');
        for($i=0;$i<9;$i++)$this->post(route('vehicle-history.search'),['chassis_no'=>'WVWZZZ1JZXW000001'])->assertRedirect(route('vehicle-history.index'));
        $this->post(route('vehicle-history.search'),['chassis_no'=>'WVWZZZ1JZXW000001'])->assertStatus(429);
    }
    public function test_audit_stale_model_save_cannot_revert_the_search_index(): void
    {
        $v=$this->vehicle('ShopA');$stale=Vehicle::find($v->id);
        $v->update(['chassis_no'=>'WVWZZZ1JZXW000002']);app(PublicVehicleHistory::class)->share($v,true);
        $stale->update(['mileage'=>130000]);
        $this->assertSame(hash('sha256','WVWZZZ1JZXW000002'),DB::table('vehicle_history_index')->where('vehicle_id',$v->id)->value('vin_hash'));
        $this->assertTrue(app(PublicVehicleHistory::class)->isPublic($v->fresh()));
    }
    public function test_audit_moving_a_vehicle_between_owners_revokes_sharing(): void
    {
        $v=$this->vehicle('ShopA');$b=$this->vehicle('ShopB','WVWZZZ1JZXW000002');
        app(PublicVehicleHistory::class)->share($v,true);$v->update(['parent_id'=>$b->parent_id]);
        $this->assertFalse(app(PublicVehicleHistory::class)->isPublic($v));
    }
    public function test_audit_trash_restore_rejects_a_reused_chassis_number(): void
    {
        $v=$this->vehicle('ShopA');$v->delete();
        Vehicle::create(['parent_id'=>$v->parent_id,'chassis_no'=>$v->chassis_no]);
        $this->expectException(ValidationException::class);
        app(\App\Services\VehicleCleanup::class)->restore($v->parent_id,[$v->id]);
    }
    public function test_audit_other_shop_cannot_delete_a_service_or_change_its_operation_line(): void
    {
        $a=$this->vehicle('ShopA');$b=$this->vehicle('ShopB');$own=$this->service($a);$foreign=$this->service($b);
        $this->actingAs(User::find($a->parent_id))->delete(route('service.destroy',$foreign))->assertForbidden();
        $this->assertNotNull($foreign->fresh());
        $a->update(['client'=>$a->parent_id]);
        $line=DB::table('service_items')->where('service_id',$foreign->id)->first();
        $ownType=DB::table('service_types')->where('parent_id',$a->parent_id)->value('id');
        $this->put(route('service.update',$own),['vehicle'=>$a->id,'client'=>$a->parent_id,'assign'=>$a->parent_id,
            'status'=>'completed','types'=>[['id'=>$line->id,'service_type'=>$ownType,'rate'=>1,'note'=>'changed']]])
            ->assertSessionHas('error');
        $this->assertEquals($b->parent_id,DB::table('service_items')->find($line->id)->parent_id);
        $this->assertSame('PRIVATE-LINE-NOTE',DB::table('service_items')->find($line->id)->note);
    }
    public function test_public_results_are_paginated_and_search_session_expires(): void
    {
        $v=$this->vehicle('ShopA');app(PublicVehicleHistory::class)->share($v,true);
        for($i=0;$i<25;$i++)$this->service($v);
        $this->search()->assertSee('25 servis kaydı')->assertSee('Sonraki');
        $secondPage=$this->get(route('vehicle-history.index',['page'=>2]))->assertOk()->assertSee('25 servis kaydı');
        $this->assertSame(5,substr_count($secondPage->getContent(),'class="history-entry"'));
        $this->get(route('vehicle-history.index'))->assertOk();
        $rows=app(PublicVehicleHistory::class)->results(hash('sha256',$v->chassis_no));
        $this->assertSame(20,$rows->count());$this->assertSame(25,$rows->total());
        $this->travel(16)->minutes();
        $this->get(route('vehicle-history.index'))->assertOk()->assertSee('Yalnızca paylaşılan işlem özetleri.')->assertDontSee('ShopA');
    }

}
