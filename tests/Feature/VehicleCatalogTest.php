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
