# SMS test sürümü

Bu sürüm İleti Merkezi randevu SMS entegrasyonunu içerir. Kurulum: `composer install`, `php artisan migrate`, `php artisan optimize:clear`.

Para birimi tüm işletmelerde TRY, sembol ₺ olarak sabittir. Eski para birimi tercihleri migration ile güncellenir; tutarlar üzerinde kur dönüşümü yapılmaz. İşletme ve ödeme ayarlarında para birimi girişi yoktur.

Stripe, PayPal, Flutterwave, Razorpay ve Paystack ayarları, ödeme uçları ve istemci kodları kaldırılmıştır. Banka havalesi ve manuel tahsilat devam eder. Geçmiş ödeme kayıtları silinmez. Eski sağlayıcı anahtarları ayarlar tablosundan temizlenir.

İşletme ayarlarındaki SMS Sistemi bölümü şimdilik bilgilendirme alanıdır. Merkezi randevu SMS ayarları yalnızca süper admin tarafından düzenlenir. Ayrıntılar: [Randevu SMS kurulumu](appointment-sms.md).
