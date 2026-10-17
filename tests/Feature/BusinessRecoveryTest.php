<?php
namespace Tests\Feature;
use App\Models\{User,Client,Vehicle,Service,Invoice,Item,PlatformBackup,BusinessRecovery};
use App\Services\{PlatformBackups,Recovery\RecoveryTables,Recovery\RecoveryFiles,Recovery\RecoveryStore,Recovery\BusinessRecoveryService,Recovery\SqlDumpReader};
use Illuminate\Support\Facades\{DB,Schema,Gate,Hash,Mail,Http};
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessRecoveryTest extends TestCase
{
    private User $admin;
    private User $owner;
    private User $other;
    private string $root;
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array']);
        DB::purge('sqlite');
        $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        $this->root=storage_path('framework/testing/recovery-'.Str::uuid());
        mkdir($this->root,0700,true);mkdir($this->root.'/uploads',0700);mkdir($this->root.'/public',0700);
        config(['recovery.write_lock_path'=>$this->root.'/write-locks','backups.path'=>$this->root.'/backups','backups.password'=>str_repeat('recovery-key-',4),
            'backups.uploads'=>[$this->root.'/uploads',$this->root.'/public']]);
        $this->admin=User::create(['name'=>'Admin','email'=>'admin@restore.test','type'=>'super admin','password'=>Hash::make('admin-password-123')]);
        $this->owner=User::create(['name'=>'Shop A','email'=>'shop-a@restore.test','type'=>'owner','parent_id'=>$this->admin->id,'password'=>Hash::make('owner-password')]);
        $this->other=User::create(['name'=>'Shop B','email'=>'shop-b@restore.test','type'=>'owner','parent_id'=>$this->admin->id,'password'=>Hash::make('other-password')]);
        $this->actingAs($this->admin);Gate::before(fn()=>true);
        $this->withoutMiddleware(\App\Http\Middleware\XSS::class);
        Mail::fake();Http::fake();
    }
    private function customer(User $owner,string $name='Customer'): User
    {
        $user=User::create(['name'=>$name,'email'=>Str::uuid().'@customer.test','type'=>'client','password'=>'x','parent_id'=>$owner->id,'phone_number'=>'5551234567']);
        Client::create(['user_id'=>$user->id,'parent_id'=>$owner->id,'client_id'=>1]);return $user;
    }
    private function records(User $owner): array
    {
        $client=$this->customer($owner);
        $type=DB::table('vehicle_types')->insertGetId(['parent_id'=>$owner->id,'type'=>'Ford']);
        $brand=DB::table('vehicle_brands')->insertGetId(['parent_id'=>$owner->id,'type'=>$type,'name'=>'Focus']);
        $vehicle=Vehicle::create(['parent_id'=>$owner->id,'client'=>$client->id,'type'=>$type,'brand'=>$brand,'license_plate'=>'34 TEST','chassis_no'=>'WVWZZZ1JZXW000001','mileage'=>100000]);
        $service=Service::create(['parent_id'=>$owner->id,'vehicle'=>$vehicle->id,'client'=>$client->id,'assign'=>$owner->id,'status'=>'completed','service_date'=>'2026-10-01']);
        $invoice=Invoice::create(['parent_id'=>$owner->id,'service'=>$service->id,'client'=>$client->id,'invoice_id'=>1,'status'=>0,'invoice_date'=>'2026-10-01']);
        return compact('client','vehicle','service','invoice');
    }
    private function literal($value): string
    {
        if($value===null)return 'NULL';
        return "'".str_replace(["\\","\0","\n","\r","\x1a","'"],["\\\\","\\0","\\n","\\r","\\Z","\\'"],(string)$value)."'";
    }
    private function backup(): PlatformBackup
    {
        // Canonical mysqldump DDL/extended INSERT shape, with real application schemas and records.
        $sql="-- MySQL dump\nCREATE DATABASE `production_database`;\nUSE `production_database`;\n";
        foreach(RecoveryTables::existing() as $table){
            $columns=Schema::getColumnListing($table);
            $sql.="DROP TABLE IF EXISTS `$table`;\nCREATE TABLE `$table` (\n".implode(",\n",array_map(fn($c)=>"  `$c` text",$columns))."\n);\n";
            $rows=[];
            foreach(DB::table($table)->orderBy(RecoveryTables::key($table))->get() as $row)$rows[]='('.implode(',',array_map(fn($c)=>$this->literal($row->$c),$columns)).')';
            if($rows)$sql.="INSERT INTO `$table` VALUES ".implode(',',$rows).";\n";
        }
        $source=$this->root.'/fixture.sql';file_put_contents($source,$sql);
        $date=\Carbon\Carbon::parse('2026-10-01')->addDays(PlatformBackup::count())->toDateString();
        $filename='daily-'.$date.'-'.Str::uuid().'.zip';$backups=app(PlatformBackups::class);$path=$backups->root().'/'.$filename;
        $backups->archive($source,$path,$date);
        return PlatformBackup::create(['kind'=>'daily','backup_date'=>$date,'filename'=>$filename,'bytes'=>filesize($path),'sha256'=>hash_file('sha256',$path)]);
    }
    private function prepared(PlatformBackup $backup,string $mode='missing'): BusinessRecovery
    {
        $job=BusinessRecovery::create(['id'=>(string)Str::uuid(),'owner_id'=>$this->owner->id,'actor_id'=>$this->admin->id,'backup_id'=>$backup->id,'mode'=>$mode,'status'=>'queued_prepare']);
        app(BusinessRecoveryService::class)->work();return $job->fresh();
    }
    private function execute(BusinessRecovery $job): BusinessRecovery
    {
        $job->update(['status'=>'queued_apply']);app(BusinessRecoveryService::class)->work();return $job->fresh();
    }
    public function test_dump_is_decoded_without_executing_sql_and_handles_mysql_escaping(): void
    {
        $file=$this->root.'/parser.sql';
        file_put_contents($file,"-- Ignore this;\nUSE `live_database`;\nCREATE TABLE `users` (\n `id` int,\n `parent_id` int,\n `name` text\n);\nINSERT INTO `users` VALUES (1,0,'Eren\\'s; -- not a comment'),(2,1,'Türkçe\\nSatır\\\\sonu'),(3,1,NULL);\nDROP TABLE invoices;\n");
        $store=new RecoveryStore($this->root.'/parser.sqlite');$store->import($file);
        $this->assertSame("Eren's; -- not a comment",$store->get('users',1)['name']);
        $this->assertSame("Türkçe\nSatır\\sonu",$store->get('users',2)['name']);
        $this->assertNull($store->get('users',3)['name']);$this->assertTrue(Schema::hasTable('invoices'));
        $values=iterator_to_array((new SqlDumpReader())->rows("(1,'a''b',0x4142,-123.45e2,NULL)"));
        $this->assertSame(['1',"a'b",'AB','-123.45e2',null],$values[0]);
    }
    public function test_missing_mode_revives_archived_records_without_overwriting_current_details_or_other_shop(): void
    {
        $a=$this->records($this->owner);$b=$this->records($this->other);$backup=$this->backup();
        $a['vehicle']->delete();$a['client']->update(['name'=>'Changed after backup']);
        DB::table('users')->where('id',$a['client']->id)->update(['client_archived_at'=>now(),'client_archive_was_active'=>1]);
        $beforeOther=(array)DB::table('vehicles')->find($b['vehicle']->id);
        $job=$this->prepared($backup);$this->assertSame('preview',$job->status,$job->error??json_encode($job->summary));
        $this->assertSame(2,$job->summary['revive']);$this->assertSame(0,$job->summary['delete']);
        $job=$this->execute($job);$this->assertSame('completed',$job->status,$job->error??'');
        $this->assertNull($a['vehicle']->fresh()->deleted_at);$this->assertNull($a['client']->fresh()->client_archived_at);
        $this->assertSame('Changed after backup',$a['client']->fresh()->name);
        $this->assertSame($beforeOther,(array)DB::table('vehicles')->find($b['vehicle']->id));
        $this->assertSame(1,$this->owner->fresh()->recovery_version);$this->assertSame(0,$this->other->fresh()->recovery_version);
        Mail::assertNothingSent();Http::assertNothingSent();
    }
    public function test_replace_restores_financial_data_removes_post_backup_records_and_can_be_undone(): void
    {
        $a=$this->records($this->owner);$b=$this->records($this->other);$backup=$this->backup();
        DB::table('invoice_payments')->insert(['invoice_id'=>$a['invoice']->id,'parent_id'=>$this->owner->id,'amount'=>100,'payment_date'=>'2026-10-02']);
        $a['client']->update(['name'=>'After backup']);
        $new=$this->customer($this->owner,'New customer after backup');
        $password=$this->owner->password;$other=DB::table('users')->where('parent_id',$this->other->id)->get()->toJson();
        $job=$this->prepared($backup,'replace');$this->assertSame('preview',$job->status,$job->error??json_encode($job->summary));
        $job=$this->execute($job);$this->assertSame('completed',$job->status,$job->error??'');
        $this->assertSame('Customer',$a['client']->fresh()->name);$this->assertNull($new->fresh());
        $this->assertSame(0,DB::table('invoice_payments')->where('parent_id',$this->owner->id)->count());
        $this->assertSame($password,$this->owner->fresh()->password);
        $this->assertSame($other,DB::table('users')->where('parent_id',$this->other->id)->get()->toJson());
        $this->post(route('recoveries.undo',$job))->assertRedirect();
        $undo=BusinessRecovery::where('source_recovery_id',$job->id)->firstOrFail();
        app(BusinessRecoveryService::class)->work();$undo=$undo->fresh();$this->assertSame('preview',$undo->status,$undo->error??json_encode($undo->summary));
        $undo=$this->execute($undo);$this->assertSame('completed',$undo->status,$undo->error??'');
        $this->assertSame('After backup',$a['client']->fresh()->name);$this->assertNotNull($new->fresh());
        $this->assertSame(1,DB::table('invoice_payments')->where('parent_id',$this->owner->id)->count());
        $this->assertSame('undone',$job->fresh()->status);$this->assertSame(0,DB::table('business_recovery_locks')->count());
        Mail::assertNothingSent();Http::assertNothingSent();
    }
    public function test_missing_financial_records_are_blocked_when_inventory_has_changed(): void
    {
        $a=$this->records($this->owner);
        $item=Item::create(['title'=>'Oil','quantity'=>10,'parent_id'=>$this->owner->id,'inventory_key'=>(string)Str::uuid()]);
        $backup=$this->backup();
        DB::table('invoices')->where('id',$a['invoice']->id)->delete();$item->update(['quantity'=>11]);
        $job=$this->prepared($backup);$this->assertSame('blocked',$job->status);
        $this->assertNotEmpty($job->summary['conflicts']);$this->assertSame(11,$item->fresh()->quantity);
    }
    public function test_stale_preview_and_tampered_archive_never_apply(): void
    {
        $a=$this->records($this->owner);$backup=$this->backup();$a['vehicle']->delete();$job=$this->prepared($backup);
        $a['client']->update(['name'=>'Updated during preview']);$job=$this->execute($job);
        $this->assertSame('failed',$job->status);$this->assertStringContainsString('değişti',$job->error);
        $this->assertNotNull(Vehicle::withTrashed()->find($a['vehicle']->id)->deleted_at);
        $this->assertSame(0,DB::table('business_recovery_locks')->count());
        $job=$this->prepared($backup);$path=app(RecoveryFiles::class)->directory($job).'/tenant.sqlite';
        file_put_contents($path,'tampered');$job=$this->execute($job);
        $this->assertSame('failed',$job->status);$this->assertStringContainsString('bütünlüğü',$job->error);
        $this->assertSame('Updated during preview',$a['client']->fresh()->name);
    }
    public function test_admin_only_confirmation_and_business_pause_are_enforced(): void
    {
        $a=$this->records($this->owner);$backup=$this->backup();$a['vehicle']->delete();$job=$this->prepared($backup);
        $this->get(route('recoveries.index'))->assertOk()->assertSee('İşletme veri kurtarma');
        $this->get(route('recoveries.show',$job))->assertOk()->assertSee('Onayla ve kurtarmayı sıraya al');
        $this->post(route('recoveries.apply',$job),['confirm'=>1,'owner_name'=>$this->owner->name,'current_password'=>'wrong'])->assertSessionHasErrors('current_password');
        $this->assertSame('preview',$job->fresh()->status);
        $this->actingAs($this->owner)->get(route('recoveries.index'))->assertForbidden();
        DB::table('business_recovery_locks')->insert(['owner_id'=>$this->owner->id,'recovery_id'=>$job->id,'created_at'=>now()]);
        $this->get(route('dashboard'))->assertStatus(503)->assertSee('veri kurtarma');
        $this->actingAs($this->other)->get(route('dashboard'))->assertOk();
        $this->actingAs($this->admin)->post(route('recoveries.apply',$job),['confirm'=>1,'owner_name'=>$this->owner->name,'current_password'=>'admin-password-123'])->assertSessionHasNoErrors();
        $this->assertSame('queued_apply',$job->fresh()->status);
    }
    public function test_logo_files_are_checkpointed_restored_and_undone(): void
    {
        $a=$this->records($this->owner);mkdir($this->root.'/uploads/logo',0700);
        $file=$this->root.'/uploads/logo/2_logo.png';file_put_contents($file,'old-logo');
        DB::table('settings')->insert(['parent_id'=>$this->owner->id,'name'=>'company_logo','value'=>'2_logo.png']);
        $backup=$this->backup();file_put_contents($file,'new-logo');
        $a['client']->update(['name'=>'new-name']);
        $job=$this->prepared($backup,'replace');$this->assertSame('preview',$job->status,$job->error??json_encode($job->summary));
        $this->assertSame(1,$job->summary['files']);$job=$this->execute($job);
        $this->assertSame('completed',$job->status,$job->error??'');$this->assertSame('old-logo',file_get_contents($file));
        $this->post(route('recoveries.undo',$job));app(BusinessRecoveryService::class)->work();
        $undo=BusinessRecovery::where('source_recovery_id',$job->id)->first();$undo=$this->execute($undo);
        $this->assertSame('completed',$undo->status,$undo->error??'');$this->assertSame('new-logo',file_get_contents($file));
    }
    public function test_failed_database_apply_rolls_back_database_and_files_and_keeps_rescue_copy(): void
    {
        $a=$this->records($this->owner);mkdir($this->root.'/uploads/logo',0700);
        $file=$this->root.'/uploads/logo/restore.png';file_put_contents($file,'old');
        DB::table('settings')->insert(['parent_id'=>$this->owner->id,'name'=>'invoice_logo','value'=>'restore.png']);
        $backup=$this->backup();file_put_contents($file,'current');$a['client']->update(['name'=>'Current']);
        $job=$this->prepared($backup,'replace');
        $plan=\Mockery::mock(\App\Services\Recovery\RecoveryPlan::class)->makePartial();
        $plan->shouldReceive('apply')->once()->andReturnUsing(function()use($a){
            DB::table('users')->where('id',$a['client']->id)->update(['name'=>'Half applied']);
            throw new \RuntimeException('Simulated failure');
        });
        $this->app->instance(\App\Services\Recovery\RecoveryPlan::class,$plan);
        $job=$this->execute($job);
        $this->assertSame('failed',$job->status);$this->assertNotNull($job->checkpoint_hash);
        $this->assertSame('Current',$a['client']->fresh()->name);$this->assertSame('current',file_get_contents($file));
        $this->assertSame(0,DB::table('business_recovery_locks')->count());
        Mail::assertNothingSent();Http::assertNothingSent();
    }
    public function test_private_acceptance_photos_are_backed_up_restored_and_undoable(): void
    {
        $a=$this->records($this->owner);
        $photoRoot=$this->root.'/private-photos';
        mkdir($photoRoot.'/photo',0700,true);
        config(['backups.uploads'=>[$this->root.'/uploads',$this->root.'/public',$photoRoot]]);
        $filename=$this->owner->id.'_'.$a['service']->id.'_'.Str::uuid().'.jpg';
        $file=$photoRoot.'/photo/'.$filename;file_put_contents($file,'photo-before');
        $id=DB::table('service_photos')->insertGetId(['parent_id'=>$this->owner->id,'service_id'=>$a['service']->id,'filename'=>$filename,'bytes'=>12,'created_at'=>now(),'updated_at'=>now()]);
        $backup=$this->backup();
        DB::table('service_photos')->where('id',$id)->delete();unlink($file);
        $job=$this->prepared($backup);
        $this->assertSame('preview',$job->status,$job->error??json_encode($job->summary));
        $this->assertSame(1,$job->summary['files']);
        $job=$this->execute($job);
        $this->assertSame('completed',$job->status,$job->error??'');
        $this->assertDatabaseHas('service_photos',['id'=>$id,'parent_id'=>$this->owner->id]);
        $this->assertSame('photo-before',file_get_contents($file));
        $this->post(route('recoveries.undo',$job));app(BusinessRecoveryService::class)->work();
        $undo=BusinessRecovery::where('source_recovery_id',$job->id)->first();$undo=$this->execute($undo);
        $this->assertSame('completed',$undo->status,$undo->error??'');
        $this->assertDatabaseMissing('service_photos',['id'=>$id]);
        $this->assertFileDoesNotExist($file);
    }
    public function test_restoring_a_backup_from_before_completion_does_not_publish_expired_photos(): void
    {
        $a=$this->records($this->owner);$a['service']->update(['status'=>'in_progress']);
        $photoRoot=$this->root.'/private-photos';mkdir($photoRoot.'/photo',0700,true);
        config(['backups.uploads'=>[$this->root.'/uploads',$this->root.'/public',$photoRoot]]);
        $filename=$this->owner->id.'_'.$a['service']->id.'_'.Str::uuid().'.jpg';
        file_put_contents($photoRoot.'/photo/'.$filename,'old-photo');
        $id=DB::table('service_photos')->insertGetId(['parent_id'=>$this->owner->id,'service_id'=>$a['service']->id,'filename'=>$filename,'bytes'=>9,'created_at'=>now(),'updated_at'=>now()]);
        $backup=$this->backup();
        $a['service']->update(['status'=>'completed']);
        DB::table('services')->where('id',$a['service']->id)->update(['completed_at'=>now()->subDays(91)]);
        // Even metadata already removed manually must not escape expiry through an older backup.
        DB::table('service_photos')->where('id',$id)->delete();unlink($photoRoot.'/photo/'.$filename);
        $job=$this->prepared($backup,'replace');$this->assertSame('preview',$job->status,$job->error??json_encode($job->summary));
        $job=$this->execute($job);$this->assertSame('completed',$job->status,$job->error??'');
        $this->assertDatabaseHas('services',['id'=>$a['service']->id,'status'=>'in_progress']);
        $this->assertDatabaseHas('service_photo_deletions',['filename'=>$filename]);
        $this->assertSame(0,\App\Models\ServicePhoto::visible()->whereKey($id)->count());
        $this->post(route('recoveries.undo',$job));app(BusinessRecoveryService::class)->work();
        $undo=BusinessRecovery::where('source_recovery_id',$job->id)->first();$undo=$this->execute($undo);
        $this->assertSame('completed',$undo->status,$undo->error??'');
        $this->assertSame(0,\App\Models\ServicePhoto::visible()->whereKey($id)->count());
    }
    public function test_interrupted_worker_repairs_files_before_unlocking_business(): void
    {
        $this->records($this->owner);mkdir($this->root.'/uploads/logo',0700);
        $file=$this->root.'/uploads/logo/crash.png';file_put_contents($file,'old');
        DB::table('settings')->insert(['parent_id'=>$this->owner->id,'name'=>'invoice_logo','value'=>'crash.png']);
        $backup=$this->backup();file_put_contents($file,'current');$job=$this->prepared($backup,'replace');
        $files=app(RecoveryFiles::class);$dir=$files->directory($job);
        $files->apply($job,$dir.'/source.zip',json_decode(file_get_contents($dir.'/files-plan.json'),true));
        $job->update(['status'=>'applying']);
        DB::table('business_recovery_locks')->insert(['owner_id'=>$this->owner->id,'recovery_id'=>$job->id,'created_at'=>now()]);
        $this->assertSame('old',file_get_contents($file));
        app(BusinessRecoveryService::class)->work();
        $this->assertSame('failed',$job->fresh()->status);$this->assertSame('current',file_get_contents($file));
        $this->assertSame(0,DB::table('business_recovery_locks')->count());
    }
    public function test_existing_staff_security_and_central_settings_are_preserved_and_restored_staff_are_disabled(): void
    {
        $a=$this->records($this->owner);
        $staff=User::create(['name'=>'Staff','email'=>'staff@restore.test','type'=>'employee','parent_id'=>$this->owner->id,'password'=>Hash::make('old')]);
        $removed=User::create(['name'=>'Removed','email'=>'removed@restore.test','type'=>'employee','parent_id'=>$this->owner->id,'password'=>Hash::make('removed')]);
        DB::table('settings')->insert(['parent_id'=>$this->owner->id,'name'=>'smtp_password','value'=>'before']);
        $backup=$this->backup();DB::table('users')->where('id',$removed->id)->delete();$staff->update(['password'=>Hash::make('new'),'name'=>'Current staff','is_active'=>0]);
        DB::table('settings')->where('parent_id',$this->owner->id)->where('name','smtp_password')->update(['value'=>'current']);
        $a['client']->update(['name'=>'Changed']);$password=$staff->fresh()->password;
        $job=$this->execute($this->prepared($backup,'replace'));
        $this->assertSame('completed',$job->status,$job->error??'');
        $this->assertSame($password,$staff->fresh()->password);$this->assertSame('Current staff',$staff->fresh()->name);
        $this->assertEquals(0,$staff->fresh()->is_active);$this->assertEquals(0,User::findOrFail($removed->id)->is_active);
        $this->assertFalse(Hash::check('removed',User::findOrFail($removed->id)->password));
        $this->assertSame('current',DB::table('settings')->where('parent_id',$this->owner->id)->where('name','smtp_password')->value('value'));
    }
    public function test_retention_expires_previews_and_keeps_only_checkpoint_for_seven_days(): void
    {
        $a=$this->records($this->owner);$backup=$this->backup();$a['vehicle']->delete();
        $preview=$this->prepared($backup);$preview->update(['expires_at'=>now()->subMinute()]);
        $dir=app(RecoveryFiles::class)->directory($preview);app(BusinessRecoveryService::class)->prune();
        $this->assertSame('expired',$preview->fresh()->status);$this->assertDirectoryDoesNotExist($dir);
        $job=$this->execute($this->prepared($backup));$this->assertSame('completed',$job->status);
        $dir=app(RecoveryFiles::class)->directory($job);app(BusinessRecoveryService::class)->prune();
        $this->assertFileExists($dir.'/checkpoint.zip');$this->assertFileDoesNotExist($dir.'/source.zip');
        $this->assertFileDoesNotExist($dir.'/tenant.sqlite');
        $job->update(['checkpoint_expires_at'=>now()->subMinute()]);app(BusinessRecoveryService::class)->prune();
        $this->assertDirectoryDoesNotExist($dir);$this->assertNull($job->fresh()->checkpoint_hash);
    }
    public function test_old_sessions_must_reauthenticate_and_old_public_sharing_is_not_republished(): void
    {
        $a=$this->records($this->owner);
        app(\App\Services\PublicVehicleHistory::class)->share($a['vehicle'],true);
        $backup=$this->backup();$a['client']->update(['name'=>'After']);$job=$this->execute($this->prepared($backup,'replace'));
        $this->assertSame('completed',$job->status,$job->error??'');
        $this->assertFalse(app(\App\Services\PublicVehicleHistory::class)->isPublic($a['vehicle']->fresh()));
        $this->actingAs($this->owner)->withSession(['business_recovery_epochs'=>[$this->owner->id=>0]])
            ->get(route('dashboard'))->assertRedirect(route('login'));
    }
    public function test_cross_business_id_collision_and_shared_file_collision_block_preview(): void
    {
        $a=$this->records($this->owner);$backup=$this->backup();
        DB::table('vehicles')->where('id',$a['vehicle']->id)->update(['parent_id'=>$this->other->id]);
        $job=$this->prepared($backup,'replace');$this->assertSame('blocked',$job->status);
        $this->assertStringContainsString('başka işletmeye',implode(' ',$job->summary['conflicts']));
        DB::table('vehicles')->where('id',$a['vehicle']->id)->update(['parent_id'=>$this->owner->id]);
        mkdir($this->root.'/uploads/logo',0700);$file=$this->root.'/uploads/logo/shared.png';file_put_contents($file,'before');
        DB::table('settings')->insert(['parent_id'=>$this->owner->id,'name'=>'invoice_logo','value'=>'shared.png']);
        DB::table('settings')->insert(['parent_id'=>$this->other->id,'name'=>'invoice_logo','value'=>'shared.png']);
        $backup=$this->backup();file_put_contents($file,'current');
        $job=$this->prepared($backup,'replace');$this->assertSame('blocked',$job->status);
        $this->assertSame('current',file_get_contents($file));
    }
    public function test_sql_decoder_rejects_expressions_and_handles_buffer_boundaries(): void
    {
        $file=$this->root.'/large.sql';$text=str_repeat('a',65530)."; ' quoted -- Türkçe";
        file_put_contents($file,"CREATE TABLE `users` (\n `id` int,\n `parent_id` int,\n `name` text\n);\nINSERT INTO `users` VALUES (1,1,".$this->literal($text).");");
        $store=new RecoveryStore($this->root.'/large.sqlite');$store->import($file);
        $this->assertSame($text,$store->get('users',1)['name']);
        $this->expectException(\App\Services\Recovery\RecoveryFailure::class);
        iterator_to_array((new SqlDumpReader())->rows("(1,NOW())"));
    }
    public function test_audit_file_changed_after_preview_blocks_application(): void
    {
        $this->records($this->owner);mkdir($this->root.'/uploads/logo',0700);
        $file=$this->root.'/uploads/logo/changed.png';file_put_contents($file,'backup');
        DB::table('settings')->insert(['parent_id'=>$this->owner->id,'name'=>'invoice_logo','value'=>'changed.png']);
        $backup=$this->backup();file_put_contents($file,'preview');
        $job=$this->prepared($backup,'replace');file_put_contents($file,'after-preview');
        $job=$this->execute($job);
        $this->assertSame('failed',$job->status);$this->assertSame('after-preview',file_get_contents($file));
    }
    public function test_audit_missing_recovery_cannot_revive_a_duplicate_chassis(): void
    {
        $a=$this->records($this->owner);$backup=$this->backup();$a['vehicle']->delete();
        Vehicle::create(['parent_id'=>$this->owner->id,'client'=>$a['client']->id,'chassis_no'=>$a['vehicle']->chassis_no]);
        $job=$this->prepared($backup);
        $this->assertSame('blocked',$job->status);
        $this->assertNotNull(Vehicle::withTrashed()->find($a['vehicle']->id)->deleted_at);
    }
    public function test_audit_authenticated_other_owner_cannot_bypass_public_business_pause(): void
    {
        $profile=new \App\Models\AppointmentProfile();
        $profile->forceFill(['owner_id'=>$this->owner->id,'public_id'=>(string)Str::uuid(),'display_name'=>'Paused shop','weekly_hours'=>[]])->save();
        DB::table('business_recovery_locks')->insert(['owner_id'=>$this->owner->id,'recovery_id'=>(string)Str::uuid(),'created_at'=>now()]);
        $this->actingAs($this->other)->get('/randevu/'.$profile->public_id)->assertStatus(503);
        $this->post('/randevu/'.$profile->public_id,[])->assertStatus(503);
    }
    public function test_audit_undo_removes_files_introduced_by_recovery(): void
    {
        $a=$this->records($this->owner);mkdir($this->root.'/uploads/logo',0700);
        $file=$this->root.'/uploads/logo/restored.png';file_put_contents($file,'logo');
        DB::table('settings')->insert(['parent_id'=>$this->owner->id,'name'=>'invoice_logo','value'=>'restored.png']);
        $backup=$this->backup();DB::table('settings')->where('parent_id',$this->owner->id)->where('name','invoice_logo')->delete();unlink($file);
        $job=$this->execute($this->prepared($backup,'replace'));$this->assertSame('completed',$job->status,$job->error??'');
        $this->assertFileExists($file);
        $this->post(route('recoveries.undo',$job))->assertRedirect();app(BusinessRecoveryService::class)->work();
        $undo=$this->execute(BusinessRecovery::where('source_recovery_id',$job->id)->first());
        $this->assertSame('completed',$undo->status,$undo->error??'');$this->assertFileDoesNotExist($file);
    }
    public function test_audit_cross_business_foreign_keys_cannot_be_changed_by_recovery(): void
    {
        $a=$this->records($this->owner);
        $category=DB::table('item_categories')->insertGetId(['parent_id'=>$this->owner->id,'name'=>'Own category']);
        $backup=$this->backup();
        $foreign=Item::create(['parent_id'=>$this->other->id,'title'=>'Other shop item','category_id'=>$category]);
        $a['client']->update(['name'=>'Changed']);$job=$this->prepared($backup,'replace');
        $this->assertSame('blocked',$job->status);
        $this->assertEquals($category,$foreign->fresh()->category_id);
    }
    public function test_unsupported_inserts_are_rejected_instead_of_treating_the_table_as_empty(): void
    {
        $file=$this->root.'/unsupported.sql';
        file_put_contents($file,"CREATE TABLE `vehicles` (\n `id` int,\n `parent_id` int\n);\nINSERT INTO vehicles VALUES (1,2);");
        $this->expectException(\App\Services\Recovery\RecoveryFailure::class);
        (new RecoveryStore($this->root.'/unsupported.sqlite'))->import($file);
    }
}
