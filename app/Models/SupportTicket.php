<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupportTicket extends Model
{
    protected $guarded=['id'];
    protected $casts=['owner_unread'=>'boolean','admin_unread'=>'boolean','last_message_at'=>'datetime'];
    public const STATUSES=['waiting_support'=>'Destek yanıtı bekleniyor','waiting_owner'=>'İşletme yanıtı bekleniyor','open'=>'İnceleniyor','closed'=>'Kapatıldı'];
    public const PRIORITIES=['low'=>'Düşük','normal'=>'Normal','high'=>'Yüksek'];
    public function owner() {return $this->belongsTo(User::class,'owner_id');}
    public function messages() {return $this->hasMany(SupportTicketMessage::class,'ticket_id');}
}
