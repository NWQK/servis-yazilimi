<?php

namespace App\Services;

use App\Mail\Common;
use App\Models\{Invoice, Notification, Service, User, Vehicle, VehicleQrCode};
use Illuminate\Support\Facades\{DB, Log, Mail};

class InvoiceCustomerEmail
{
    public const MODULE = 'platform_invoice_create';

    public static function definition(): array
    {
        return ['module' => self::MODULE, 'name' => 'Müşteriye fatura bildirimi (Sanayi Randevu)',
            'subject' => '{invoice_number} numaralı fatura — sanayirandevu.com',
            'templete' => '<p>Merhaba {client_name},</p><p><strong>{company_name}</strong> adlı işletmede yaptırdığınız işlemlere ait <strong>{invoice_number}</strong> numaralı faturanız oluşturuldu.</p><p>Fatura tutarı: <strong>{total_amount} ₺</strong></p><p><a href="{vehicle_link}" style="display:inline-block;padding:12px 20px;background:#e32228;color:#fff;text-decoration:none;border-radius:8px">Faturanızı görüntülemek için tıklayın</a></p><p>Bu bağlantıdan aracınıza ait faturaları görüntüleyebilirsiniz.</p><p>sanayirandevu.com</p>',
            'short_code' => ['{invoice_number}', '{client_name}', '{company_name}', '{total_amount}', '{vehicle_link}']];
    }

    public static function template(User $admin): Notification
    {
        $definition = self::definition();
        return Notification::firstOrCreate(['parent_id' => $admin->id, 'module' => self::MODULE], [
            'name' => $definition['name'], 'subject' => $definition['subject'], 'message' => $definition['templete'],
            'short_code' => json_encode($definition['short_code']), 'enabled_email' => 1, 'enabled_sms' => 0, 'sms_message' => '',
        ]);
    }

    public function send(Invoice $invoice): void
    {
        if (!CentralEmail::enabled()) return;
        // Never send a message for a transaction which may still roll back.
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => $this->send($invoice));
            return;
        }
        try {
            DB::transaction(function () use ($invoice) {
                $invoice = Invoice::where('parent_id', $invoice->parent_id)->lockForUpdate()->findOrFail($invoice->id);
                if ($invoice->customer_email_sent_at) return;
                $client = User::where('parent_id', $invoice->parent_id)->find($invoice->client);
                if (!$client) return;
                $recipient = $invoice->customer_details['email'] ?? $client->email;
                if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) return;
                $admin = User::where('type', 'super admin')->orderBy('id')->first();
                if (!$admin) return;
                $template = self::template($admin);
                if (!$template->enabled_email) return;
                $service = Service::where('parent_id', $invoice->parent_id)->where('client', $client->id)->find($invoice->service);
                $code = $service ? VehicleQrCode::where('parent_id', $invoice->parent_id)->where('vehicle_id', $service->vehicle)->first() : null;
                if (!$code || !Vehicle::where('parent_id', $invoice->parent_id)->where('client', $client->id)->whereKey($service->vehicle)->exists()) return;
                $business = $invoice->business_details ?? InvoiceBilling::business($invoice->parent_id);
                $values = ['{invoice_number}' => (settingsById($invoice->parent_id)['invoice_number_prefix'] ?? '#INV') . $invoice->invoice_id,
                    '{client_name}' => $client->name, '{company_name}' => ($business['name'] ?? '') ?: User::find($invoice->parent_id)?->name,
                    '{total_amount}' => number_format($invoice->getInvoiceAllTotalAmount(), 2, ',', '.'), '{vehicle_link}' => $code->publicUrl()];
                $subject = str_replace(["\r", "\n"], '', strtr($template->subject, $values));
                $message = strtr($template->message, array_map(fn ($value) => e($value ?? ''), $values));
                $smtp = app(TenantMailSettings::class)->apply($admin->id);
                $smtp['FROM_NAME'] = 'sanayirandevu.com';
                Mail::to($recipient)->send(new Common(['module' => self::MODULE, 'subject' => $subject,
                    'message' => $message, 'settings' => $smtp + ['company_name' => 'sanayirandevu.com',
                        'brand_logo' => rtrim(config('app.url'), '/') . '/images/brand/sanayirandevu-email-logo.png']]));
                $invoice->customer_email_sent_at = now();
                $invoice->save();
            });
        } catch (\Throwable $exception) {
            Log::warning('Merkezi fatura e-postası gönderilemedi.', ['invoice_id' => $invoice->id,
                'parent_id' => $invoice->parent_id, 'exception_type' => get_class($exception)]);
        }
    }
}
