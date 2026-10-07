<?php
namespace App\Http\Controllers;
use App\Models\{Announcement,AnnouncementRecipient,User};
use App\Services\AdminAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class AnnouncementController extends Controller {
 private function admin():void {abort_unless(auth()->user()?->type==='super admin',403);}
 public function index(Request $r){
  abort_unless(in_array($r->user()->type,['super admin','owner']),403);
  $isAdmin=$r->user()->type==='super admin';
  $announcements=Announcement::query()->when(!$isAdmin,fn($q)=>$q->where('published',true)->whereHas('recipients',fn($q)=>$q->where('owner_id',auth()->id())))->withCount(['recipients','recipients as read_count'=>fn($q)=>$q->whereNotNull('read_at')])->latest('id')->paginate(20);
  $owners=$isAdmin ? User::where('type','owner')->orderBy('name')->get(['id','name','email']) : collect();
  return view('admin.announcements',compact('announcements','isAdmin','owners'));
 }
 public function store(Request $r){
  $this->admin();
  $data=$r->validate(['title'=>'required|string|max:150','body'=>'required|string|max:5000','audience'=>'required|in:all,selected','owner_ids'=>'required_if:audience,selected|array|max:500','owner_ids.*'=>['integer','distinct',\Illuminate\Validation\Rule::exists('users','id')->where('type','owner')]]);
  $announcement=DB::transaction(function()use($data){
   $a=Announcement::create(['actor_id'=>auth()->id(),'title'=>$data['title'],'body'=>$data['body'],'audience'=>$data['audience']]);
   User::where('type','owner')->when($data['audience']==='selected',fn($q)=>$q->whereIn('id',$data['owner_ids']))->select('id')->chunkById(500,function($owners)use($a){DB::table('announcement_recipients')->insert($owners->map(fn($o)=>['announcement_id'=>$a->id,'owner_id'=>$o->id])->all());});
   AdminAudit::record('announcement.created',null,[],['announcement_id'=>$a->id,'title'=>$a->title,'audience'=>$a->audience,'recipients'=>$a->recipients()->count()]);return $a;
  });
  return redirect()->route('announcements.show',$announcement->id)->with('success','Duyuru işletmelerin panel bildirimlerine gönderildi.');
 }
 public function show(int $id){
  abort_unless(in_array(auth()->user()->type,['super admin','owner']),403);
  $announcement=Announcement::findOrFail($id);$isAdmin=auth()->user()->type==='super admin';
  if(!$isAdmin){abort_unless($announcement->published,404);$recipient=$announcement->recipients()->where('owner_id',auth()->id())->firstOrFail();$announcement->recipients()->whereKey($recipient->id)->whereNull('read_at')->update(['read_at'=>now()]);}
  $recipients=$isAdmin ? $announcement->recipients()->with('owner')->paginate(25) : null;
  return view('admin.announcement-show',compact('announcement','isAdmin','recipients'));
 }
 public function withdraw(int $id){$this->admin();DB::transaction(function()use($id){$a=Announcement::lockForUpdate()->findOrFail($id);if(!$a->published)return;$a->update(['published'=>false]);AdminAudit::record('announcement.withdrawn',null,['published'=>true],['announcement_id'=>$a->id,'published'=>false]);});return back()->with('success','Duyuru yayından kaldırıldı.');}
}
