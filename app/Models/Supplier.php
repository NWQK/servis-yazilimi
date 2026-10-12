<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Supplier extends Model
{
    protected $fillable = ['company_name', 'contact_name', 'phone', 'email'];
    public function items() { return $this->hasMany(Item::class); }
    public function getWhatsappUrlAttribute(): ?string
    {
        $phone = \App\Support\TurkishPhone::mobile($this->phone);
        return $phone ? 'https://wa.me/'.ltrim($phone, '+') : null;
    }
}
