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
    public function test_staff_branch_managers_and_clients_cannot_moderate_even_with_gate_permissions(): void {
        // Exercise controller role checks even if branch routing has already been bypassed.
        $this->withoutMiddleware(\App\Http\Middleware\BranchAccess::class);
        $v=$this->vehicle('ShopA');$this->service($v);$admin=auth()->user();
        foreach(['employee','branch_manager','client'] as $type){
            $this->actingAs($admin);
            $user=User::create(['name'=>'Synthetic '.$type,'email'=>$type.'@roles.test','type'=>$type,'parent_id'=>$v->parent_id,'password'=>'x','is_active'=>1]);
            $this->actingAs($user)->get(route('vehicle-history-admin.index'))->assertForbidden();
            $this->post(route('vehicle-history-admin.update',$v->id),['hidden'=>1,'reason'=>'Forged administrator request','expected_revision'=>0])->assertForbidden();
        }
        $this->assertFalse(app(PublicVehicleHistory::class)->hidden($v->id));
    }
    public function test_brand_model_and_only_three_plate_characters_are_public_per_business(): void {
        $a=$this->vehicle('ShopA');$b=$this->vehicle('ShopB');
        foreach([[$a,'Ford','Focus','34 ABC 987'],[$b,'Toyota','Corolla','06 XYZ 123']] as [$v,$make,$model,$plate]){
            $type=VehicleType::create(['parent_id'=>$v->parent_id,'type'=>$make]);
            $brand=VehicleBrand::create(['parent_id'=>$v->parent_id,'type'=>$type->id,'name'=>$model]);
            $v->update(['type'=>$type->id,'brand'=>$brand->id,'license_plate'=>$plate]);$this->service($v);
        }
        $this->search()->assertSee('ShopA')->assertSee('ShopB')->assertSee('Ford Focus')->assertSee('Toyota Corolla')
            ->assertSee('••• 987')->assertSee('••• 123')->assertDontSee('34 ABC 987')->assertDontSee('06 XYZ 123')
            ->assertDontSee('PRIVATE-SERVICE-NOTE')->assertDontSee('PRIVATE-LINE-NOTE')->assertDontSee('WVWZZZ1JZXW000001');
        $this->assertSame('987',DB::table('public_service_history')->where('vehicle_id',$a->id)->value('plate_suffix'));
    }
    public function test_published_summary_survives_service_vehicle_and_owner_source_changes(): void {
        $v=$this->vehicle('OriginalShop');$job=$this->service($v);
        $job->update(['status'=>'in_progress','service_date'=>'2026-10-20','mileage_at_service'=>999999]);
        DB::table('service_items')->where('service_id',$job->id)->delete();$job->delete();
        $v->forceDelete();DB::table('users')->where('id',$v->parent_id)->delete();
        $this->search()->assertSee('OriginalShop')->assertSee('Yağ ve filtre değişimi')->assertSee('120.000 km')->assertDontSee('999.999 km');
        $this->assertSame(1,DB::table('public_service_history')->count());
    }
    public function test_admin_screens_validate_reason_and_reject_stale_visibility_writes(): void {
        $v=$this->vehicle('ShopA');$this->service($v);
        $this->get(route('vehicle-history-admin.index'))->assertOk()->assertSee('ShopA');
        $this->get(route('vehicle-history-admin.show',$v->id))->assertOk()->assertSee('Gizleme gerekçesi')->assertSee('Yağ ve filtre değişimi');
        $url=route('vehicle-history-admin.update',$v->id);
        $this->post($url,['hidden'=>1,'expected_revision'=>0])->assertSessionHasErrors('reason');
        $this->assertFalse(app(PublicVehicleHistory::class)->hidden($v->id));
        $this->post($url,['hidden'=>1,'reason'=>'Yanlış şasi incelemesi','expected_revision'=>0])->assertRedirect();
        $this->post($url,['hidden'=>0,'reason'=>'Eski form ile açma','expected_revision'=>0])->assertStatus(409);
        $this->search()->assertSee('0 servis kaydı');
        $this->post($url,['hidden'=>0,'reason'=>'İnceleme tamamlandı','expected_revision'=>1])->assertRedirect();
        $this->search()->assertSee('1 servis kaydı');
        $this->assertSame(2,DB::table('admin_audit_logs')->where('action','vehicle-history.visibility')->count());
    }
    public function test_deleted_vehicle_can_still_be_moderated_and_owner_cannot_bypass_decision(): void {
        $v=$this->vehicle('ShopA');$this->service($v);$admin=auth()->user();
        $this->post(route('vehicle-history-admin.update',$v->id),['hidden'=>1,'reason'=>'Yönetici incelemesi','expected_revision'=>0])->assertRedirect();
        $this->actingAs(User::find($v->parent_id))->post(route('vehicle.history-sharing',$v),['history_public'=>1])->assertForbidden();
        $v->update(['chassis_no'=>'WVWZZZ1JZXW000002']);$v->forceDelete();
        $this->search()->assertSee('0 servis kaydı');
        $this->actingAs($admin)->get(route('vehicle-history-admin.show',$v->id))->assertOk()->assertSee('Yönetici incelemesi');
        $this->post(route('vehicle-history-admin.update',$v->id),['hidden'=>0,'reason'=>'İnceleme tamamlandı','expected_revision'=>1])->assertRedirect();
        $this->search()->assertSee('1 servis kaydı');
    }
    public function test_foreign_catalogue_ids_do_not_expose_another_business_brand_or_model(): void {
        $a=$this->vehicle('ShopA');$b=$this->vehicle('ShopB');
        $make=VehicleType::create(['parent_id'=>$b->parent_id,'type'=>'FOREIGN-PRIVATE-MAKE']);
        $model=VehicleBrand::create(['parent_id'=>$b->parent_id,'type'=>$make->id,'name'=>'FOREIGN-PRIVATE-MODEL']);
        $a->update(['type'=>$make->id,'brand'=>$model->id]);$this->service($a);
        $this->search()->assertDontSee('FOREIGN-PRIVATE-MAKE')->assertDontSee('FOREIGN-PRIVATE-MODEL');
    }
    public function test_legacy_private_records_are_backfilled_without_inventing_mileage(): void {
        \Schema::drop('public_service_history');\Schema::drop('vehicle_history_controls');
        $v=$this->vehicle('LegacyShop');
        DB::table('vehicle_history_index')->where('vehicle_id',$v->id)->update(['is_public'=>false]);
        $service=DB::table('services')->insertGetId(['parent_id'=>$v->parent_id,'vehicle'=>$v->id,'status'=>'completed','service_date'=>'2026-09-01','mileage_at_service'=>null]);
        $migration=require database_path('migrations/2026_10_20_000001_centralize_public_vehicle_history.php');$migration->up();
        $this->search()->assertSee('LegacyShop')->assertSee('1 servis kaydı')->assertSee('Belirtilmedi')->assertDontSee('120.000 km');
        $this->assertNull(DB::table('public_service_history')->where('service_id',$service)->value('mileage_at_service'));
        $this->assertSame(1,DB::table('public_service_history')->count());
        DB::table('vehicle_history_controls')->insert(['vehicle_id'=>$v->id,'hidden'=>true,'revision'=>5,'reason'=>'Kalıcı yönetici kararı']);
        $migration->up();
        $this->assertSame(1,DB::table('public_service_history')->count());
        $this->assertSame(5,DB::table('vehicle_history_controls')->where('vehicle_id',$v->id)->value('revision'));
    }
    public function test_legacy_create_sharing_flag_cannot_disable_automatic_visibility(): void {
        $old=$this->vehicle('ShopA');$owner=User::find($old->parent_id);$this->actingAs($owner);
        $make=VehicleType::create(['parent_id'=>$owner->id,'type'=>'Ford']);
        $model=VehicleBrand::create(['parent_id'=>$owner->id,'type'=>$make->id,'name'=>'Focus']);
        $v=app(\App\Services\VehicleRegistration::class)->create($owner->id,new Request([
            'client'=>$owner->id,'type'=>$make->id,'brand'=>$model->id,'chassis_no'=>'WVWZZZ1JZXW000002','license_plate'=>'34 TEST 987','history_public'=>false,
        ]));
        $this->service($v);$this->assertTrue(app(PublicVehicleHistory::class)->isPublic($v));
        $this->search('WVWZZZ1JZXW000002')->assertSee('1 servis kaydı');
        $this->assertStringNotContainsString('name="history_public"',view('vehicle.history-fields',['historyVehicle'=>$v])->render());
    }
    public function test_marketing_directory_and_booking_header_link_to_public_chassis_lookup(): void {
        auth()->logout();
        foreach(['marketing.index','directory.index'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('Şasi sorgula')->assertSee(route('vehicle-history.index'),false);
        }
        $html=view('components.public-brand',['directoryHeader'=>false])->render();
        $this->assertStringContainsString(route('vehicle-history.index'),$html);
        $this->assertStringContainsString('Şasi sorgula',$html);
    }
    protected function setUp(): void
    {
        parent::setUp();config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array']);DB::purge('sqlite');
        $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        $admin=User::create(['name'=>'Admin','email'=>'admin@vin.test','password'=>'x','type'=>'super admin']);$this->actingAs($admin);
        \Illuminate\Support\Facades\Mail::fake();\Illuminate\Support\Facades\Http::preventStrayRequests();
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
        app(PublicVehicleHistory::class)->capture($service->id);
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
        $this->post(route('vehicle-history-admin.update',$c->id),['hidden'=>1,'reason'=>'Sentetik yönetici gizleme kararı','expected_revision'=>0])->assertRedirect();
        $r=$this->search('wvw zzz1jz-xw000001');$r->assertOk()->assertSee('ShopA')->assertSee('ShopB')->assertDontSee('PrivateShop')
            ->assertSee('2 servis kaydı')->assertSee('Yağ ve filtre değişimi')->assertSee('120.000 km')
            ->assertDontSee('PRIVATE-SERVICE-NOTE')->assertDontSee('PRIVATE-LINE-NOTE')->assertDontSee('98765')
            ->assertDontSee('12345')->assertDontSee('34 SECRET')->assertDontSee('WVWZZZ1JZXW000001')
            ->assertSee('%100 doğrulanmış değildir')->assertHeader('Cache-Control','no-store, private');
        $this->assertSame($a->parent_id,$a->fresh()->parent_id);$this->assertSame($b->parent_id,$b->fresh()->parent_id);
    }
    public function test_admin_moderation_survives_identity_changes_and_source_deletion(): void
    {
        $v=$this->vehicle('ShopA');$this->service($v);$history=app(PublicVehicleHistory::class);
        $this->search()->assertSee('1 servis kaydı');
        $this->post(route('vehicle-history-admin.update',$v->id),['hidden'=>1,'reason'=>'Hatalı kayıt incelemesi','expected_revision'=>0])->assertRedirect();
        $this->search()->assertSee('0 servis kaydı');
        $v->update(['chassis_no'=>'WVWZZZ1JZXW000002']);$this->assertFalse($history->isPublic($v));
        $this->post(route('vehicle-history-admin.update',$v->id),['hidden'=>0,'reason'=>'İnceleme tamamlandı','expected_revision'=>1])->assertRedirect();
        $this->search()->assertSee('1 servis kaydı');$this->search('WVWZZZ1JZXW000002')->assertSee('0 servis kaydı');
        $v->delete();$this->search()->assertSee('1 servis kaydı');
        $v->restore();$this->search()->assertSee('1 servis kaydı');
    }
    public function test_mileage_is_a_service_snapshot_and_legacy_unknown_mileage_is_not_invented(): void
    {
        $v=$this->vehicle('ShopA');$this->service($v);$v->update(['mileage'=>180000]);
        $this->search()->assertSee('120.000 km')->assertDontSee('180.000 km');
        $other=$this->vehicle('LegacyShop','WVWZZZ1JZXW000002');
        $legacy=DB::table('services')->insertGetId(['parent_id'=>$other->parent_id,'vehicle'=>$other->id,'status'=>'completed','service_date'=>'2026-10-10','mileage_at_service'=>null]);
        app(PublicVehicleHistory::class)->capture($legacy);
        $this->search('WVWZZZ1JZXW000002')->assertSee('Belirtilmedi')->assertDontSee('120.000 km');
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
    public function test_business_sharing_endpoints_are_forbidden_and_records_default_public(): void
    {
        $a=$this->vehicle('ShopA');$b=$this->vehicle('ShopB');$this->assertTrue(app(PublicVehicleHistory::class)->isPublic($a));
        $this->actingAs(User::find($a->parent_id))->post(route('vehicle.history-sharing',$b),['history_public'=>1])->assertForbidden();
        $this->post(route('vehicle.history-sharing',$a),['history_public'=>0])->assertForbidden();
        $this->get(route('vehicle-history-admin.index'))->assertForbidden();
        $this->post(route('vehicle-history-admin.update',$a->id),['hidden'=>1,'reason'=>'Forged request','expected_revision'=>0])->assertForbidden();
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
        $v->update(['chassis_no'=>'WVWZZZ1JZXW000002']);
        $stale->update(['mileage'=>130000]);
        $this->assertSame(hash('sha256','WVWZZZ1JZXW000002'),DB::table('vehicle_history_index')->where('vehicle_id',$v->id)->value('vin_hash'));
        $this->assertTrue(app(PublicVehicleHistory::class)->isPublic($v->fresh()));
    }
    public function test_audit_moving_a_vehicle_does_not_move_published_history_between_owners(): void
    {
        $v=$this->vehicle('ShopA');$b=$this->vehicle('ShopB','WVWZZZ1JZXW000002');
        $service=$this->service($v);$oldOwner=$v->parent_id;$v->update(['parent_id'=>$b->parent_id]);
        $this->assertSame($oldOwner,DB::table('public_service_history')->where('service_id',$service->id)->value('parent_id'));
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
        $v=$this->vehicle('ShopA');
        for($i=0;$i<25;$i++)$this->service($v);
        $this->search()->assertSee('25 servis kaydı')->assertSee('Sonraki');
        $secondPage=$this->get(route('vehicle-history.index',['page'=>2]))->assertOk()->assertSee('25 servis kaydı');
        $this->assertSame(5,substr_count($secondPage->getContent(),'class="history-entry"'));
        $this->get(route('vehicle-history.index'))->assertOk();
        $rows=app(PublicVehicleHistory::class)->results(hash('sha256',$v->chassis_no));
        $this->assertSame(20,$rows->count());$this->assertSame(25,$rows->total());
        $this->travel(16)->minutes();
        $this->get(route('vehicle-history.index'))->assertOk()->assertSee('Yalnızca tamamlanmış işlem özetleri.')->assertDontSee('ShopA');
    }

}
