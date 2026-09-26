<?php
namespace App\Services;

use App\Models\{Appointment, AppointmentProfile, AppointmentSmsChallenge, AppointmentSmsMessage, AppointmentSmsSetting};
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Validation\ValidationException;

class AppointmentSms
{
    private function invalid(string $text): void { throw ValidationException::withMessages(['sms' => $text]); }
    private function fingerprint(string $text): string { return hash_hmac('sha256', $text, config('app.key')); }

    private function limits(AppointmentSmsSetting $settings, string $phoneHash, string $ipHash): void
    {
        $recent = AppointmentSmsChallenge::where('last_sent_at', '>=', now()->subHour());
        if ((clone $recent)->where('phone_hash', $phoneHash)->sum('send_count') >= 3 ||
            (clone $recent)->where('ip_hash', $ipHash)->sum('send_count') >= 10) {
            $this->invalid('SMS gönderim sınırına ulaştınız. Lütfen bir saat sonra tekrar deneyin.');
        }
        $todayCount = AppointmentSmsChallenge::where('last_sent_at', '>=', today())->sum('send_count')
            + AppointmentSmsMessage::where('created_at', '>=', today())->count();
        if ($todayCount >= $settings->daily_limit) $this->invalid('Bugün için SMS gönderim sınırına ulaşıldı. Lütfen işletmeyle iletişime geçin.');
    }

    public function start(AppointmentProfile $profile, array $data, string $ip): AppointmentSmsChallenge
    {
        AppointmentSmsSetting::central();
        $code = (string) random_int(100000, 999999);
        [$challenge, $send] = DB::transaction(function () use ($profile, $data, $ip, $code) {
            $settings = AppointmentSmsSetting::lockForUpdate()->findOrFail(1);
            if (!$settings->ready()) $this->invalid('SMS doğrulaması şu anda kullanılamıyor. Lütfen daha sonra tekrar deneyin.');
            $existing = AppointmentSmsChallenge::where('appointment_profile_id', $profile->id)->where('request_key', $data['request_key'])->first();
            if ($existing) return [$existing, false];
            if (!in_array((int) $data['hour'], app(AppointmentBooking::class)->availableHours($profile->fresh(), $data['date']), true)) {
                throw ValidationException::withMessages(['hour' => 'Bu saat artık uygun değil. Lütfen başka bir saat seçin.']);
            }
            $phoneHash = $this->fingerprint($data['phone']);
            $ipHash = $this->fingerprint($ip);
            $this->limits($settings, $phoneHash, $ipHash);
            $challenge = AppointmentSmsChallenge::create([
                'appointment_profile_id' => $profile->id, 'token' => bin2hex(random_bytes(32)),
                'request_key' => $data['request_key'], 'phone_hash' => $phoneHash, 'ip_hash' => $ipHash,
                'payload' => $data, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(5), 'last_sent_at' => now(),
            ]);
            return [$challenge, true];
        });
        if ($send) $this->sendCode($challenge, $profile, $code);
        return $challenge;
    }

    private function sendCode(AppointmentSmsChallenge $challenge, AppointmentProfile $profile, string $code): void
    {
        $settings = AppointmentSmsSetting::central();
        $body = $settings->renderMessage($settings->verification_template, ['{kod}' => $code, '{isletme}' => $profile->display_name]);
        $result = app(AppointmentSmsGateway::class)->send($challenge->payload['phone'], $body);
        $challenge->update(['send_status' => $result['status']]);
    }

