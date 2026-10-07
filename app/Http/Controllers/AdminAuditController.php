<?php
namespace App\Http\Controllers;
use App\Models\{AdminAuditLog,User};
use Illuminate\Http\Request;
class AdminAuditController extends Controller {
 public function index(Request $r){abort_unless($r->user()->type==='super admin',403);$filters=$r->validate(['owner'=>'nullable|integer','action'=>'nullable|string|max:80','date'=>'nullable|date_format:Y-m-d']);$logs=AdminAuditLog::with('owner')->when($filters['owner']??null,fn($q,$v)=>$q->where('owner_id',$v))->when($filters['action']??null,fn($q,$v)=>$q->where('action',$v))->when($filters['date']??null,fn($q,$v)=>$q->whereDate('created_at',$v))->latest('id')->paginate(30)->withQueryString();$owners=User::where('type','owner')->orderBy('name')->get(['id','name']);$actions=AdminAuditLog::distinct()->orderBy('action')->pluck('action');return view('admin.audit',compact('logs','filters','owners','actions'));}
}
