<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\{DB,Schema};
class SystemHealthController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->type==='super admin',403);
        $health=DB::table('system_health')->get()->keyBy('component');
        $errors=DB::table('system_health_errors')->where('day','>=',now()->subDays(30)->toDateString())->orderByDesc('last_seen_at')->paginate(25);
        $backup=\App\Models\PlatformBackupSetting::central();
        $lastBackup=\App\Models\PlatformBackup::where('local_available',true)->max('created_at');
        $lastDrive=\App\Models\PlatformBackup::max('drive_verified_at');
        $driveContentAt=\App\Models\PlatformBackup::whereNotNull('drive_verified_at')->max('created_at');
        $enabled=['smtp'=>\App\Services\CentralEmail::enabled(),'sms'=>\App\Models\AppointmentSmsSetting::central()->enabled,'scheduler'=>true];
        $failedJobs=Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        return view('admin.system-health',compact('health','errors','backup','lastBackup','lastDrive','driveContentAt','enabled','failedJobs'));
    }
}
