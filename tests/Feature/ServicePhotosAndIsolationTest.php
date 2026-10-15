<?php
namespace Tests\Feature;

use App\Models\{User,Vehicle,Service,ServicePhoto,VehicleQrCode,Invoice,Item};
use App\Services\Recovery\{RecoveryStore,RecoveryFiles};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Gate,Storage,Mail};
use Illuminate\Support\Str;
use Tests\TestCase;

class ServicePhotosAndIsolationTest extends TestCase
{
    private User $owner;
    private User $other;
    private Service $service;
    private Vehicle $vehicle;
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','session.driver'=>'array','cache.default'=>'array']);
        DB::purge('sqlite');
        $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        User::create(['name'=>'Admin','email'=>'admin@photo.test','type'=>'super admin','password'=>'x']);
        $this->owner=User::create(['name'=>'Shop A','email'=>'a@photo.test','type'=>'owner','parent_id'=>1,'password'=>'x']);
        $this->other=User::create(['name'=>'Shop B','email'=>'b@photo.test','type'=>'owner','parent_id'=>1,'password'=>'x']);
        $this->vehicle=Vehicle::create(['parent_id'=>$this->owner->id,'license_plate'=>'34 PHOTO']);
        $this->service=Service::create(['parent_id'=>$this->owner->id,'vehicle'=>$this->vehicle->id,'assign'=>$this->owner->id,'status'=>'scheduled','service_id'=>1]);
        Gate::before(fn()=>true); Mail::fake();
        $this->actingAs($this->owner)->withoutMiddleware(\App\Http\Middleware\XSS::class);
        Storage::fake('service_photos'); Storage::fake('local');
        $root=storage_path('framework/testing/photo-retention-'.Str::uuid());
        config(['backups.path'=>$root.'/backups','recovery.write_lock_path'=>$root.'/locks']);
    }
    private function upload(): ServicePhoto
    {
        $this->post(route('service.photos.store',$this->service),['photos'=>[UploadedFile::fake()->image('acceptance.jpg',2000,1000)]])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        return ServicePhoto::latest('id')->firstOrFail();
    }
    private function code(Vehicle $vehicle): VehicleQrCode
    {
        return VehicleQrCode::create(['parent_id'=>$vehicle->parent_id,'vehicle_id'=>$vehicle->id,'token'=>bin2hex(random_bytes(32)),'assigned_at'=>now()]);
    }
    public function test_photo_is_resized_private_and_visible_only_through_matching_vehicle_link(): void
    {
        $photo=$this->upload();
        $disk=Storage::disk('service_photos');
        $size=getimagesize($disk->path('photo/'.$photo->filename));
        $this->assertSame([1600,800],array_slice($size,0,2));
        $this->assertSame('image/jpeg',$size['mime']);
        $this->assertSame($this->owner->id,$photo->parent_id);
        $this->assertSame($this->owner->id,$photo->uploaded_by);
        $this->assertLessThan(1024*1024,$photo->bytes);
        $code=$this->code($this->vehicle);
        $this->get(route('service.show',encrypt($this->service->id)))->assertOk()->assertSee('Araç kabul fotoğrafları');
        auth()->logout();
        $this->get(route('vehicle-portal.show',$code->token))->assertOk()->assertSee(route('vehicle-portal.photo',[$code->token,$photo->id]));
        $this->get(route('vehicle-portal.photo',[$code->token,$photo->id]))->assertOk()
            ->assertHeader('Content-Type','image/jpeg')->assertHeader('X-Content-Type-Options','nosniff')
            ->assertHeader('Cache-Control','no-store, private');
        $unassigned=VehicleQrCode::create(['parent_id'=>$this->owner->id,'token'=>bin2hex(random_bytes(32))]);
        $this->get(route('vehicle-portal.photo',[$unassigned->token,$photo->id]))->assertNotFound();
        $second=Vehicle::create(['parent_id'=>$this->owner->id]); $wrong=$this->code($second);
        $this->get(route('vehicle-portal.photo',[$wrong->token,$photo->id]))->assertNotFound();
        $foreign=Vehicle::create(['parent_id'=>$this->other->id]); $wrong=$this->code($foreign);
        $this->get(route('vehicle-portal.photo',[$wrong->token,$photo->id]))->assertNotFound();
        $this->vehicle->delete();
        $this->get(route('vehicle-portal.photo',[$code->token,$photo->id]))->assertNotFound();
    }
    public function test_other_shop_and_client_cannot_read_upload_or_delete_photos(): void
    {
        $photo=$this->upload();
        foreach([$this->other,User::create(['name'=>'Client','email'=>null,'parent_id'=>$this->owner->id,'type'=>'client','password'=>'x'])] as $user){
            $this->actingAs($user);
            $this->get(route('service.photos.show',[$this->service->id,$photo->id]))->assertForbidden();
            $this->delete(route('service.photos.destroy',[$this->service->id,$photo->id]))->assertForbidden();
            $this->post(route('service.photos.store',$this->service),['photos'=>[UploadedFile::fake()->image('new.jpg')]])->assertForbidden();
        }
        $this->assertSame(1,ServicePhoto::count());
        Storage::disk('service_photos')->assertExists('photo/'.$photo->filename);
    }
    public function test_batch_failure_rolls_back_files_and_photo_limit_is_enforced(): void
    {
        $good=UploadedFile::fake()->image('good.png');
        $bad=UploadedFile::fake()->createWithContent('broken.png',substr(file_get_contents($good->getRealPath()),0,40));
        $this->post(route('service.photos.store',$this->service),['photos'=>[$good,$bad]])->assertSessionHasErrors();
        $this->assertSame(0,ServicePhoto::count());
        $this->assertSame([],Storage::disk('service_photos')->allFiles());
        $photos=[];for($i=0;$i<10;$i++)$photos[]=UploadedFile::fake()->image('photo.jpg');
        $this->post(route('service.photos.store',$this->service),['photos'=>$photos])->assertSessionHasNoErrors();
        $this->post(route('service.photos.store',$this->service),['photos'=>[UploadedFile::fake()->image('extra.jpg')]])->assertSessionHasErrors('photos');
        $this->assertSame(10,ServicePhoto::count());
        $this->assertCount(10,Storage::disk('service_photos')->allFiles());
    }
    public function test_images_are_reencoded_and_fake_executable_or_oversized_images_are_rejected(): void
    {
        $original=UploadedFile::fake()->image('logo.png',1200,600);
        $bytes=file_get_contents($original->getRealPath()).'<?php echo "UPLOAD_PAYLOAD"; ?>';
        $this->post(route('setting.general'),['invoice_logo'=>UploadedFile::fake()->createWithContent('logo.png',$bytes)])->assertSessionHasNoErrors();
        $filename=DB::table('settings')->where('parent_id',$this->owner->id)->where('name','invoice_logo')->value('value');
        $disk=Storage::disk('local'); $saved=$disk->get('upload/logo/'.$filename);
        $this->assertStringNotContainsString('UPLOAD_PAYLOAD',$saved);
        $this->assertSame([480,240],array_slice(getimagesizefromstring($saved),0,2));
        $this->post(route('setting.general'),['logo'=>UploadedFile::fake()->createWithContent('payload.png','<?php echo 1;')])->assertSessionHasErrors('logo');
        $this->post(route('service.photos.store',$this->service),['photos'=>[UploadedFile::fake()->image('huge.jpg',6001,1)]])->assertSessionHasErrors('photos.0');
        $this->post(route('service.photos.store',$this->service),['photos'=>[UploadedFile::fake()->createWithContent('bad.svg','<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')]])->assertSessionHasErrors();
        $this->assertSame(0,ServicePhoto::count());
    }
    public function test_storage_failure_leaves_no_photo_record_and_returns_a_clear_form_error(): void
    {
        $disk=\Mockery::mock();
        $disk->shouldReceive('put')->once()->andThrow(\League\Flysystem\UnableToWriteFile::atLocation('photo/test.jpg'));
        $disk->shouldReceive('delete')->once()->andReturn(true);
        Storage::shouldReceive('disk')->with('service_photos')->andReturn($disk);
        $this->post(route('service.photos.store',$this->service),['photos'=>[UploadedFile::fake()->image('photo.jpg')]])
            ->assertSessionHasErrors('photos');
        $this->assertSame(0,ServicePhoto::count());
    }
    public function test_employee_assignment_and_deletion_are_enforced(): void
    {
        $photo=$this->upload();
        $employee=User::create(['name'=>'Employee','email'=>null,'parent_id'=>$this->owner->id,'type'=>'employee','password'=>'x']);
        $this->actingAs($employee)->get(route('service.photos.show',[$this->service->id,$photo->id]))->assertForbidden();
        $this->service->update(['assign'=>$employee->id]);
        $this->get(route('service.photos.show',[$this->service->id,$photo->id]))->assertOk();
        $this->delete(route('service.photos.destroy',[$this->service->id,$photo->id]))->assertSessionHas('success');
        Storage::disk('service_photos')->assertMissing('photo/'.$photo->filename);
        $this->assertSame(0,ServicePhoto::count());
        $this->actingAs($this->owner); $photo=$this->upload(); $this->service->delete();
        Storage::disk('service_photos')->assertMissing('photo/'.$photo->filename);
        $this->assertSame(0,ServicePhoto::count());
    }
    public function test_foreign_business_read_mutation_and_line_endpoints_are_blocked(): void
    {
        $client=User::create(['name'=>'Private client','email'=>null,'parent_id'=>$this->other->id,'type'=>'client','password'=>'x']);
        $vehicle=Vehicle::create(['parent_id'=>$this->other->id,'client'=>$client->id]);
        $service=Service::create(['parent_id'=>$this->other->id,'vehicle'=>$vehicle->id,'status'=>'scheduled']);
        $invoice=Invoice::create(['parent_id'=>$this->other->id,'service'=>$service->id]);
        $item=Item::create(['parent_id'=>$this->other->id,'title'=>'Private item']);
        $this->get(route('client.show',encrypt($client->id)))->assertNotFound();
        $this->delete(route('client.destroy',$client->id))->assertNotFound();
        $this->get(route('service.show',encrypt($service->id)))->assertNotFound();
        $this->get(route('vehicle.show',$vehicle))->assertForbidden();
        $this->get(route('invoice.show',encrypt($invoice->id)))->assertNotFound();
        $this->get(route('item.show',$item->id))->assertForbidden();
        $this->post(route('service.change.status'),['applicantId'=>$service->id,'stageId'=>'completed'])->assertNotFound();
        $type=DB::table('service_types')->insertGetId(['parent_id'=>$this->other->id,'type'=>'Private type']);
        $this->post(route('service.type'),['type_id'=>$type])->assertNotFound();
        $line=DB::table('service_items')->insertGetId(['parent_id'=>$this->other->id,'service_id'=>$service->id,'type_id'=>$type]);
        $this->delete(route('service.type.destroy'),['id'=>$line])->assertNotFound();
        $staff=User::create(['name'=>'Private staff','email'=>null,'parent_id'=>$this->other->id,'type'=>'employee','password'=>'x']);
        $this->get(route('users.edit',$staff->id))->assertNotFound();
        $this->put(route('users.update',$staff->id),['name'=>'changed'])->assertNotFound();
        $this->delete(route('users.destroy',$staff->id))->assertNotFound();
        $this->assertDatabaseHas('services',['id'=>$service->id,'status'=>'scheduled']);
        $this->assertDatabaseHas('service_items',['id'=>$line]);
        $this->assertDatabaseHas('users',['id'=>$client->id,'name'=>'Private client']);
        $this->assertDatabaseHas('users',['id'=>$staff->id,'name'=>'Private staff']);
        $this->get(route('employee.show',encrypt($staff->id)))->assertNotFound();
        $this->get(route('employee.edit',$staff->id))->assertNotFound();
        $this->delete(route('employee.destroy',$staff->id))->assertNotFound();
        $quotation=\App\Models\Quotation::create(['parent_id'=>$this->other->id,'client_id'=>$client->id,'vehicle_id'=>$vehicle->id]);
        $this->get(route('quotation.show',encrypt($quotation->id)))->assertNotFound();
        $this->get(route('quotation.edit',encrypt($quotation->id)))->assertNotFound();
        $this->delete(route('quotation.destroy',$quotation->id))->assertNotFound();
        $this->get(route('quotation.convert',$quotation->id))->assertNotFound();
        $this->post(route('quotation.convertservice',$quotation->id),[])->assertNotFound();
        $this->get(route('quotation.item',['item_id'=>$item->id]))->assertNotFound();
        $expense=\App\Models\Expense::create(['parent_id'=>$this->other->id,'title'=>'Private expense','amount'=>100]);
        $this->get(route('expense.edit',$expense->id))->assertForbidden();
        $this->delete(route('expense.destroy',$expense->id))->assertForbidden();
        $this->assertDatabaseHas('quotations',['id'=>$quotation->id,'parent_id'=>$this->other->id]);
        $this->assertDatabaseHas('expenses',['id'=>$expense->id,'amount'=>100]);
    }
    public function test_service_and_quotation_inputs_cannot_link_other_business_records(): void
    {
        $client=User::create(['name'=>'Own client','email'=>null,'parent_id'=>$this->owner->id,'type'=>'client','password'=>'x']);
        $this->vehicle->update(['client'=>$client->id]);
        $type=DB::table('service_types')->insertGetId(['parent_id'=>$this->owner->id,'type'=>'Oil change','rate'=>100]);
        $foreignType=DB::table('service_types')->insertGetId(['parent_id'=>$this->other->id,'type'=>'Private type','rate'=>100]);
        $foreignTax=DB::table('taxes')->insertGetId(['parent_id'=>$this->other->id,'title'=>'Private tax','rate'=>20]);
        $foreignStaff=User::create(['name'=>'Foreign staff','email'=>null,'type'=>'employee','parent_id'=>$this->other->id,'password'=>'x']);
        $payload=['client'=>$client->id,'vehicle'=>$this->vehicle->id,'assign'=>$this->owner->id,'status'=>'scheduled','types'=>[]];
        foreach([
            array_replace($payload,['assign'=>$foreignStaff->id]),
            array_replace($payload,['types'=>[['service_type'=>$foreignType,'rate'=>100,'note'=>'']]]),
            array_replace($payload,['types'=>[['service_type'=>$type,'rate'=>100,'tax'=>[$foreignTax],'note'=>'']]]),
        ] as $bad) $this->post(route('service.store'),$bad)->assertSessionHas('error');
        $this->assertSame(1,Service::count());
        $this->post(route('service.store'),$payload)->assertSessionMissing('error');
        $this->assertSame(2,Service::count());
        $quote=['client'=>$client->id,'vehicle'=>$this->vehicle->id,'quotation_date'=>'2026-10-15','types'=>[],'items'=>[],'status'=>'draft'];
        $this->post(route('quotation.store'),array_replace($quote,['types'=>[['service_type'=>$foreignType,'rate'=>100]]]))->assertSessionHasErrors('types.0.service_type');
        $this->assertSame(0,\App\Models\Quotation::count());
        $this->post(route('quotation.store'),$quote)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(1,\App\Models\Quotation::count());
    }
    public function test_completion_starts_retention_and_exact_expiry_hides_then_removes_only_photos(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-15 12:00:00'));
        $photo=$this->upload();$code=$this->code($this->vehicle);
        $invoice=Invoice::create(['parent_id'=>$this->owner->id,'service'=>$this->service->id]);
        $this->service->update(['status'=>'completed']);
        $completed=$this->service->fresh()->completed_at->toDateTimeString();
        $this->travel(89)->days();
        $this->service->update(['notes'=>'Updated after completion']);
        $this->assertSame($completed,$this->service->fresh()->completed_at->toDateTimeString());
        $this->get(route('vehicle-portal.photo',[$code->token,$photo->id]))->assertOk();
        $this->travel(1)->days();
        $this->get(route('vehicle-portal.photo',[$code->token,$photo->id]))->assertNotFound();
        $this->get(route('service.photos.show',[$this->service->id,$photo->id]))->assertNotFound();
        $this->get(route('vehicle-portal.show',$code->token))->assertOk()->assertDontSee(route('vehicle-portal.photo',[$code->token,$photo->id]));
        $this->post(route('service.photos.store',$this->service),['photos'=>[UploadedFile::fake()->image('new.jpg')]])->assertSessionHasErrors('photos');
        $this->artisan('photos:purge')->assertExitCode(0);
        $this->assertDatabaseMissing('service_photos',['id'=>$photo->id]);
        $this->assertDatabaseHas('service_photo_deletions',['filename'=>$photo->filename]);
        $this->assertDatabaseHas('services',['id'=>$this->service->id]);
        $this->assertDatabaseHas('vehicles',['id'=>$this->vehicle->id]);
        $this->assertDatabaseHas('invoices',['id'=>$invoice->id]);
        Storage::disk('service_photos')->assertMissing('photo/'.$photo->filename);
    }
    public function test_active_services_and_recent_orphans_are_kept_while_old_orphans_are_cleaned(): void
    {
        $photo=$this->upload();
        DB::table('services')->where('id',$this->service->id)->update(['status'=>'in_progress','created_at'=>now()->subYears(2),'updated_at'=>now()->subYears(2)]);
        $disk=Storage::disk('service_photos');
        $old=$this->owner->id.'_999_'.Str::uuid().'.jpg';
        $recent=$this->owner->id.'_998_'.Str::uuid().'.jpg';
        $paused=$this->other->id.'_997_'.Str::uuid().'.jpg';
        foreach([$old,$recent,$paused] as $filename)$disk->put('photo/'.$filename,'orphan');
        foreach([$old,$paused] as $filename)touch($disk->path('photo/'.$filename),now()->subDays(2)->timestamp);
        DB::table('business_recovery_locks')->insert(['owner_id'=>$this->other->id,'recovery_id'=>(string)Str::uuid(),'created_at'=>now()]);
        $stats=app(\App\Services\PhotoRetention::class)->purge();
        $this->assertSame(0,$stats['photos']);$this->assertSame(1,$stats['orphans']);
        $disk->assertMissing('photo/'.$old);$disk->assertExists('photo/'.$recent);$disk->assertExists('photo/'.$paused);
        $disk->assertExists('photo/'.$photo->filename);
        $this->assertDatabaseHas('service_photos',['id'=>$photo->id]);
    }
    public function test_policy_admin_usage_and_non_revival_after_reopening_or_restoring_old_metadata(): void
    {
        $photo=$this->upload();
        $this->get(route('photo-retention.index'))->assertForbidden();
        $this->post(route('photo-retention.save'),['days'=>30])->assertForbidden();
        $admin=User::where('type','super admin')->first();
        $this->actingAs($admin)->get(route('photo-retention.index'))->assertOk()->assertSee('Fotoğraf saklama ve kullanım')
            ->assertViewHas('owners',fn($owners)=>(int)$owners->first()->photo_count===1 && (int)$owners->first()->photo_bytes===$photo->bytes);
        $this->post(route('photo-retention.save'),['days'=>0])->assertSessionHasErrors('days');
        $this->post(route('photo-retention.save'),['days'=>366])->assertSessionHasErrors('days');
        $this->service->update(['status'=>'completed']);
        DB::table('services')->where('id',$this->service->id)->update(['completed_at'=>now()->subDays(91)]);
        $this->post(route('photo-retention.save'),['days'=>365])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(365,\App\Services\PhotoRetention::days());
        $this->assertDatabaseHas('service_photo_deletions',['filename'=>$photo->filename]);
        DB::table('services')->where('id',$this->service->id)->update(['status'=>'in_progress','completed_at'=>null]);
        $this->assertSame(0,ServicePhoto::visible()->whereKey($photo->id)->count());
        app(\App\Services\PhotoRetention::class)->purge();
        $restored=$photo->getAttributes();DB::table('service_photos')->insert($restored);
        Storage::disk('service_photos')->put('photo/'.$photo->filename,'restored-from-backup');
        $this->assertSame(0,ServicePhoto::visible()->whereKey($photo->id)->count());
        app(\App\Services\PhotoRetention::class)->purge();
        $this->assertDatabaseMissing('service_photos',['id'=>$photo->id]);
        Storage::disk('service_photos')->assertMissing('photo/'.$photo->filename);
    }
    public function test_expired_photos_do_not_reappear_when_service_is_reopened_before_cleanup(): void
    {
        $photo=$this->upload();
        $this->service->update(['status'=>'completed']);
        DB::table('services')->where('id',$this->service->id)->update(['completed_at'=>now()->subDays(91)]);
        $this->service->refresh()->update(['status'=>'in_progress']);
        $this->assertDatabaseHas('service_photo_deletions',['filename'=>$photo->filename]);
        $this->assertSame(0,ServicePhoto::visible()->whereKey($photo->id)->count());
        $this->service->update(['status'=>'completed']);
        $this->assertTrue($this->service->fresh()->completed_at->greaterThan(now()->subMinute()));
        $this->assertSame(0,ServicePhoto::visible()->whereKey($photo->id)->count());
    }
    public function test_cleanup_waits_for_backup_and_business_recovery_locks(): void
    {
        $photo=$this->upload();$this->service->update(['status'=>'completed']);
        DB::table('services')->where('id',$this->service->id)->update(['completed_at'=>now()->subDays(91)]);
        $lock=fopen(app(\App\Services\PlatformBackups::class)->root().'/run.lock','c');
        flock($lock,LOCK_EX);
        try {$this->artisan('photos:purge')->expectsOutput('Yedekleme veya kurtarma sürüyor; fotoğraf temizliği sonraki çalışmaya bırakıldı.')->assertExitCode(0);}
        finally {flock($lock,LOCK_UN);fclose($lock);}
        $this->assertDatabaseHas('service_photos',['id'=>$photo->id]);
        DB::table('business_recovery_locks')->insert(['owner_id'=>$this->owner->id,'recovery_id'=>(string)Str::uuid(),'created_at'=>now()]);
        $this->artisan('photos:purge')->assertExitCode(0);
        $this->assertDatabaseHas('service_photos',['id'=>$photo->id]);
        Storage::disk('service_photos')->assertExists('photo/'.$photo->filename);
        DB::table('business_recovery_locks')->where('owner_id',$this->owner->id)->delete();
        $this->artisan('photos:purge')->assertExitCode(0);
        $this->assertDatabaseMissing('service_photos',['id'=>$photo->id]);
    }
}
