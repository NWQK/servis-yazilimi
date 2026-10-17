<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB,Crypt,Mail};
use Illuminate\Support\Str;

/** Durable, encrypted messages. A claimed delivery is never blindly resent. */
class CommunicationOutbox
{
    public static function asynchronous(): bool {return (bool)config('communications.async',true);}
    public function enqueue(string $channel,array $payload,?int $owner=null,?string $key=null,?\DateTimeInterface $expires=null,int $priority=5): object
    {
        $key=$key??(string)Str::uuid();
        if($owner)$payload['recovery_version']=(int)DB::table('users')->where('id',$owner)->value('recovery_version');
        DB::table('communication_outbox')->insertOrIgnore(['event_key'=>$key,'parent_id'=>$owner,'channel'=>$channel,
            'payload'=>Crypt::encryptString(json_encode($payload,JSON_THROW_ON_ERROR)),'status'=>'pending','priority'=>$priority,
            'available_at'=>now(),'expires_at'=>$expires,'created_at'=>now(),'updated_at'=>now()]);
        return DB::table('communication_outbox')->where('event_key',$key)->first();
    }
    public function email(string $to,array $data,string $type='common',?int $owner=null,?string $key=null,array $tracking=[],?\DateTimeInterface $expires=null): object
    {
        unset($data['settings']); // Never snapshot SMTP secrets; select central settings at delivery.
        return $this->enqueue('email',compact('to','data','type','tracking'),$owner,$key,$expires??now()->addDay(),$type==='verification'||($data['module']??'')==='platform_password_reset'?0:5);
    }
    public function work(int $limit=40): int
    {
        // A process may have died after acceptance. Keep such outcomes uncertain.
        $stale=DB::table('communication_outbox')->where('status','sending')->where('claimed_at','<',now()->subMinutes(10))->limit(100)->get();
        foreach($stale as $row){try{$p=json_decode(Crypt::decryptString($row->payload),true,512,JSON_THROW_ON_ERROR);}catch(\Throwable $e){$p=[];}
            $this->finish($row,'unknown','worker_interrupted',$p);
        }
        $count=0;$deadline=microtime(true)+45;
        while($count<$limit && microtime(true)<$deadline){
            // Recheck priority after every send, so new OTPs bypass an existing batch.
            $id=DB::table('communication_outbox')->where('status','pending')->where('available_at','<=',now())->orderBy('priority')->orderBy('id')->value('id');
            if(!$id)break;$this->deliver($id);$count++;
        }
        return $count;
    }
    public function deliver(int $id,bool $locked=false): void
    {
        $initial=DB::table('communication_outbox')->find($id);
        if(!$initial)return;
        if(!$locked && $initial->parent_id){app(\App\Services\Recovery\RecoveryWriteLock::class)->shared($initial->parent_id,fn()=>$this->deliver($id,true));return;}
        if(!DB::table('communication_outbox')->where('id',$id)->where('status','pending')->where('available_at','<=',now())
            ->update(['status'=>'sending','claimed_at'=>now(),'attempts'=>DB::raw('attempts + 1'),'updated_at'=>now()]))return;
        $row=DB::table('communication_outbox')->find($id);$payload=[];$networkStarted=false;
        try {
            $payload=json_decode(Crypt::decryptString($row->payload),true,512,JSON_THROW_ON_ERROR);
            if($row->parent_id){
                $owner=DB::table('users')->where('id',$row->parent_id)->first();
                if(!$owner || (int)$owner->recovery_version!==(int)($payload['recovery_version']??0)){$this->finish($row,'cancelled','business_changed',$payload);return;}
            }
            if($row->expires_at && now()->gte(\Carbon\Carbon::parse($row->expires_at))) {$this->finish($row,'cancelled','expired',$payload);return;}
            if($row->parent_id && DB::table('business_recovery_locks')->where('owner_id',$row->parent_id)->exists())throw new \RuntimeException('recovery');
            if(!$this->current($payload['tracking']??[])){$this->finish($row,'cancelled','superseded',$payload);return;}
            if($row->channel==='sms') {
                $settings=\App\Models\AppointmentSmsSetting::central();
                if(!$settings->enabled || (($payload['tracking']['kind']??'')==='vehicle' && !$settings->vehicle_sms_enabled)
                    || (($payload['tracking']['kind']??'')==='review_sms' && !$settings->review_sms_enabled)){$this->finish($row,'cancelled','disabled',$payload);return;}
                if(!$settings->ready())throw new \RuntimeException('configuration');
                $networkStarted=true;
                $result=app(AppointmentSmsGateway::class)->sendNow($payload['to'],$payload['body']);
                $this->finish($row,$result['status'],$result['error_code']??null,$payload,$result['provider_sid']??null);
            } else {
                if(!CentralEmail::enabled()){$this->finish($row,'cancelled','disabled',$payload);return;}
                $module=$payload['data']['module']??'';
                if($module && $module!==SecurityEmail::RESET){
                    $template=DB::table('notifications')->where('parent_id',CentralEmail::administrator()?->id)->where('module',$module)->first();
                    if($template && !$template->enabled_email){$this->finish($row,'cancelled','disabled',$payload);return;}
                }
                $smtp=app(TenantMailSettings::class)->apply(0);
                $data=$payload['data'];$data['settings']=$smtp;
                $message=match($payload['type']){'verification'=>new \App\Mail\EmailVerification($data),default=>new \App\Mail\Common($data)};
                $networkStarted=true;
                Mail::to($payload['to'])->send($message);
                $this->finish($row,'accepted',null,$payload);
            }
        } catch(\Throwable $e) {
            // Preflight failures are safe to retry. SMTP/network ambiguity is not.
            if(!$networkStarted && $row->attempts<3){
                DB::table('communication_outbox')->where('id',$id)->update(['status'=>'pending','available_at'=>now()->addMinutes($row->attempts*2),'error_code'=>'preflight_error','updated_at'=>now()]);
            } else $this->finish($row,$networkStarted?'unknown':'failed',$networkStarted?'delivery_unknown':'preflight_error',$payload);
            SystemHealth::record('communications',false,$networkStarted?'delivery_unknown':'preflight_error');
        }
    }
    private function current(array $t): bool
    {
        if(!$t)return true;
        $q=$this->tracked($t);if(!$q)return false;$row=$q->first();if(!$row)return false;
        if(($t['kind']??'')==='challenge')return !$row->appointment_id && hash_equals((string)$row->code_hash,(string)($t['code_hash']??''));
        if(($t['kind']??'')==='approval')return DB::table('appointments')->where('id',$row->appointment_id)->where('status','approved')->exists();
        if(($t['kind']??'')==='vehicle')return DB::table('vehicles')->where('id',$row->vehicle_id)->where('parent_id',$row->parent_id)->whereNull('deleted_at')->exists();
        if(in_array($t['kind']??'',['review_sms','review_email'],true))return DB::table('users')->where('id',$row->customer_id)->where('parent_id',$row->owner_id)->where('type','client')->whereNull('client_archived_at')->exists();
        if(($t['kind']??'')==='invoice')return (int)$row->client===(int)($t['client']??$row->client) && (int)$row->service===(int)($t['service']??$row->service);
        return true;
    }
    private function tracked(array $t)
    {
        $table=['challenge'=>'appointment_sms_challenges','approval'=>'appointment_sms_messages','vehicle'=>'vehicle_sms_messages','review_sms'=>'business_reviews','review_email'=>'business_reviews','invoice'=>'invoices'][$t['kind']??'']??null;
        if(!$table || empty($t['id']))return null;
        $q=DB::table($table)->where('id',$t['id']);
        if(isset($t['owner']))$q->where(in_array($t['kind'],['review_sms','review_email'],true)?'owner_id':'parent_id',$t['owner']);
        return $q;
    }
    private function finish(object $row,string $status,?string $code,array $payload,?string $sid=null): void
    {
        DB::transaction(function()use($row,$status,$code,$payload,$sid){
            DB::table('communication_outbox')->where('id',$row->id)->update(['status'=>$status,'error_code'=>$code,'payload'=>null,'updated_at'=>now()]);
            $t=$payload['tracking']??[];if(!$t || !$this->current($t))return;$q=$this->tracked($t);$sent=$status==='accepted'?now():null;
            $changes=match($t['kind']){
                'invoice'=> $status==='accepted'?['customer_email_sent_at'=>$sent]:[],
                'challenge'=>['send_status'=>$status,'error_code'=>$code,'provider_sid'=>$sid],
                'review_sms'=>['sms_status'=>$status,'sms_error'=>$code,'sms_sent_at'=>$sent,'provider_sid'=>$sid],
                'review_email'=>['email_status'=>$status,'email_error'=>$code,'email_sent_at'=>$sent],
                default=>['status'=>$status,'error_code'=>$code,'sent_at'=>$sent,'provider_sid'=>$sid],
            };
            if($changes)$q->update($changes);
        });
    }
}
