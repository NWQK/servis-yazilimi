<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\{DB, Hash};
use PragmaRX\Google2FAQRCode\Google2FA;

class TwoFactorAuthentication
{
    public function setupSecret(User $user): string
    {
        if (session('2fa_setup_user') !== $user->id || !session('2fa_secret')) {
            session(['2fa_setup_user'=>$user->id, '2fa_secret'=>(new Google2FA())->generateSecretKey(32)]);
        }
        return session('2fa_secret');
    }

    public function qr(User $user): string
    {
        $generator = new Google2FA(null, new \BaconQrCode\Renderer\Image\SvgImageBackEnd());
        $svg = $generator->getQRCodeInline('sanayirandevu.com', $user->email, $this->setupSecret($user), 240);
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public function consume(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (!$locked->twofa_secret) return false;
            if (preg_match('/^\d{6}$/D', $code)) {
                $step = (new Google2FA())->verifyKeyNewer($locked->twofa_secret, $code, $locked->twofa_last_used_at ?? 0, 1);
                if ($step === false) return false;
                $locked->twofa_last_used_at = $step;
            } else {
                $hashes = $locked->twofa_recovery_codes ?? [];
                $matched = false;
                foreach ($hashes as $index=>$hash) {
                    if (Hash::check(strtoupper(trim($code)), $hash)) {
                        unset($hashes[$index]); $matched = true; break;
                    }
                }
                if (!$matched) return false;
                $locked->twofa_recovery_codes = array_values($hashes);
            }
            $locked->save();
            return true;
        });
    }

    public function recoveryCodes(): array
    {
        return array_map(fn()=>strtoupper(bin2hex(random_bytes(4)).'-'.bin2hex(random_bytes(4))), range(1,8));
    }
}
