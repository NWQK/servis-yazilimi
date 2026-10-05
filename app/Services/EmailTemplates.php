<?php
namespace App\Services;

class EmailTemplates
{
    public static function all(): array
    {
        $templates = legacyEmailTemplateList();
        $content = [
            'user_create'=>['Kullanıcı hesabı', '{company_name} — Hesabınız oluşturuldu', '<p>Merhaba {new_user_name},</p><p>{company_name} hesabınız oluşturuldu.</p><p>Kullanıcı adınız: <strong>{username}</strong></p><p><a href="{app_link}">Hesabınıza giriş yapın</a></p>'],
            'employee_create'=>['Personel kaydı', '{company_name} — Personel kaydınız oluşturuldu', '<p>Merhaba {new_employee_name},</p><p>{company_name} personel kaydınız oluşturuldu. Giriş ve görev bilgileri için işletme yetkilisiyle iletişime geçebilirsiniz.</p>'],
            'client_create'=>['Müşteri kaydı', '{company_name} — Müşteri kaydınız oluşturuldu', '<p>Merhaba {new_client_name},</p><p>{company_name} müşteri kaydınız oluşturuldu. Araç ve fatura işlemleriniz bu kayıt üzerinden takip edilecektir.</p>'],
            'vehicle_create'=>['Araç kaydı', '{company_name} — {license_plate} araç kaydı', '<p>Merhaba {client_name},</p><p>Aracınız {company_name} tarafından kaydedildi.</p><p>Plaka: <strong>{license_plate}</strong><br>Marka / model: {vehicle_type} / {vehicle_brand}</p><p><a href="{vehicle_link}">Aracınıza ait faturaları görüntüleyin</a></p>'],
            'service_create'=>['Servis kaydı', '{company_name} — {license_plate} servis kaydı', '<p>Merhaba {client_name},</p><p><strong>{license_plate}</strong> plakalı aracınız için servis kaydı oluşturuldu.</p><p>Sorularınız için {company_phone_number} numarasından bize ulaşabilirsiniz.</p>'],
            'service_assign'=>['Personel görevlendirme', '{company_name} — Yeni servis göreviniz', '<p>Merhaba {employee_name},</p><p><strong>{license_plate}</strong> plakalı aracın servis işlemleri size atandı.</p><p>Müşteri: {client_name}<br>Telefon: {client_phone_number}</p>'],
            'invoice_create'=>['Fatura oluşturma', '{company_name} — {invoice_number} numaralı fatura', '<p>Merhaba {client_name},</p><p><strong>{invoice_number}</strong> numaralı faturanız oluşturuldu.</p><p>Fatura tarihi: {invoice_date}<br>Toplam tutar: <strong>{total_amount} {company_currency}</strong><br>Ödeme durumu: {status}</p><p><a href="{vehicle_link}">Faturalarınızı görüntüleyin</a></p><p>Faturanızla ilgili sorularınız için bizimle iletişime geçebilirsiniz.</p>'],
            'payment_create'=>['Ödeme alındı', '{company_name} — {invoice_number} ödeme bilgisi', '<p>Merhaba {client_name},</p><p><strong>{invoice_number}</strong> numaralı faturanızın ödeme bilgileri güncellendi.</p><p>Fatura toplamı: {total_amount} {company_currency}<br>Toplam tahsilat: {paid_amount} {company_currency}<br>Kalan tutar: <strong>{due_amount} {company_currency}</strong></p><p><a href="{vehicle_link}">Faturalarınızı görüntüleyin</a></p>'],
        ];
        foreach ($content as $module => [$name, $subject, $message]) {
            $templates[$module]['name']=$name;
            $templates[$module]['subject']=$subject;
            $templates[$module]['templete']=$message . '<p>Saygılarımızla,<br><strong>{company_name}</strong><br>{company_phone_number}<br>{company_email}</p>';
            if (in_array($module, ['vehicle_create','service_create','service_assign','invoice_create','payment_create'])) {
                $templates[$module]['short_code'][]='{vehicle_link}';
            }
        }
        return $templates;
    }
}
