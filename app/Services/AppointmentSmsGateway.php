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
        if (!preg_match('/^\+905[0-9]{9}$/D', $phone)) return ['status' => 'failed', 'error_code' => 'invalid_phone'];
        try {
            // A timeout can occur after acceptance; never automatically retry a send.
            $response = Http::asJson()->acceptJson()->connectTimeout(5)->timeout(15)
                ->post('https://api.iletimerkezi.com/v1/send-sms/json', ['request' => [
                    'authentication' => ['key' => $settings->api_key, 'hash' => $settings->api_hash],
                    'order' => ['sender' => $settings->sender, 'iys' => '0', 'message' => [
                        'text' => $body, 'receipents' => ['number' => [ltrim($phone, '+')]],
                    ]],
                ]]);
            $code = (string) $response->json('response.status.code');
            $orderId = (string) $response->json('response.order.id');
            if ($response->successful() && $code === '200' && preg_match('/^[1-9][0-9]*$/D', $orderId)) {
                return ['status' => 'accepted', 'provider_sid' => $orderId, 'error_code' => null];
            }
            // Duplicate order can refer to a prior accepted request; reconcile manually.
            $failed = !$response->serverError() && $code !== '451' &&
                (($code !== '200' && ctype_digit($code) && (int) $code >= 400 && (int) $code < 500) || $response->clientError());
            return ['status' => $failed ? 'failed' : 'unknown', 'error_code' => ctype_digit($code) ? $code : 'provider_error'];
        } catch (\Throwable $e) {
            // Never persist raw exceptions, credentials, phone numbers or message text.
            return ['status' => 'unknown', 'error_code' => 'connection_error'];
        }
    }
}
