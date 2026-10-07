<?php
namespace Tests\Feature;

use App\Models\{User, VehicleType, VehicleBrand, Vehicle, VehicleCatalogImport};
use App\Services\VehicleCatalog;
use Illuminate\Support\Facades\{DB, Gate};
use Tests\TestCase;

class VehicleCatalogTest extends TestCase
{
    private $owner;
    private $catalog;
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array']);
        DB::purge('sqlite');
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        $this->owner=User::create(['name'=>'Servis','email'=>'catalog@example.test','password'=>'x','type'=>'owner']);
        $this->actingAs($this->owner)->withoutMiddleware(\App\Http\Middleware\XSS::class);
        Gate::before(fn($user)=>$user->type==='owner' ? true : false);
        $this->catalog=app(VehicleCatalog::class);
    }

    public function test_all_catalogues_have_valid_unique_names_and_import_expected_counts()
    {
        foreach (VehicleCatalog::CATEGORIES as $category=>$label) {
            $other=User::create(['name'=>'Test','email'=>$category.'@example.test','password'=>'x','type'=>'owner']);
            $data=$this->catalog->dataset($category);
            foreach($data as $brand=>$models) {
                $this->assertNotEmpty(trim($brand));
                $this->assertSame(count($models),count(array_unique($models)));
            }
            $result=$this->catalog->import($other->id,$category);
            $this->assertSame(count($data),$result['brands']);
            $this->assertSame(array_sum(array_map('count',$data)),$result['models']);
            $this->assertEquals(count($data),VehicleType::where('parent_id',$other->id)->count());
        }
        $this->assertSame(0,VehicleType::where('parent_id',$this->owner->id)->count());
    }

    public function test_setup_warnings_are_scoped_and_disappear_when_completed()
    {
        $warnings=app(\App\Services\OwnerSetupWarnings::class);
        $this->assertEqualsCanonicalizing(['business','invoice_logo','catalog','appointments','products','services'],array_column($warnings->forUser($this->owner),'key'));
        $this->get(route('vehicle-type.index'))->assertOk()->assertSee('id="owner-setup-warnings-toggle"',false)->assertSee('Fatura logonuzu yükleyin');
        $other=User::create(['name'=>'Other','email'=>'setup-other@example.test','password'=>'x','type'=>'owner']);
        DB::table('invoice_business_profiles')->insert(['parent_id'=>$other->id,'details'=>json_encode(['name'=>'Other','address'=>'Adres','phone'=>'5551234567'])]);
        $this->catalog->import($other->id,'otomobil');
        DB::table('settings')->insert(['parent_id'=>$other->id,'name'=>'invoice_logo','value'=>'other.png']);
        $this->assertCount(6,$warnings->forUser($this->owner));
        DB::table('invoice_business_profiles')->insert(['parent_id'=>$this->owner->id,'details'=>json_encode(['name'=>'Servis','address'=>'Adres','phone'=>'5551234567'])]);
        DB::table('settings')->insert(['parent_id'=>$this->owner->id,'name'=>'invoice_logo','value'=>'invoice.png']);
        $this->catalog->import($this->owner->id,'motosiklet');
        $profile=app(\App\Services\AppointmentBooking::class)->profileForOwner($this->owner->id);
        $profile->update(['display_name'=>'Servis','is_active'=>true,'weekly_hours'=>[1=>[9,10]]]);
        \App\Models\Item::create(['parent_id'=>$this->owner->id,'title'=>'Yağ','item_code'=>'Y1','quantity'=>1,'units'=>1,'purchase_price'=>100,'sales_price'=>120,'purchase_date'=>'2026-10-07']);
        \App\Models\Service::create(['parent_id'=>$this->owner->id,'service_id'=>1,'client'=>1,'vehicle'=>1,'assign'=>1,'status'=>'scheduled']);
        $this->assertSame([],$warnings->forUser($this->owner));
        $profile->update(['weekly_hours'=>[]]);
        $this->assertSame(['appointments'],array_column($warnings->forUser($this->owner),'key'));
        $profile->update(['is_active'=>false,'weekly_hours'=>[1=>[9]]]);
        $this->assertSame(['appointments'],array_column($warnings->forUser($this->owner),'key'));
        $this->owner->type='super admin';
        $this->assertSame([],$warnings->forUser($this->owner));
    }

    public function test_warning_links_open_business_settings_and_preselect_catalogue_without_importing()
    {
        $this->get(route('setting.index',['tab'=>'invoice_business']))->assertOk()->assertSee('tab-pane active show" id="invoice_business"',false);
        $this->get(route('setting.index',['tab'=>'user_profile_settings']))->assertOk()->assertSee('id="business-invoice-logo"',false);
        foreach (array_keys(VehicleCatalog::CATEGORIES) as $category) {
            $response=$this->get(route('vehicle-catalog.create',['category'=>$category]))->assertOk();
            $this->assertMatchesRegularExpression('/value="'.preg_quote($category,'/').'"[^>]*checked/',$response->getContent());
        }
        $this->assertSame(0,VehicleType::where('parent_id',$this->owner->id)->count());
        $this->assertSame(0,VehicleCatalogImport::where('parent_id',$this->owner->id)->count());
        $this->get(route('vehicle-catalog.create',['category'=>'invalid']))->assertOk();
    }

    public function test_guides_show_real_steps_and_ready_defaults_are_confirmed_scoped_and_idempotent()
    {
        $this->get(route('inventory.setup'))->assertOk()->assertSee('kategori oluşturmak zorunlu değildir');
        $this->get(route('service.setup'))->assertOk()->assertSee('Servis kaydedildiğinde')->assertSee('isteğe bağlıdır');
        $this->post(route('inventory.setup.defaults'),[])->assertSessionHasErrors('confirm');
        $this->post(route('inventory.setup.defaults'),['confirm'=>1,'owner_id'=>999])->assertRedirect(route('inventory.setup'));
        $taxCount=\App\Models\Tax::where('parent_id',$this->owner->id)->count();
        $unitCount=\App\Models\Unit::where('parent_id',$this->owner->id)->count();
        $this->assertSame(3,$taxCount);$this->assertSame(12,$unitCount);
        $this->post(route('inventory.setup.defaults'),['confirm'=>1])->assertRedirect();
        $this->assertSame($taxCount,\App\Models\Tax::count());$this->assertSame($unitCount,\App\Models\Unit::count());
        $client=User::create(['name'=>'Client','email'=>'guides-client@example.test','password'=>'x','type'=>'client']);
        $this->actingAs($client)->get(route('inventory.setup'))->assertForbidden();
        $this->get(route('service.setup'))->assertForbidden();
        $this->post(route('inventory.setup.defaults'),['confirm'=>1])->assertForbidden();
    }

    public function test_education_guide_renders_all_topics_and_only_links_available_to_the_account()
    {
        $topics=config('education.topics');
        $this->assertSame(count($topics),count(array_unique(array_column($topics,'id'))));
        $response=$this->get(route('education.index'))->assertOk()->assertSee('İlk kayıttan')->assertSee('id="learn-search"',false)->assertSee('sanayirandevu-logo.svg');
        foreach($topics as $topic) {
            $this->assertArrayHasKey($topic['group'],config('education.groups'));
            $this->assertNotEmpty($topic['steps']);$this->assertNotEmpty($topic['notes']);
            $response->assertSee($topic['title'])->assertSee('id="'.$topic['id'].'"',false);
        }
        $this->get(route('vehicle-type.index'))->assertSee('aria-label="Eğitim rehberi"',false);
        $admin=User::create(['name'=>'Admin','email'=>'education-admin@example.test','password'=>'x','type'=>'super admin']);
        $this->actingAs($admin)->get(route('education.index'))->assertOk()->assertDontSee('href="'.route('appointments.settings').'"',false);
        foreach(['client','employee'] as $type) {
            $user=User::create(['name'=>$type,'email'=>$type.'-education@example.test','password'=>'x','type'=>$type]);
            $this->actingAs($user)->get(route('education.index'))->assertForbidden();
        }
        auth()->logout();$this->get(route('education.index'))->assertRedirect(route('login'));
    }

    public function test_import_reuses_manual_records_is_idempotent_and_undo_preserves_manual_records()
    {
        $ford=VehicleType::create(['parent_id'=>$this->owner->id,'type'=>' ford ']);
        $focus=VehicleBrand::create(['parent_id'=>$this->owner->id,'type'=>$ford->id,'name'=>'FOCUS']);
        $custom=VehicleBrand::create(['parent_id'=>$this->owner->id,'type'=>$ford->id,'name'=>'Özel model']);
        $skoda=VehicleType::create(['parent_id'=>$this->owner->id,'type'=>'Skoda']);
        $this->post(route('vehicle-catalog.store'),['category'=>'otomobil'])->assertSessionHasErrors('confirm');
        $this->post(route('vehicle-catalog.store'),['category'=>'hatalı','confirm'=>1])->assertSessionHasErrors('category');
        $this->post(route('vehicle-catalog.store'),['category'=>'otomobil','confirm'=>1])->assertSessionHasNoErrors();
        $count=VehicleBrand::count();
        $this->post(route('vehicle-catalog.store'),['category'=>'otomobil','confirm'=>1])->assertSessionHasNoErrors();
        $this->assertSame($count,VehicleBrand::count()); $this->assertSame(1,VehicleCatalogImport::count());
        $this->assertSame(1,VehicleBrand::where('type',$ford->id)->where('name','FOCUS')->count());
        $batch=VehicleCatalogImport::first();
        $this->get(route('vehicle-type.index'))->assertOk()->assertSee('Geri al otomatik yüklemeyi');
        $this->get(route('vehicle-catalog.create'))->assertOk()->assertSee('Ağır vasıta')->assertSee('Motosiklet')->assertSee('Evet, yükle');
        $this->delete(route('vehicle-catalog.destroy',$batch->id))->assertSessionHasErrors('confirm');
        $this->delete(route('vehicle-catalog.destroy',$batch->id),['confirm'=>1])->assertSessionHasNoErrors();
        $this->assertSame(2,VehicleType::count()); $this->assertSame(2,VehicleBrand::count());
        $this->assertNotNull($skoda->fresh());
        $this->assertNotNull($focus->fresh()); $this->assertNotNull($custom->fresh());
        $this->assertFalse($batch->fresh()->active);
        $this->delete(route('vehicle-catalog.destroy',$batch->id),['confirm'=>1])->assertSessionHasNoErrors();
        $this->catalog->import($this->owner->id,'otomobil');
        $this->assertSame(1,VehicleCatalogImport::where('active',true)->count());
    }

    public function test_undo_keeps_used_models_modified_records_and_manual_models_on_imported_brand()
    {
        $this->catalog->import($this->owner->id,'otomobil'); $batch=VehicleCatalogImport::first();
        $ford=VehicleType::where('type','Ford')->first();
        $focus=VehicleBrand::where('type',$ford->id)->where('name','Focus')->first();
        Vehicle::create(['parent_id'=>$this->owner->id,'type'=>$ford->id,'brand'=>$focus->id]);
        $renamed=VehicleBrand::where('type',$ford->id)->where('name','Fiesta')->first(); $renamed->update(['name'=>'Fiesta özel']);
        $custom=VehicleBrand::create(['parent_id'=>$this->owner->id,'type'=>$ford->id,'name'=>'Elle eklenen']);
        $toyota=VehicleType::where('type','Toyota')->first(); $toyota->update(['type'=>'Toyota özel']);
        $result=$this->catalog->undo($this->owner->id,$batch->id);
        $this->assertGreaterThan(0,$result['retained']);
        $this->assertNotNull($ford->fresh()); $this->assertNotNull($focus->fresh());
        $this->assertNotNull($renamed->fresh()); $this->assertNotNull($custom->fresh()); $this->assertNotNull($toyota->fresh());
        $this->assertSame($focus->id,(int)Vehicle::first()->brand);
        $this->assertSame(3,VehicleBrand::count());
    }

    public function test_shared_brand_is_retained_until_last_category_is_undone()
    {
        $this->catalog->import($this->owner->id,'otomobil');
        $car=VehicleCatalogImport::first();
        $bmw=VehicleType::where('type','BMW')->first();
        $this->catalog->import($this->owner->id,'motosiklet');
        $bike=VehicleCatalogImport::where('category','motosiklet')->first();
        $this->assertSame(1,VehicleType::where('type','BMW')->count());
        $this->catalog->undo($this->owner->id,$car->id);
        $this->assertNotNull($bmw->fresh());
        $this->assertGreaterThan(0,VehicleBrand::where('type',$bmw->id)->count());
        $this->catalog->undo($this->owner->id,$bike->id);
        $this->assertSame(0,VehicleBrand::count()); $this->assertSame(0,VehicleType::count());
    }

    public function test_imports_are_scoped_to_shop_and_require_permissions()
    {
        $other=User::create(['name'=>'Other','email'=>'other-catalog@example.test','password'=>'x','type'=>'owner']);
        $this->catalog->import($other->id,'otomobil'); $batch=VehicleCatalogImport::first();
        $this->delete(route('vehicle-catalog.destroy',$batch->id),['confirm'=>1])->assertNotFound();
        $this->assertTrue($batch->fresh()->active);
        $this->catalog->import($this->owner->id,'otomobil');
        $this->assertSame(2,VehicleType::where('type','Ford')->count());
        $employee=User::create(['name'=>'Employee','email'=>'employee-catalog@example.test','password'=>'x','type'=>'employee','parent_id'=>$this->owner->id]);
        $this->actingAs($employee)->post(route('vehicle-catalog.store'),['category'=>'motosiklet','confirm'=>1])->assertForbidden();
        $this->actingAs($employee)->delete(route('vehicle-catalog.destroy',$batch->id),['confirm'=>1])->assertForbidden();
    }
}
