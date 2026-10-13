# Panelden bakım modu

Süper admin → Ayarlar → Bakım modu ekranından açılıp kapatılır. Varsayılan kapalıdır; ayrıca migration gerektirmez. Açma/kapatma yönetici işlem geçmişine yazılır.

Bakım açıkken:

- İşletme panelleri, randevu işlemleri ve müşteri araç/fatura bağlantıları HTTP 503 bakım ekranı gösterir.
- AJAX/JSON istekleri aynı HTTP 503 durumunu anlaşılır JSON mesajıyla alır. Yanıtlar önbelleğe alınmaz; Retry-After 300 saniyedir ve kesin bitiş saati taahhüdü değildir.
- Tanıtım (`/tanitim`) ve bilgilendirme (`/page/...`) sayfaları açık kalır. Statik logo/CSS/JS dosyaları etkilenmez.
- Giriş, çıkış, şifre sıfırlama ve iki aşamalı doğrulama erişilebilir kalır. Süper admin, normal kimlik doğrulama/2FA adımlarını tamamladıktan sonra paneli kullanmaya ve bakım modunu kapatmaya devam eder.
- Süper admin dışındaki hesaplar ayarı değiştiremez. URL parametresiyle bakım atlanamaz.
- Zamanlayıcı, e-posta/SMS kuyruğu ve yedekleme komutları durmaz. Bu özellik web erişimini yönetir.

Terminalde `php artisan down` ile açılan Laravel bakım modu farklıdır: uygulamayı daha erken aşamada durdurur ve tanıtım sayfası da etkilenir. Veritabanı migration'ı gibi teknik yayına geçişlerde ayrı bakım prosedürü uygulanmalıdır; panel bakım modu kuyrukları veya yönetici işlemlerini durduran bir dağıtım kilidi değildir.
