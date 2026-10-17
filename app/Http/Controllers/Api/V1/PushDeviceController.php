<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Crypt,DB};

class PushDeviceController extends Controller
{
    public function store(Request $request) {
        abort_unless($request->user()->type === 'owner',403);
        $input=$request->validate(['fid'=>'required|string|min:20|max:4096','platform'=>'required|in:android,ios']);
        $token=$request->user()->currentAccessToken();
        $hash=hash('sha256',$input['fid']);
        // A physical installation belongs to one authenticated session at a time.
        DB::table('mobile_push_devices')->where('token_hash',$hash)->where('access_token_id','!=',$token->id)->delete();
        DB::table('mobile_push_devices')->updateOrInsert(['access_token_id'=>$token->id],[
            'user_id'=>$request->user()->id,'owner_id'=>$request->attributes->get('mobile_owner'),
            'token_hash'=>$hash,'encrypted_token'=>Crypt::encryptString($input['fid']),
            'platform'=>$input['platform'],'created_at'=>now(),'updated_at'=>now(),
        ]);
        return response()->noContent();
    }
    public function destroy(Request $request) {
        DB::table('mobile_push_devices')->where('access_token_id',$request->user()->currentAccessToken()->id)->delete();
        return response()->noContent();
    }
}
