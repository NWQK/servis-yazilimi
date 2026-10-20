<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class MobileDetailOperation {
    /** Called only behind MobileSession's recovery fence + tenant accounting transaction. */
    public function run(Request $r,string $resource,int $id,string $action,\Closure $write): array {
        $owner=(int)$r->attributes->get('mobile_owner');
        $key=$r->header('Idempotency-Key');validator(['key'=>$key],['key'=>'required|uuid'])->validate();
        $version=(int)User::findOrFail($owner)->recovery_version;
        $body=$r->except('preview_token');
        $sort=function(&$v)use(&$sort){ksort($v);foreach($v as &$item)if(is_array($item))$sort($item);};$sort($body);
        $hash=hash('sha256',json_encode([$resource,$id,$action,$body],JSON_THROW_ON_ERROR));
        $query=DB::table('mobile_detail_operations')->where('owner_id',$owner)->where('actor_id',$r->user()->id)
            ->where('recovery_version',$version)->where('request_key',$key);
        if($prior=(clone $query)->lockForUpdate()->first()) {
            abort_unless(hash_equals($prior->payload_hash,$hash),409,'İşlem anahtarı başka bilgilerle kullanılmış.');
            return ['data'=>json_decode($prior->result,true),'meta'=>['replayed'=>true]];
        }
        $result=$write();
        DB::table('mobile_detail_operations')->insert(['owner_id'=>$owner,'actor_id'=>$r->user()->id,'recovery_version'=>$version,
            'request_key'=>$key,'resource'=>$resource,'record_id'=>$id,'action'=>$action,'payload_hash'=>$hash,
            'result'=>json_encode($result,JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
        return ['data'=>$result,'meta'=>['replayed'=>false]];
    }
}
