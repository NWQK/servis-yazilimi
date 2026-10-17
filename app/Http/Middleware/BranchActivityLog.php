<?php
namespace App\Http\Middleware;
use App\Services\{BranchActivity,BranchContext};
use Closure;
use Illuminate\Support\Facades\{DB,Schema};
class BranchActivityLog
{
    public function handle($request, Closure $next)
    {
        $user=$request->user(); $name=(string)$request->route()?->getName();
        $owner=$user && in_array($user->type,['owner','employee','branch_manager'],true) ? parentId() : null;
        $group=$owner && BranchContext::businessRoute($name) && Schema::hasTable('business_branches')
            ? DB::table('business_branches')->where('owner_id',$owner)->value('group_id') : null;
        $response=$next($request);
        $flashed=$request->hasSession() ? $request->session()->get('_flash.new',[]) : [];
        if ($group && !$request->isMethod('get') && !$request->isMethod('head') && $response->getStatusCode()<400
            && !array_intersect(['errors','error'],$flashed)
            && !in_array($name,['setting.account','setting.password','setting.twofa.enable'],true)) {
            $recordId=$request->attributes->get('created_invoice_id');
            foreach($request->route()->parameters() as $value) {
                if($value instanceof \Illuminate\Database\Eloquent\Model) { $recordId=$value->getKey(); break; }
                if(is_scalar($value) && ctype_digit((string)$value)) { $recordId=(int)$value; break; }
                if(is_string($value)) try{$decoded=decrypt($value);if(is_scalar($decoded)&&ctype_digit((string)$decoded)){$recordId=(int)$decoded;break;}}catch(\Throwable $e){}
            }
            BranchActivity::record($group,$owner,'operation',$recordId?['record_id'=>(int)$recordId]:[],$name);
        }
        return $response;
    }
}
