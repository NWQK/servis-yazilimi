<?php
namespace App\Http\Controllers;
use App\Models\{BusinessRecovery,PlatformBackup,User};
use App\Services\{AdminAudit,Recovery\RecoveryFiles};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BusinessRecoveryController extends Controller
{
    private function authorizeAdmin(): void{abort_unless(auth()->user()?->type==='super admin',403);}
    public function index()
    {
        $this->authorizeAdmin();
        $jobs=BusinessRecovery::with(['owner','backup'])->latest()->paginate(20);
        return view('admin.recoveries.index',compact('jobs'));
    }
    public function create(Request $request)
    {
        $this->authorizeAdmin();
        $owners=User::where('type','owner')->orderBy('name')->get(['id','name']);
        $copies=PlatformBackup::where(fn($q)=>$q->where('local_available',true)->orWhereNotNull('drive_verified_at'))->latest('backup_date')->get();
        $selectedBackup=(int)$request->query('backup');$selectedOwner=(int)$request->query('owner');
        $sqlite=in_array('sqlite',\PDO::getAvailableDrivers(),true);
        return view('admin.recoveries.create',compact('owners','copies','selectedBackup','selectedOwner','sqlite'));
    }
    public function store(Request $request)
    {
        $this->authorizeAdmin();
        $data=$request->validate(['owner_id'=>'required|integer','backup_id'=>'required|integer|exists:platform_backups,id','mode'=>'required|in:missing,replace']);
        User::where('type','owner')->findOrFail($data['owner_id']);
        $backup=PlatformBackup::findOrFail($data['backup_id']);
        if(!$backup->local_available&&!$backup->drive_verified_at)throw ValidationException::withMessages(['backup_id'=>'Yedeğin saklanan bir kopyası yok.']);
        if(!in_array('sqlite',\PDO::getAvailableDrivers(),true))throw ValidationException::withMessages(['backup_id'=>'Sunucuda PHP PDO SQLite eklentisini etkinleştirin.']);
        $job=DB::transaction(function()use($data){
            User::whereKey($data['owner_id'])->lockForUpdate()->firstOrFail();
            if(BusinessRecovery::where('owner_id',$data['owner_id'])->whereIn('status',['queued_prepare','preparing','queued_apply','applying','needs_attention'])->exists())
                throw ValidationException::withMessages(['owner_id'=>'İşletmenin devam eden kurtarma işlemini önce tamamlayın.']);
            return BusinessRecovery::create($data+['id'=>(string)Str::uuid(),'actor_id'=>auth()->id(),'status'=>'queued_prepare','requested_at'=>now()]);
        });
        AdminAudit::record('recovery.requested',$job->owner_id,[],['operation_id'=>$job->id,'mode'=>$job->mode]);
        return redirect()->route('recoveries.show',$job)->with('success','Ön izleme hazırlığı sıraya alındı. Henüz işletme verileri değiştirilmedi.');
    }
    public function show(BusinessRecovery $recovery)
    {
        $this->authorizeAdmin();$recovery->load(['owner','backup','sourceRecovery']);
        return view('admin.recoveries.show',compact('recovery'));
    }
    public function apply(Request $request,BusinessRecovery $recovery)
    {
        $this->authorizeAdmin();
        $data=$request->validate(['confirm'=>'accepted','owner_name'=>'required|string|max:255','current_password'=>'required|string']);
        if(!Hash::check($data['current_password'],auth()->user()->password))throw ValidationException::withMessages(['current_password'=>'Yönetici şifresi doğrulanamadı.']);
        DB::transaction(function()use($recovery,$data){
            $owner=User::where('type','owner')->whereKey($recovery->owner_id)->lockForUpdate()->firstOrFail();
            $job=BusinessRecovery::whereKey($recovery->id)->lockForUpdate()->firstOrFail();
            if(!hash_equals($owner->name,trim($data['owner_name'])))throw ValidationException::withMessages(['owner_name'=>'İşletme hesap adını ekranda gösterildiği gibi yazın.']);
            if($job->status!=='preview'||!$job->expires_at||$job->expires_at->isPast()||!empty($job->summary['conflicts']))
                throw ValidationException::withMessages(['confirm'=>'Ön izleme uygulanabilir değil. Yeni hazırlık oluşturun.']);
            if(array_sum(array_intersect_key($job->summary,array_flip(['insert','update','delete','revive','files'])))===0)
                throw ValidationException::withMessages(['confirm'=>'Kurtarılacak bir fark bulunmuyor.']);
            $job->update(['status'=>'queued_apply','actor_id'=>auth()->id(),'requested_at'=>now()]);
        });
        AdminAudit::record('recovery.approved',$recovery->owner_id,[],['operation_id'=>$recovery->id]);
        return back()->with('success','Onay kaydedildi. İşlem öncesi kurtarma kopyası alınarak yalnızca bu işletme işlenecek.');
    }
    public function undo(BusinessRecovery $recovery)
    {
        $this->authorizeAdmin();
        if(!in_array($recovery->status,['completed','failed','needs_attention'],true)||!$recovery->checkpoint_hash
            ||!$recovery->checkpoint_expires_at||($recovery->checkpoint_expires_at->isPast()&&$recovery->status!=='needs_attention'))
            throw ValidationException::withMessages(['undo'=>'İşlem öncesi kurtarma kopyası kullanılamıyor.']);
        $job=DB::transaction(function()use($recovery){
            User::where('type','owner')->whereKey($recovery->owner_id)->lockForUpdate()->firstOrFail();
            if(BusinessRecovery::where('owner_id',$recovery->owner_id)->whereIn('status',['queued_prepare','preparing','queued_apply','applying'])->exists())
                throw ValidationException::withMessages(['undo'=>'İşletmenin başka kurtarma işlemi devam ediyor.']);
            return BusinessRecovery::create(['id'=>(string)Str::uuid(),'owner_id'=>$recovery->owner_id,'actor_id'=>auth()->id(),
                'source_recovery_id'=>$recovery->id,'mode'=>'rollback','status'=>'queued_prepare','requested_at'=>now()]);
        });
        AdminAudit::record('recovery.undo_requested',$job->owner_id,[],['operation_id'=>$job->id,'source_operation'=>$recovery->id]);
        return redirect()->route('recoveries.show',$job)->with('success','İşlem öncesine dönüş için ayrı ön izleme hazırlanıyor. Yeniden onayınız olmadan veri değişmez.');
    }
    public function cancel(BusinessRecovery $recovery)
    {
        $this->authorizeAdmin();
        DB::transaction(function()use($recovery){
            User::whereKey($recovery->owner_id)->lockForUpdate()->firstOrFail();
            $job=BusinessRecovery::whereKey($recovery->id)->lockForUpdate()->firstOrFail();
            if(!in_array($job->status,['queued_prepare','preview','blocked','queued_apply'],true))throw ValidationException::withMessages(['cancel'=>'Çalışan veya tamamlanmış işlem bu düğmeyle iptal edilemez.']);
            $job->update(['status'=>'cancelled','finished_at'=>now()]);
        });
        return back()->with('success','Bekleyen kurtarma işlemi iptal edildi.');
    }
}