    public function resend(AppointmentSmsChallenge $challenge, AppointmentProfile $profile): void
    {
        $payload = $challenge->payload;
        if (!in_array((int) $payload['hour'], app(AppointmentBooking::class)->availableHours($profile->fresh(), $payload['date']), true)) {
            $this->invalid('Seçtiğiniz saat artık uygun değil. Lütfen yeni bir randevu talebi oluşturun.');
        }
        $code = (string) random_int(100000, 999999);
        DB::transaction(function () use ($challenge, $code) {
            $settings = AppointmentSmsSetting::lockForUpdate()->findOrFail(1);
            if (!$settings->ready()) $this->invalid('SMS doğrulaması şu anda kullanılamıyor.');
            $locked = AppointmentSmsChallenge::lockForUpdate()->findOrFail($challenge->id);
            if ($locked->appointment_id) $this->invalid('Bu talep zaten doğrulandı.');
            if ($locked->last_sent_at->gt(now()->subMinute())) $this->invalid('Yeni kod istemek için en az 60 saniye bekleyin.');
            if ($locked->send_count >= 3) $this->invalid('Bu talep için SMS gönderim sınırına ulaşıldı. Yeni bir randevu talebi oluşturun.');
            $this->limits($settings, $locked->phone_hash, $locked->ip_hash);
            $locked->update(['code_hash' => Hash::make($code), 'attempts' => 0, 'send_count' => $locked->send_count + 1,
                'send_status' => 'sending', 'last_sent_at' => now(), 'expires_at' => now()->addMinutes(5)]);
        });
        $this->sendCode($challenge->fresh(), $profile, $code);
    }

    public function verify(AppointmentSmsChallenge $challenge, AppointmentProfile $profile, string $code): Appointment
    {
        // Return validation errors after commit so failed attempts cannot be rolled back.
        $result = DB::transaction(function () use ($challenge, $profile, $code) {
            $locked = AppointmentSmsChallenge::lockForUpdate()->findOrFail($challenge->id);
            if ($locked->appointment_id) return $profile->appointments()->findOrFail($locked->appointment_id);
            if (!AppointmentSmsSetting::central()->ready()) return 'SMS doğrulaması şu anda kullanılamıyor. Lütfen daha sonra tekrar deneyin.';
            if (!$locked->expires_at->isFuture()) return 'Kodun süresi doldu. Yeni bir kod isteyin.';
            if ($locked->attempts >= 5) return 'Çok fazla hatalı kod girdiniz. Yeni bir kod isteyin.';
            if (!in_array($locked->send_status, ['accepted', 'unknown'], true)) return 'SMS henüz gönderilemedi. Yeni bir kod isteyin.';
            $locked->increment('attempts');
            if (!Hash::check($code, $locked->code_hash)) return 'Onay kodu hatalı.';
            $appointment = app(AppointmentBooking::class)->book($profile, $locked->payload);
            $appointment->phone_verified_at = now();
            $appointment->save();
            $locked->update(['appointment_id' => $appointment->id, 'code_hash' => null]);
            return $appointment;
        });
        if (is_string($result)) $this->invalid($result);
        return $result;
    }

    public function queueApproval(Appointment $appointment): void
    {
        if (!$appointment->phone_verified_at) return;
        $settings = AppointmentSmsSetting::central();
        AppointmentSmsMessage::firstOrCreate(['appointment_id' => $appointment->id], [
            'recipient' => $appointment->phone,
            'body' => $settings->renderMessage($settings->approval_template, ['{isletme}' => $appointment->profile->display_name,
                '{tarih}' => dateFormat($appointment->starts_at), '{saat}' => timeFormat($appointment->starts_at)]),
        ]);
    }

    public function dispatchApproval(int $appointmentId): ?AppointmentSmsMessage
    {
        $message = AppointmentSmsMessage::where('appointment_id', $appointmentId)->first();
        if (!$message) return null;
        $claimed = AppointmentSmsMessage::whereKey($message->id)->where('status', 'pending')->update(['status' => 'sending', 'updated_at' => now()]);
        if (!$claimed) return $message->fresh();
        $appointment = $message->appointment;
        if (!$appointment || $appointment->status !== 'approved') {
            $message->update(['status' => 'cancelled']);
            return $message;
        }
        $result = app(AppointmentSmsGateway::class)->send($message->recipient, $message->body);
        $message->update($result + ['sent_at' => $result['status'] === 'accepted' ? now() : null]);
        return $message;
    }
}
