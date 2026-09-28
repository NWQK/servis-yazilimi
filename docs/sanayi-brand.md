# Sanayi Randevu tanıtım sayfası

- Genel randevu akışı `/` ve `/randevu` adreslerinde devam eder.
- Tanıtım sayfası `/tanitim` adresindedir. Mevcut `landing_page` ayarı açıkken `/home` üzerinden gelen ziyaretçilere de bu görünüm sunulur. Oturum açmış kullanıcıların panel yönlendirmesi korunur.
- Randevu arama sayfasının alt kısmından tanıtıma ulaşılır.
- İçerik: `resources/views/marketing/landing.blade.php`.
- Stil ve mobil menü: `public/css/sanayi-landing.css`, `public/js/sanayi-landing.js`.
- Logolar: `public/images/brand/sanayirandevu-logo.svg`, `sanayirandevu-logo-light.svg`, `sanayirandevu-mark.svg`.
- Renkler: kırmızı `#E32228`, siyaha yakın `#18191C`, beyaz `#FFFFFF`.
- Logo vektördür. Mockuplar HTML/CSS ile çizilmiştir; gerçek müşteri veya işletme verisi içermez. QR çizimi temsilidir.
- Sayfa harici yazı tipi, görsel servisi veya JavaScript kütüphanesi yüklemez.
- Yeni tanıtım içeriği Blade dosyasından düzenlenir; eski ana sayfa içerik editörünün alanları bu görünümü yönetmez.
- Paket sayfası ve fiyatlandırma bu değişikliğin kapsamında değildir.
- Veritabanı migration işlemi gerektirmez.

## Müşteri ekranları

- Genel servis araması, işletme randevu formu, SMS doğrulama ve randevu takip ekranı aynı marka başlığını ve kırmızı/siyah/beyaz paleti kullanır.
- Araç QR/kalıcı bağlantı ekranı, boş QR ekranı ve fatura detayı aynı temadadır.
- Paylaşılan başlık `resources/views/components/public-brand.blade.php`, müşteri teması `public/css/sanayi-customer.css` dosyasındadır.
- İşletme panelinin takvim ve açık saat renkleri etkilenmez.
- Mobilde fatura kalemleri etiketli kartlar halinde görünür; yazdırmada tablo düzeni korunur.
- Yetkilendirme, QR bağlantıları, randevu ve SMS işleyişi değişmez.
