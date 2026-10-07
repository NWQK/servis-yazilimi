<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Validation\ValidationException;

class TwoFactorAdminController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->type==='super admin',403);
        $data=$request->validate(['search'=>'nullable|string|max:150']);
        $owners=User::where('type','owner')->when($data['search'] ?? null,function($q,$term){
            $q->where(fn($q)=>$q->where('name','like','%'.$term.'%')->orWhere('email','like','%'.$term.'%'));
        })->orderBy('name')->paginate(25)->withQueryString();
        $actions=DB::table('two_factor_admin_actions as a')->leftJoin('users as o','o.id','=','a.owner_id')
            ->leftJoin('users as u','u.id','=','a.actor_id')->select('a.*','o.name as owner_name','u.name as actor_name')->orderByDesc('a.id')->limit(20)->get();
        return view('settings.two-factor-admin',compact('owners','actions'));
    }

    public function update(Request $request,int $id)
    {
        abort_unless($request->user()->type==='super admin',403);
        $data=$request->validate(['action'=>'required|in:require,disable,reset','reason'=>'required|string|max:500','password'=>'required|string']);
        if (!Hash::check($data['password'],$request->user()->password)) {
            throw ValidationException::withMessages(['password'=>'Süper admin şifresi hatalı.']);
        }
        DB::transaction(function() use($id,$data,$request) {
            $owner=User::where('type','owner')->whereKey($id)->lockForUpdate()->firstOrFail();
            $before=['required'=>$owner->twofa_required,'configured'=>(bool)$owner->twofa_secret];
            $owner->twofa_required=$data['action']!=='disable';
            if ($data['action']!=='require') {
                $owner->twofa_secret=null;
                $owner->twofa_last_used_at=null;
                $owner->twofa_recovery_codes=null;
            }
            $owner->save();
            \App\Services\AdminAudit::record('security.'.$data['action'],$owner->id,$before,['required'=>$owner->twofa_required,'configured'=>(bool)$owner->twofa_secret,'reason'=>$data['reason']]);
            DB::table('two_factor_admin_actions')->insert(['owner_id'=>$owner->id,'actor_id'=>$request->user()->id,
                'action'=>$data['action'],'reason'=>$data['reason'],'created_at'=>now()]);
        });
        return back()->with('success',match($data['action']) {
            'disable'=>'İşletmenin iki aşamalı doğrulaması kapatıldı. Eski anahtar ve kurtarma kodları iptal edildi.',
            'reset'=>'Doğrulama sıfırlandı. İşletmenin yeniden QR kurulumu yapması gerekiyor.',
            default=>'İki aşamalı doğrulama zorunlu hâle getirildi. Kurulum yoksa işletme QR kurulumuna yönlendirilecek.',
        });
    }
}
