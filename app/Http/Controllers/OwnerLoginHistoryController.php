<?php
namespace App\Http\Controllers;
use App\Models\{LoggedHistory, User};
use Illuminate\Http\Request;
class OwnerLoginHistoryController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->type === 'super admin',403);
        app(\App\Services\OwnerLoginSecurity::class)->purge();
        $filters=$request->validate(['owner'=>'nullable|integer|min:1']);
        $histories=LoggedHistory::with('user')->where('type','owner')->where('date','>',now()->subDays(7))
            ->when($filters['owner'] ?? null,fn($query,$id)=>$query->where('user_id',$id))
            ->orderByDesc('date')->orderByDesc('id')->paginate(30)->withQueryString();
        $owners=User::where('type','owner')->orderBy('name')->get(['id','name','email']);
        return view('logged_history.owners',compact('histories','owners','filters'));
    }
}
