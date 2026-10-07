<?php
namespace App\Http\Controllers;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule,ValidationException};

class SupportTicketController extends Controller
{
    private function admin(): bool {return auth()->user()->type==='super admin';}
    private function authorizeUser(): void {abort_unless(in_array(auth()->user()->type,['owner','super admin'],true),403);}
    private function query() {
        $this->authorizeUser();
        return SupportTicket::query()->when(!$this->admin(),fn($query)=>$query->where('owner_id',auth()->id()));
    }
    public function index(Request $request) {
        $filters=$request->validate(['search'=>'nullable|string|max:150','status'=>['nullable',Rule::in(array_keys(SupportTicket::STATUSES))],'priority'=>['nullable',Rule::in(array_keys(SupportTicket::PRIORITIES))],'unread'=>'nullable|in:1']);
        $query=$this->query()->with('owner');
        if (!empty($filters['search'])) {
            $term=$filters['search'];
            $query->where(function($q) use($term) {
                $q->where('subject','like','%'.$term.'%');
                if (ctype_digit($term)) $q->orWhere('id',(int)$term);
                if ($this->admin()) $q->orWhereHas('owner',fn($q)=>$q->where('name','like','%'.$term.'%')->orWhere('email','like','%'.$term.'%'));
            });
        }
        if (!empty($filters['status'])) $query->where('status',$filters['status']);
        if (!empty($filters['priority'])) $query->where('priority',$filters['priority']);
        $unreadField=$this->admin()?'admin_unread':'owner_unread';
        if (!empty($filters['unread'])) $query->where($unreadField,true);
        $tickets=$query->orderByDesc($unreadField)->orderByDesc('last_message_at')->orderByDesc('id')->paginate(20)->withQueryString();
        $isAdmin=$this->admin();
        return view('support.index',compact('tickets','filters','isAdmin'));
    }
    public function create() {
        $this->authorizeUser();abort_if($this->admin(),403);
        return view('support.create');
    }
    public function store(Request $request) {
        $this->authorizeUser();abort_if($this->admin(),403);
        $data=$request->validate(['subject'=>'required|string|max:150','priority'=>['required',Rule::in(array_keys(SupportTicket::PRIORITIES))],'body'=>'required|string|max:10000']);
        $ticket=DB::transaction(function() use($data) {
            $ticket=SupportTicket::create(['owner_id'=>auth()->id(),'subject'=>$data['subject'],'priority'=>$data['priority'],
                'status'=>'waiting_support','last_message_at'=>now(),'admin_unread'=>true,'owner_unread'=>false]);
            $ticket->messages()->create(['author_id'=>auth()->id(),'is_admin'=>false,'body'=>$data['body']]);
            return $ticket;
        });
        return redirect()->route('support.show',$ticket->id)->with('success','Destek biletiniz oluşturuldu. Yanıtları bu ekrandan takip edebilirsiniz.');
    }
    public function show(int $id) {
        $isAdmin=$this->admin();
        [$ticket,$messages]=DB::transaction(function() use($id,$isAdmin) {
            $ticket=$this->query()->lockForUpdate()->findOrFail($id);
            $messages=$ticket->messages()->with('author')->orderByDesc('id')->paginate(40);
            $ticket->{$isAdmin?'admin_unread':'owner_unread'}=false;$ticket->save();$ticket->load('owner');
            return [$ticket,$messages];
        });
        return view('support.show',compact('ticket','messages','isAdmin'));
    }
    public function reply(Request $request,int $id) {
        // Check ownership before processing the supplied message.
        $this->query()->findOrFail($id);
        $data=$request->validate(['body'=>'required|string|max:10000']);
        DB::transaction(function() use($id,$data) {
            $ticket=$this->query()->lockForUpdate()->findOrFail($id);
            if ($ticket->status==='closed') throw ValidationException::withMessages(['body'=>'Yanıt yazmak için bileti önce yeniden açın.']);
            $admin=$this->admin();
            $ticket->messages()->create(['author_id'=>auth()->id(),'is_admin'=>$admin,'body'=>$data['body']]);
            $ticket->status=$admin?'waiting_owner':'waiting_support';
            $ticket->owner_unread=$admin;$ticket->admin_unread=!$admin;$ticket->last_message_at=now();$ticket->save();
        });
        return redirect()->route('support.show',$id)->with('success','Mesajınız gönderildi.');
    }
    public function status(Request $request,int $id) {
        $this->query()->findOrFail($id);
        $allowed=$this->admin()?array_keys(SupportTicket::STATUSES):['closed','waiting_support'];
        $data=$request->validate(['status'=>['required',Rule::in($allowed)],'priority'=>['nullable',Rule::in(array_keys(SupportTicket::PRIORITIES))]]);
        DB::transaction(function() use($id,$data) {
            $ticket=$this->query()->lockForUpdate()->findOrFail($id);
            $changed=$ticket->status!==$data['status'];$ticket->status=$data['status'];
            if ($this->admin() && !empty($data['priority'])) {
                $changed=$changed || $ticket->priority!==$data['priority'];$ticket->priority=$data['priority'];
            }
            if ($changed) {
                $ticket->messages()->create(['author_id'=>auth()->id(),'is_admin'=>$this->admin(),
                    'body'=>'Bilet durumu: '.SupportTicket::STATUSES[$ticket->status].'. Öncelik: '.SupportTicket::PRIORITIES[$ticket->priority].'.']);
                $ticket->owner_unread=$this->admin();$ticket->admin_unread=!$this->admin();$ticket->last_message_at=now();
            }
            $ticket->save();
        });
        return redirect()->route('support.show',$id)->with('success','Bilet güncellendi.');
    }
}
