<?php
namespace App\Services;
use App\Models\AdminAuditLog;
class AdminAudit {
 public static function labels():array {return ['announcement.created'=>'Duyuru / bildirim gönderildi','announcement.withdrawn'=>'Duyuru kaldırıldı','subscription.update'=>'Paket / süre değiştirildi','subscription.suspend'=>'Abonelik askıya alındı','subscription.resume'=>'Abonelik açıldı','security.require'=>'İki aşamalı doğrulama zorunlu kılındı','security.disable'=>'İki aşamalı doğrulama kapatıldı','security.reset'=>'İki aşamalı doğrulama sıfırlandı','communication.status'=>'E-posta / SMS durumu değiştirildi','communication.smtp'=>'SMTP ayarları değiştirildi','communication.sms'=>'SMS ayarları değiştirildi','email.template'=>'E-posta şablonu değiştirildi','email.template_created'=>'E-posta şablonu oluşturuldu','email.template_deleted'=>'E-posta şablonu silindi','capacity.review'=>'Ek kapasite talebi değerlendirildi','business.created'=>'İşletme hesabı oluşturuldu','business.updated'=>'İşletme hesabı değiştirildi','business.deleted'=>'İşletme hesabı silindi'];}
 public static function record(string $action,?int $ownerId=null,array $before=[],array $after=[]):void {
  if(auth()->user()?->type!=='super admin') return;
  if(!\Illuminate\Support\Facades\Schema::hasTable('admin_audit_logs')) return;
  $clean=function(array $data) use (&$clean):array { $out=[];foreach($data as $key=>$value){if(preg_match('/password|secret|token|api_key|api_hash|recovery|payload/i',(string)$key))continue;$out[$key]=is_array($value)?$clean($value):$value;}return $out;};
  AdminAuditLog::create(['actor_id'=>auth()->id(),'actor_name'=>auth()->user()->name,'owner_id'=>$ownerId,'action'=>$action,'before'=>$clean($before),'after'=>$clean($after),'created_at'=>now()]);
 }
}
