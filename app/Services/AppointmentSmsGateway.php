<?php
namespace App\Services;

use App\Models\AppointmentSmsSetting;
use Illuminate\Support\Facades\Http;

class AppointmentSmsGateway
{
    public function send(string $phone, string $body): array
    {
        $settings = AppointmentSmsSetting::central();
        if (!$settings->ready()) return ['status' => 'failed', 'error_code' => 'not_configured'];
        $data = ['To' => $phone, 'Body' => $body];
        if ($settings->messaging_service_sid) $data['MessagingServiceSid'] = $settings->messaging_service_sid;
        else $data['From'] = $settings->from_number;
        try {
            // No automatic retry: a timed-out POST may already have sent an SMS.
            $response = Http::asForm()->withBasicAuth($settings->account_sid, $settings->auth_token)
                ->connectTimeout(5)->timeout(15)->post('https://api.twilio.com/2010-04-01/Accounts/'.$settings->account_sid.'/Messages.json', $data);
            if ($response->successful() && preg_match('/^SM[a-f0-9]{32}$/i', (string) $response->json('sid'))) {
                return ['status' => 'accepted', 'provider_sid' => $response->json('sid'), 'error_code' => null];
            }
            return ['status' => $response->clientError() ? 'failed' : 'unknown',
                'error_code' => ctype_digit((string) $response->json('code')) ? (string) $response->json('code') : 'provider_error'];
        } catch (\Throwable $e) {
            // Never persist exception text, request bodies, tokens, or OTP codes.
            return ['status' => 'unknown', 'error_code' => 'connection_error'];
        }
    }
}
