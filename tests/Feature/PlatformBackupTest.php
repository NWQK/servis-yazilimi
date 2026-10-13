<?php
namespace Tests\Feature;

use App\Models\{PlatformBackup, PlatformBackupSetting, User};
use App\Services\{BackupDatabase, BackupDrive, PlatformBackups};
use Illuminate\Support\Facades\{DB, Gate};
use Tests\TestCase;

class PlatformBackupTest extends TestCase
{
    private string $folder;
    private PlatformBackups $service;
    private $drive;
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array']);
        DB::purge('sqlite');
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate', ['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        $this->folder = storage_path('framework/testing/backups-'.\Illuminate\Support\Str::uuid());
        mkdir($this->folder,0700,true);
        file_put_contents($this->folder.'/rclone.conf','test configuration');
        mkdir($this->folder.'/uploads'); file_put_contents($this->folder.'/uploads/logo.png','logo fixture');
        config(['backups.path'=>$this->folder.'/private','backups.password'=>str_repeat('Test-secret-',4),'backups.mysqldump'=>PHP_BINARY,'backups.rclone'=>PHP_BINARY,'backups.rclone_config'=>$this->folder.'/rclone.conf','backups.remote'=>'test-drive:Backups','backups.uploads'=>[$this->folder.'/uploads']]);
        $database = new class extends BackupDatabase {
            public function dump(string $target): void { file_put_contents($target, "CREATE TABLE restored (id INTEGER, name TEXT); INSERT INTO restored VALUES (1, 'Sanayi Randevu');"); }
        };
        $this->drive = new class extends BackupDrive {
            public array $files = []; public bool $fail = false;
            public function upload(string $local,string $remote): void {
                if ($this->fail) throw new \RuntimeException('provider failure');
                $this->files[$remote] = hash_file('sha256',$local);
            }
            public function delete(string $remote): void { unset($this->files[$remote]); }
        };
        $this->service = new PlatformBackups($database,$this->drive);
        $this->app->instance(PlatformBackups::class,$this->service);
        $this->assertSame([], $this->service->problems());
        PlatformBackupSetting::central()->update(['enabled'=>true]);
    }
    protected function tearDown(): void
    {
        $this->travelBack();
        if (isset($this->folder) && is_dir($this->folder)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->folder,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($this->folder);
        }
        parent::tearDown();
    }
    private function onDate(string $date): void { $this->travelTo(\Carbon\Carbon::parse($date.' 23:55:00','Europe/Istanbul')); }

    public function test_first_of_month_plus_last_three_days_and_monthly_rotation(): void
    {
        foreach (['2026-10-01','2026-10-27','2026-10-28','2026-10-29','2026-10-30'] as $date) { $this->onDate($date); $this->assertTrue($this->service->run()); }
        $this->onDate('2026-10-31');
        $this->assertSame(['2026-10-28','2026-10-29','2026-10-30'],PlatformBackup::where('kind','daily')->where('local_available',true)->orderBy('backup_date')->get()->map(fn($b)=>$b->backup_date->toDateString())->all());
        $this->assertSame('2026-10-01',PlatformBackup::where('kind','monthly')->where('local_available',true)->first()->backup_date->toDateString());
        $this->assertCount(4,$this->drive->files);
        $this->onDate('2026-11-01'); $this->assertTrue($this->service->run());
        $this->assertSame('2026-11-01',PlatformBackup::where('kind','monthly')->where('local_available',true)->first()->backup_date->toDateString());
        $this->assertCount(4,$this->drive->files);
        $this->assertCount(4,glob($this->folder.'/private/*.zip'));
    }

    public function test_cloud_failure_preserves_previous_cloud_backups_and_retry_is_idempotent(): void
    {
        foreach (['2026-10-01','2026-10-28','2026-10-29','2026-10-30'] as $date) { $this->onDate($date); $this->assertTrue($this->service->run()); }
        $before = $this->drive->files;
        $this->drive->fail = true; $this->onDate('2026-10-31'); $this->assertFalse($this->service->run());
        $this->assertSame($before,$this->drive->files);
        $this->assertNotNull(PlatformBackupSetting::central()->last_error);
        $backup = PlatformBackup::where('kind','daily')->whereDate('backup_date','2026-10-31')->firstOrFail();
        $this->drive->fail = false; $this->assertTrue($this->service->run());
        $this->assertSame($backup->sha256,$backup->fresh()->sha256);
        $this->assertCount(4,$this->drive->files);
        $this->assertNull(PlatformBackupSetting::central()->last_error);
    }

    public function test_archive_is_encrypted_and_can_restore_database_and_uploads(): void
    {
        $this->onDate('2026-10-13'); $this->assertTrue($this->service->run());
        $backup = PlatformBackup::firstOrFail();
        $zip = new \ZipArchive(); $this->assertTrue($zip->open($this->service->path($backup)));
        $this->assertFalse($zip->getFromName('database.sql'));
        $zip->setPassword(config('backups.password'));
        $sql = $zip->getFromName('database.sql');
        $restored = new \PDO('sqlite::memory:'); $restored->exec($sql);
        $this->assertSame('Sanayi Randevu',$restored->query('SELECT name FROM restored')->fetchColumn());
        $this->assertSame('logo fixture',$zip->getFromName('uploads/0/logo.png'));
        $this->assertFalse($zip->locateName('.env'));
        $this->assertFalse($zip->locateName('mysql.cnf'));
        $zip->close();
        $this->assertEmpty(glob($this->folder.'/private/work-*'));
    }

    public function test_missing_month_first_is_not_fabricated_and_disabled_does_not_delete_backups(): void
    {
        $this->onDate('2026-10-13'); $this->assertTrue($this->service->run());
        $this->assertSame(0,PlatformBackup::where('kind','monthly')->count());
        PlatformBackupSetting::central()->update(['enabled'=>false]);
        $this->onDate('2026-11-01'); $this->assertFalse($this->service->run());
        $this->assertSame(1,PlatformBackup::count());
    }

    public function test_bad_new_archive_never_prunes_older_backups(): void
    {
        foreach (['2026-10-27','2026-10-28','2026-10-29','2026-10-30'] as $date) { $this->onDate($date); $this->assertTrue($this->service->run()); }
        $old = PlatformBackup::whereDate('backup_date','2026-10-27')->first();
        // Recreate a valid older copy to prove corrupt replacements do not delete it.
        $new = PlatformBackup::whereDate('backup_date','2026-10-30')->first();
        copy($this->service->path($new),$this->service->path($old));
        $old->update(['local_available'=>true,'sha256'=>$new->sha256]);
        file_put_contents($this->service->path($new),'corrupt');
        try { $this->service->prune(); $this->fail('Corruption must stop retention.'); } catch (\RuntimeException $e) {}
        $this->assertFileExists($this->service->path($old));
    }

    public function test_admin_panel_and_controls_are_private_and_request_does_not_run_in_web(): void
    {
        Gate::before(fn($u)=>true);
        $admin = User::create(['name'=>'Admin','email'=>'backup-admin@example.test','password'=>'x','type'=>'super admin']);
        DB::table('settings')->insert(['parent_id'=>$admin->id,'name'=>'pricing_feature','value'=>'off']);
        $this->actingAs($admin)->withoutMiddleware(\App\Http\Middleware\XSS::class);
        $this->get(route('backups.index'))->assertOk()->assertSee('Platform yedekleri')->assertDontSee(config('backups.password'));
        $this->post(route('backups.request'))->assertRedirect()->assertSessionHas('success');
        $this->assertNotNull(PlatformBackupSetting::central()->requested_at);
        $this->assertSame(0,PlatformBackup::count());
        $this->assertTrue($this->service->run(true));
        $this->assertNull(PlatformBackupSetting::central()->requested_at);
        $owner = User::create(['name'=>'Shop','email'=>'backup-owner@example.test','password'=>'x','type'=>'owner']);
        $this->actingAs($owner)->get(route('backups.index'))->assertForbidden();
        $this->post(route('backups.save'),['enabled'=>false])->assertForbidden();
        $this->post(route('backups.request'))->assertForbidden();
        $this->assertTrue(PlatformBackupSetting::central()->enabled);
    }

    public function test_invalid_setup_cannot_be_enabled_and_secrets_are_not_logged(): void
    {
        config(['backups.password'=>'short']);
        $this->assertFalse($this->service->run());
        $this->assertSame(0,PlatformBackup::count());
        $this->assertStringNotContainsString('short',PlatformBackupSetting::central()->last_error);
        $admin = User::create(['name'=>'Admin','email'=>'backup-invalid@example.test','password'=>'x','type'=>'super admin']);
        $this->actingAs($admin)->withoutMiddleware(\App\Http\Middleware\XSS::class)->post(route('backups.save'),['enabled'=>true])->assertSessionHasErrors('enabled');
    }

    public function test_monthly_cloud_failure_keeps_previous_month_until_retry(): void
    {
        $this->onDate('2026-10-01'); $this->assertTrue($this->service->run());
        $old = PlatformBackup::where('kind','monthly')->firstOrFail();
        $this->drive->fail = true;
        $this->onDate('2026-11-01'); $this->assertFalse($this->service->run());
        $this->assertArrayHasKey($old->drive_path,$this->drive->files);
        $this->assertNotNull($old->fresh()->drive_verified_at);
        $this->assertFalse($old->fresh()->local_available);
        $this->drive->fail = false;
        $this->assertTrue($this->service->run());
        $this->assertArrayNotHasKey($old->drive_path,$this->drive->files);
        $this->assertNull($old->fresh()->drive_verified_at);
    }

    public function test_drive_verification_rejects_success_response_with_wrong_hash(): void
    {
        $file = $this->folder.'/sample.zip'; file_put_contents($file,'backup contents');
        $drive = new class extends BackupDrive {
            public bool $valid = false;
            protected function command(array $arguments): string {
                return $arguments[0] === 'lsjson' ? json_encode(['Size'=>15,'Hashes'=>['md5'=>$this->valid ? md5('backup contents') : str_repeat('0',32)]]) : '';
            }
        };
        try { $drive->upload($file,'drive:test'); $this->fail('Incorrect hash must fail.'); } catch (\RuntimeException $e) { $this->assertStringContainsString('bütünlük',$e->getMessage()); }
        $drive->valid = true;
        $drive->upload($file,'drive:test');
        $this->assertTrue(true);
    }

    public function test_failed_dump_preserves_existing_backups_and_removes_plaintext(): void
    {
        $this->onDate('2026-10-13'); $this->assertTrue($this->service->run());
        $before = $this->drive->files;
        $database = new class extends BackupDatabase {
            public function dump(string $target): void {
                file_put_contents($target,'partial SQL');
                throw new \RuntimeException('connection failed');
            }
        };
        $this->onDate('2026-10-14');
        $this->assertFalse((new PlatformBackups($database,$this->drive))->run());
        $this->assertSame($before,$this->drive->files);
        $this->assertSame(1,PlatformBackup::count());
        $this->assertEmpty(glob($this->folder.'/private/work-*'));
    }
}
