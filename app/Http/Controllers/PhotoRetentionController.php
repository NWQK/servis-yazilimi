<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Services\{PhotoRetention,AdminAudit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PhotoRetentionController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->type==='super admin',403);
        $data=$request->validate(['search'=>'nullable|string|max:150']);
        $usage=DB::table('service_photos')->selectRaw('parent_id, COUNT(*) AS photo_count, SUM(bytes) AS photo_bytes')->groupBy('parent_id');
        $owners=User::where('type','owner')->leftJoinSub($usage,'photo_usage',fn($q)=>$q->on('photo_usage.parent_id','=','users.id'))
            ->select('users.id','users.name','users.email')->selectRaw('COALESCE(photo_count,0) AS photo_count, COALESCE(photo_bytes,0) AS photo_bytes')
            ->when($data['search']??null,fn($q,$search)=>$q->where(fn($q)=>$q->where('users.name','like','%'.$search.'%')->orWhere('users.email','like','%'.$search.'%')))
            ->orderByDesc('photo_bytes')->orderBy('users.id')->paginate(25)->withQueryString();
        $days=PhotoRetention::days();
        $total=DB::table('service_photos')->selectRaw('COUNT(*) AS photos, COALESCE(SUM(bytes),0) AS bytes')->first();
        $last=json_decode(DB::table('settings')->where('parent_id',0)->where('type','platform')->where('name','photo_cleanup_last')->value('value')??'',true);
        $error=DB::table('settings')->where('parent_id',0)->where('type','platform')->where('name','photo_cleanup_error')->value('value');
        return view('admin.photo-retention',compact('owners','days','total','last','error'));
    }
    public function save(Request $request)
    {
        abort_unless($request->user()?->type==='super admin',403);
        $data=$request->validate(['days'=>'required|integer|min:1|max:365']);
        $before=PhotoRetention::days();
        PhotoRetention::setDays((int)$data['days']);
        AdminAudit::record('photos.retention',null,['days'=>$before],['days'=>(int)$data['days']]);
        return back()->with('success','Fotoğraf saklama süresi kaydedildi.');
    }
}
