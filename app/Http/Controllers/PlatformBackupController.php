<?php
namespace App\Http\Controllers;

use App\Models\{PlatformBackup, PlatformBackupSetting};
use App\Services\{AdminAudit, PlatformBackups};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PlatformBackupController extends Controller
{
    private function authorizeAdmin(): void { abort_unless(auth()->user()?->type === 'super admin', 403); }
    public function index(PlatformBackups $service)
    {
        $this->authorizeAdmin();
        $settings = PlatformBackupSetting::central();
        return view('admin.backups', [
            'settings'=>$settings, 'problems'=>$service->problems(),
            'copies'=>PlatformBackup::where(fn($q)=>$q->where('local_available',true)->orWhereNotNull('drive_path'))->orderByDesc('backup_date')->orderBy('kind')->get(),
            'monthlyMissing'=>!PlatformBackup::where('kind','monthly')->whereDate('backup_date',now('Europe/Istanbul')->startOfMonth()->toDateString())->exists(),
        ]);
    }
    public function save(Request $request, PlatformBackups $service)
    {
        $this->authorizeAdmin();
        $data = $request->validate(['enabled'=>'required|boolean']);
        if ($data['enabled'] && $service->problems()) throw ValidationException::withMessages(['enabled'=>implode(' ', $service->problems())]);
        $settings = PlatformBackupSetting::central();
        $before = $settings->enabled;
        $settings->update(['enabled'=>(bool)$data['enabled']]);
        AdminAudit::record('backups.status', null, ['enabled'=>$before], ['enabled'=>$settings->enabled]);
        return back()->with('success','Yedekleme tercihi kaydedildi.');
    }
    public function requestBackup(PlatformBackups $service)
    {
        $this->authorizeAdmin();
        $settings = PlatformBackupSetting::central();
        if (!$settings->enabled || $service->problems()) throw ValidationException::withMessages(['backup'=>'Önce kurulum gereksinimlerini tamamlayıp yedeklemeyi etkinleştirin.']);
        $settings->update(['requested_at'=>now()]);
        AdminAudit::record('backups.requested');
        return back()->with('success','Yedekleme talebi alındı. Sunucudaki zamanlayıcı bir dakika içinde başlatır. Bugünkü yedek varsa Drive aktarımı yeniden denenir.');
    }
}
