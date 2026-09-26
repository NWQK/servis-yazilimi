<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentSmsSetting extends Model
{
    public $incrementing = false;
    protected $guarded = [];
    protected $hidden = ['auth_token'];
    protected $casts = ['enabled' => 'boolean', 'auth_token' => 'encrypted'];

    public static function central(): self
    {
        if ($existing = static::find(1)) return $existing;
        // Concurrent first visits must converge on the same central settings row.
        static::query()->insertOrIgnore([
            'id' => 1, 'created_at' => now(), 'updated_at' => now(),
            'brand' => 'sanayirandevu.com',
            'verification_template' => '{kod}, {isletme} için randevu talebi onay kodunuzdur. Kod 5 dakika geçerlidir. {marka}',
            'approval_template' => '{isletme} için {tarih} tarihinde {saat} saatli randevu talebiniz onaylanmıştır. {marka}',
        ]);
        return static::findOrFail(1);
    }

    public function ready(): bool
    {
        return $this->enabled && $this->account_sid && $this->auth_token && ($this->from_number || $this->messaging_service_sid);
    }

    public function renderMessage(string $template, array $values): string
    {
        return strtr($template, $values + ['{marka}' => $this->brand]);
    }
}
