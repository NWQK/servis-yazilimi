<?php
namespace Tests\Feature;
use App\Models\{User,Tax,Unit,VehicleType,VehicleBrand,ServiceType,ItemCategory,Vehicle,Service,Invoice,Client};
use Illuminate\Support\Facades\{DB,Gate,Storage,Mail,Http};
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
class BusinessSetupTest extends TestCase {
 private $owner;
 protected function setUp(): void {
  parent::setUp();config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array']);DB::purge('sqlite');
  $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
  $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
  // The legacy 1.7 migration contains MySQL-only ALTER statements and is excluded above.
  \Illuminate\Support\Facades\Schema::table('service_types',fn($t)=>$t->string('tax')->nullable());
  $this->owner=User::create(['name'=>'Setup Shop','email'=>'setup@example.test','password'=>'x','type'=>'owner']);
  Gate::before(fn($user)=>$user->type==='owner' && $user->email!=='limited@example.test' ? true : null);
  $this->actingAs($this->owner);$this->withoutMiddleware(\App\Http\Middleware\XSS::class);Mail::fake();Http::fake();
  DB::table('settings')->insert(['parent_id'=>$this->owner->id,'name'=>'pricing_feature','value'=>'off']);
 }
 private function url($step) {return route('business-setup.store',['step'=>$step]);}
 public function test_all_steps_render_with_saved_progress_and_menu_link_at_the_end() {
  foreach(array_keys(\App\Http\Controllers\BusinessSetupController::STEPS) as $step) $this->get(route('business-setup.index',['step'=>$step]))->assertOk()->assertSee('İşletme kurulumu')->assertSee('business-setup.css');
  $this->get(route('business-setup.index',['step'=>'bad']))->assertNotFound();
  $this->get(route('business-setup.index',['step'=>'ready']))->assertViewHas('complete',fn($c)=>!$c['business'] && !$c['vehicles'] && !$c['team']);
 }
 public function test_business_form_keeps_existing_invoice_flow_and_advances_only_after_validation() {
  $this->from(route('business-setup.index'))->post($this->url('business'),['action'=>'business','continue'=>1,'billing'=>['email'=>'bad']])->assertSessionHasErrors('billing.email')->assertRedirect(route('business-setup.index'));
  $this->post($this->url('business'),['action'=>'business','continue'=>1,'billing'=>['name'=>'Test Shop','address'=>'Test address','phone'=>'2121234567','company_admin'=>1]])->assertSessionHasNoErrors()->assertRedirect(route('business-setup.index',['step'=>'logos']));
  $details=\App\Services\InvoiceBilling::business($this->owner->id);$this->assertSame('Test Shop',$details['name']);$this->assertArrayNotHasKey('company_admin',$details);
  $this->get(route('business-setup.index',['step'=>'business']))->assertSee('Test Shop')->assertViewHas('complete',fn($c)=>$c['business']);
 }
 public function test_logos_use_existing_upload_rules_and_do_not_change_shared_environment() {
  Storage::fake(config('filesystems.default'));$before=file_get_contents(app()->environmentFilePath());
  $temp=tempnam(sys_get_temp_dir(),'setup-logo-');file_put_contents($temp,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC'));
  $logo=new UploadedFile($temp,'invoice.png','image/png',null,true);
  $this->post($this->url('logos'),['action'=>'logos','continue'=>1,'invoice_logo'=>$logo])->assertRedirect(route('business-setup.index',['step'=>'vehicles']));
  $filename=DB::table('settings')->where('parent_id',$this->owner->id)->where('name','invoice_logo')->value('value');
  Storage::disk(config('filesystems.default'))->assertExists('upload/logo/'.$filename);
  $this->get(route('business-setup.index',['step'=>'logos']))->assertSee($filename)->assertViewHas('complete',fn($c)=>$c['logos']);
  $this->post($this->url('logos'),['action'=>'logos'])->assertSessionHasNoErrors();$this->assertSame($filename,DB::table('settings')->where('parent_id',$this->owner->id)->where('name','invoice_logo')->value('value'));
  $this->assertSame($before,file_get_contents(app()->environmentFilePath()));
 }
 public function test_vehicle_import_confirmation_repeat_safety_and_manual_model_tenant_scope() {
  $this->post($this->url('vehicles'),['action'=>'catalog','category'=>'motosiklet'])->assertSessionHasErrors('confirm');$this->assertSame(0,VehicleType::count());
  $this->post($this->url('vehicles'),['action'=>'catalog','category'=>'motosiklet','confirm'=>1])->assertSessionHasNoErrors();$count=VehicleBrand::count();$this->assertGreaterThan(0,$count);
  $this->post($this->url('vehicles'),['action'=>'catalog','category'=>'motosiklet','confirm'=>1])->assertSessionHasNoErrors();$this->assertSame($count,VehicleBrand::count());
  $this->post($this->url('vehicles'),['action'=>'brand','type'=>'Test Brand','parent_id'=>999])->assertSessionHasNoErrors();$brand=VehicleType::where('type','Test Brand')->firstOrFail();$this->assertSame($this->owner->id,$brand->parent_id);
  $other=VehicleType::create(['type'=>'Other','parent_id'=>999]);
  $this->post($this->url('vehicles'),['action'=>'model','type'=>$other->id,'name'=>'Wrong owner'])->assertSessionHasErrors('type');
  $this->post($this->url('vehicles'),['action'=>'model','type'=>$brand->id,'name'=>'Test model'])->assertSessionHasNoErrors();$this->assertDatabaseHas('vehicle_brands',['parent_id'=>$this->owner->id,'type'=>$brand->id,'name'=>'Test model']);
  $this->get(route('business-setup.index',['step'=>'vehicles']))->assertViewHas('complete',fn($c)=>$c['vehicles']);
 }
 public function test_inventory_defaults_and_custom_services_are_scoped_idempotent_and_do_not_make_sales() {
  $this->post($this->url('taxes'),['action'=>'defaults','confirm'=>1])->assertSessionHasNoErrors();$tc=Tax::count();$uc=Unit::count();
  $this->post($this->url('units'),['action'=>'defaults','confirm'=>1])->assertSessionHasNoErrors();$this->assertSame($tc,Tax::count());$this->assertSame($uc,Unit::count());
  foreach([['taxes','tax',['title'=>'Test tax','rate'=>5]],['units','unit',['unit'=>'Test unit']],['categories','category',['name'=>'Yağlar']]] as [$step,$action,$fields]) {for($i=0;$i<2;$i++) $this->post($this->url($step),['action'=>$action]+$fields)->assertSessionHasNoErrors();}
  $this->assertSame(1,ItemCategory::where('name','Yağlar')->count());$this->assertSame(1,Unit::where('unit','Test unit')->count());
  $tax=Tax::where('parent_id',$this->owner->id)->firstOrFail();
  $this->post($this->url('services'),['action'=>'service','type'=>'Yağ değişimi','rate'=>250,'tax'=>[$tax->id]])->assertRedirect(route('business-setup.index',['step'=>'services']))->assertSessionHasNoErrors();
  $service=ServiceType::firstOrFail();$this->assertSame((string)$tax->id,$service->tax);$this->assertEquals(250,$service->rate);
  DB::table('taxes')->insert(['title'=>'Other tax','rate'=>20,'parent_id'=>999]);$foreign=Tax::where('parent_id',999)->firstOrFail();
  $this->post($this->url('services'),['action'=>'service','type'=>'Bad','rate'=>250,'tax'=>[$foreign->id]])->assertSessionHasErrors('tax.0');
  $this->post($this->url('taxes'),['action'=>'tax','title'=>'Bad','rate'=>101])->assertSessionHasErrors('rate');
  foreach([Client::class,Vehicle::class,Service::class,Invoice::class] as $model) $this->assertSame(0,$model::count());Mail::assertNothingSent();Http::assertNothingSent();
 }
 public function test_only_owners_can_access_and_each_action_requires_its_permission() {
  foreach(['super admin','client','employee'] as $type) {$user=User::create(['name'=>'Denied','email'=>str_replace(' ','',$type).'@example.test','password'=>'x','type'=>$type]);$this->actingAs($user)->get(route('business-setup.index'))->assertForbidden();$this->post($this->url('taxes'),['action'=>'defaults','confirm'=>1])->assertForbidden();}
  $limited=User::create(['name'=>'Limited','email'=>'limited@example.test','password'=>'x','type'=>'owner']);
  $this->actingAs($limited)->post($this->url('units'),['action'=>'unit','unit'=>'Bad'])->assertForbidden();
  $this->actingAs($this->owner)->post($this->url('units'),['action'=>'employee'])->assertSessionHasErrors('action');
 }
 public function test_employee_form_returns_to_setup_and_success_marks_team_ready() {
  \Spatie\Permission\Models\Role::create(['name'=>'employee','guard_name'=>'web','parent_id'=>$this->owner->id]);
  $this->post($this->url('team'),['action'=>'employee','name'=>'Test Employee','email'=>'employee-setup@example.test','password'=>'testpassword','phone_number'=>'5551234567','gender'=>'Male','age'=>25,'joining_date'=>today()->toDateString(),'address'=>'Test address'])->assertSessionHasNoErrors()->assertRedirect(route('business-setup.index',['step'=>'team']));
  $employee=User::where('email','employee-setup@example.test')->firstOrFail();$this->assertSame($this->owner->id,$employee->parent_id);$this->assertTrue($employee->hasRole('employee'));
  $this->get(route('business-setup.index',['step'=>'ready']))->assertViewHas('complete',fn($c)=>$c['team']);
 }
}
