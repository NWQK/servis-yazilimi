<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class TenantMailSettings
{
    public function apply(int $ownerId): array
    {
        $values = DB::table('settings')->where('type', 'smtp')->where('parent_id', $ownerId)->pluck('value', 'name')->all();
        $values = array_merge(array_fill_keys(['FROM_EMAIL','FROM_NAME','SERVER_HOST','SERVER_PORT','SERVER_USERNAME','SERVER_PASSWORD','SERVER_ENCRYPTION'], ''), ['SERVER_DRIVER'=>'smtp'], $values);
        // Clear every field, including URL overrides, so another shop's credentials cannot be reused.
        config(['mail.default'=>'smtp', 'mail.mailers.smtp'=>[
            'transport'=>'smtp', 'scheme'=>$values['SERVER_ENCRYPTION'] === 'ssl' ? 'smtps' : 'smtp',
            'url'=>null, 'host'=>$values['SERVER_HOST'], 'port'=>(int) $values['SERVER_PORT'],
            'encryption'=>$values['SERVER_ENCRYPTION'] ?: null, 'username'=>$values['SERVER_USERNAME'],
            'password'=>$values['SERVER_PASSWORD'], 'timeout'=>15,
        ], 'mail.from.address'=>$values['FROM_EMAIL'], 'mail.from.name'=>$values['FROM_NAME']]);
        if (app('mail.manager') instanceof \Illuminate\Mail\MailManager) { app('mail.manager')->purge('smtp'); }
        if (!filter_var($values['FROM_EMAIL'], FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-zA-Z0-9.-]+$/', $values['SERVER_HOST']) || (int) $values['SERVER_PORT'] < 1 || (int) $values['SERVER_PORT'] > 65535 || !in_array($values['SERVER_ENCRYPTION'], ['tls','ssl'])) {
            throw new \RuntimeException('İşletmenin SMTP ayarları eksik.');
        }
        return $values;
    }
}
