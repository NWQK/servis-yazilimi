<?php
namespace Tests\Feature;
use App\Models\{User,AppointmentProfile,Appointment,BusinessLocation,BookingService,BusinessReview};
use App\Services\GoogleMapsLocation;
use Illuminate\Support\Facades\{DB,Gate,Http};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
class BookingLocationTest extends TestCase {
 protected function setUp(): void {
  parent::setUp();config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array']);DB::purge('sqlite');
  $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
  $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
  Gate::before(fn($u)=>$u->type==='owner' ? true : null);$this->withoutMiddleware(\App\Http\Middleware\XSS::class);
 }
 private function shop(string $name, ?float $lat=null, ?int $rating=null): AppointmentProfile {
  $owner=User::create(['name'=>$name,'email'=>Str::random(8).'@example.test','password'=>'x','type'=>'owner']);
  $profile=new AppointmentProfile;$profile->forceFill(['owner_id'=>$owner->id,'public_id'=>(string)Str::uuid(),'display_name'=>$name,'weekly_hours'=>[],'is_active'=>true,'directory_visible'=>true,'directory_region'=>'54'])->save();
  $service=BookingService::firstOrCreate(['name'=>'Test service'],['vehicle_types'=>['otomobil'],'is_active'=>true]);
  DB::table('booking_offerings')->insert(['appointment_profile_id'=>$profile->id,'vehicle_type'=>'otomobil','booking_service_id'=>$service->id]);
  if($lat!==null) BusinessLocation::create(['owner_id'=>$owner->id,'google_maps_url'=>'https://www.google.com/maps?q='.$lat.',30','latitude'=>$lat,'longitude'=>30]);
  if($rating!==null) BusinessReview::create(['owner_id'=>$owner->id,'customer_id'=>$owner->id,'identity_hash'=>hash('sha256',$owner->id),'token'=>Str::random(64),'rating'=>$rating,'rated_at'=>now()]);
  return $profile;
 }
 private function appointment($profile,$status): Appointment {
  return Appointment::create(['appointment_profile_id'=>$profile->id,'public_token'=>bin2hex(random_bytes(32)),'request_key'=>(string)Str::uuid(),'customer_name'=>'Test','phone'=>'+905551234567','starts_at'=>now()->addDay(),'ends_at'=>now()->addDay()->addHour(),'status'=>$status,'pending_expires_at'=>now()->addHours(2)]);
 }
 private function directory(array $extra=[]): string {return route('directory.index',array_merge(['region'=>'54','vehicle'=>'otomobil','service'=>BookingService::where('name','Test service')->firstOrFail()->id],$extra));}
 public function test_maps_pin_coordinates_redirect_and_manual_fallback() {
  Http::fake(['https://maps.app.goo.gl/abc'=>Http::response('',302,['Location'=>'https://www.google.com/maps/place/Test/@39,29,12z/data=!3d40.75!4d30.25'])]);
  $point=app(GoogleMapsLocation::class)->resolve('https://maps.app.goo.gl/abc');$this->assertSame(40.75,$point['latitude']);$this->assertSame(30.25,$point['longitude']);Http::assertSentCount(1);
  $this->assertSame(41.0,app(GoogleMapsLocation::class)->resolve('https://www.google.com/maps?q=41,29')['latitude']);
  $this->assertSame(42.0,app(GoogleMapsLocation::class)->resolve('https://maps.app.goo.gl/abc',42,30)['latitude']);Http::assertSentCount(1);
 }
 public function test_maps_rejects_unsafe_hosts_redirects_and_camera_centre() {
  Http::fake(['*'=>Http::response('',302,['Location'=>'https://127.0.0.1/private'])]);
  foreach(['https://evil.test/maps?q=41,29','http://www.google.com/maps?q=41,29','https://www.google.com.evil.test/maps?q=41,29','https://www.google.com@evil.test/maps','https://www.google.com/maps/dir/?api=1','https://maps.app.goo.gl/abc','https://www.google.com/maps/@41,29,12z'] as $url) {
   try {app(GoogleMapsLocation::class)->resolve($url);$this->fail('Unsafe/ambiguous map accepted');}catch(ValidationException $e){$this->assertArrayHasKey('google_maps_url',$e->errors());}
  }
  Http::assertNotSent(fn($r)=>str_contains($r->url(),'127.0.0.1') || str_contains($r->url(),'evil.test'));
 }
 public function test_business_location_is_tenant_scoped_reused_and_removable() {
  $a=$this->shop('Shop A',40);$b=$this->shop('Shop B',41);Http::fake();
  $url='https://www.google.com/maps?q=40.5,30.5';
  $this->actingAs($a->owner)->post(route('setting.invoice-business'),['google_maps_url'=>$url])->assertSessionHasNoErrors();
  $this->assertSame(40.5,$a->businessLocation->latitude);$this->assertSame(41.0,$b->businessLocation->latitude);
  $this->post(route('setting.invoice-business'),['google_maps_url'=>$url])->assertSessionHasNoErrors();Http::assertNothingSent();
  $this->post(route('setting.invoice-business'),['google_maps_url'=>'https://evil.test/maps?q=0,0'])->assertSessionHasErrors('google_maps_url');$this->assertSame($url,$a->businessLocation->fresh()->google_maps_url);
  $this->post(route('setting.invoice-business'),['google_maps_url'=>''])->assertSessionHasNoErrors();$this->assertDatabaseMissing('business_locations',['owner_id'=>$a->owner_id]);$this->assertDatabaseHas('business_locations',['owner_id'=>$b->owner_id]);
 }
 public function test_cookie_is_encrypted_short_lived_and_validated() {
  $response=$this->postJson(route('directory.location'),['latitude'=>40.12345678,'longitude'=>30.12345678,'accuracy'=>50])->assertOk()->assertJson(['saved'=>true]);
  $cookie=collect($response->headers->getCookies())->first(fn($c)=>$c->getName()==='booking_location');
  $this->assertTrue($cookie->isHttpOnly());$this->assertSame('lax',$cookie->getSameSite());$this->assertStringNotContainsString('40.123',$cookie->getValue());$this->assertLessThanOrEqual(1800,$cookie->getExpiresTime()-time());$this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
  $this->postJson(route('directory.location'),['latitude'=>91,'longitude'=>30,'accuracy'=>5])->assertUnprocessable();
  $this->postJson(route('directory.location'),['latitude'=>40,'longitude'=>30,'accuracy'=>5001])->assertUnprocessable();
 }
 public function test_default_rating_sort_and_appointment_sort_excludes_unconfirmed_requests() {
  $a=$this->shop('Low rating',40,2);$b=$this->shop('High rating',41,5);$this->shop('Unrated');
  $this->appointment($a,'approved');$this->appointment($a,'completed');
  foreach(['pending','cancelled','rejected','expired','no_show'] as $status) $this->appointment($b,$status);
  $this->get($this->directory())->assertOk()->assertSeeInOrder(['Low rating'],false)->assertViewHas('shops',fn($shops)=>$shops->first()->id===$b->id);
  $this->get($this->directory(['sort'=>'appointments']))->assertOk()->assertViewHas('shops',fn($shops)=>$shops->first()->id===$a->id && $shops->first()->appointment_count===2);
  $this->get($this->directory(['sort'=>'distance']))->assertOk()->assertSee('Şimdilik işletmeler puana göre sıralanıyor.')->assertSee('Mesafe bilinmiyor');
 }
 public function test_distance_sort_is_before_pagination_and_unknown_locations_are_last() {
  for($i=0;$i<12;$i++) $this->shop('Far '.$i,42+$i/100,5);
  $near=$this->shop('Nearest',40,1);$this->shop('Unknown',null,5);
  $cookie=json_encode(['lat'=>40,'lng'=>30,'expires'=>time()+1800]);
  $this->withCookie('booking_location',$cookie)->get($this->directory(['sort'=>'distance']))->assertOk()->assertViewHas('shops',fn($shops)=>$shops->first()->id===$near->id && $shops->total()===14 && $shops->first()->distance_km<0.01);
  $this->get($this->directory(['sort'=>'distance','page'=>2]))->assertOk()->assertViewHas('shops',fn($shops)=>$shops->last()->display_name==='Unknown');
  $this->withCookie('booking_location',json_encode(['lat'=>40,'lng'=>30,'expires'=>time()-1]))->get($this->directory(['sort'=>'distance']))->assertOk()->assertSee('Şimdilik işletmeler puana göre sıralanıyor.');
 }
 public function test_directions_button_is_only_on_pending_and_approved_with_saved_location() {
  $profile=$this->shop('Directions Shop',40.75);
  foreach(['pending','approved','completed','cancelled','rejected'] as $status) {
   $appointment=$this->appointment($profile,$status);$response=$this->get($appointment->statusUrl())->assertOk();
   if(in_array($status,['pending','approved'])) $response->assertSee('Yol tarifi al')->assertSee('destination=40.75%2C30',false);
   else $response->assertDontSee('Yol tarifi al');
  }
  $profile->businessLocation->delete();$this->get($this->appointment($profile,'approved')->statusUrl())->assertOk()->assertDontSee('Yol tarifi al');
 }
}
