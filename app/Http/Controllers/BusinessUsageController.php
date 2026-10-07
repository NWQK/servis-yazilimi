<?php
namespace App\Http\Controllers;
use App\Services\BusinessUsage;
use Illuminate\Http\Request;
class BusinessUsageController extends Controller {
 public function index(Request $r){abort_unless($r->user()->type==='super admin',403);$data=$r->validate(['search'=>'nullable|string|max:150']);$owners=BusinessUsage::query()->when($data['search']??null,fn($q,$v)=>$q->where(fn($q)=>$q->where('name','like','%'.$v.'%')->orWhere('email','like','%'.$v.'%')))->orderBy('name')->paginate(25)->withQueryString();return view('admin.business-usage',compact('owners'));}
}
