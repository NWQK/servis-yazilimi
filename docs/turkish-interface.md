# Türkçe arayüz

Uygulamanın tek arayüz dili Türkçedir (`tr`). Giriş, şifre sıfırlama, müşteri portalı ve yönetim ekranları aynı dili kullanır. Eski hesapların `users.lang` değeri arayüzü değiştirmez. Dil menüsü, dil değiştirme rotası ve yapay zekâ içerik formundaki dil seçimi kaldırılmıştır. Yapay zekâ içerikleri Türkçe istenir.

Çeviriler `resources/lang/tr.json` ve `resources/lang/tr/` altında tutulur. Laravel'in dil dizini uygulama başlangıcında bu konuma sabitlenmiştir. Doğrulama, oturum açma, şifre sıfırlama, sayfalama ve kurulum mesajları Türkçedir. Eski dil dosyaları uyumluluk amacıyla korunur; arayüzden seçilemez.

Tablolar, seçim kutuları, takvim, grafik menüleri, metin düzenleyici ve onay pencereleri yerel Türkçe metinlerle çalışır; dil dosyası indirmek için dış bağlantı gerekmez. Tarihlerde ay adları Türkçedir. Tutarlar `1.234,50 ₺` biçiminde gösterilir; seçili para birimi korunur. Veritabanı tutarları, form değerleri ve muhasebe hesapları değişmez.

Kullanıcıların yazdığı ürün adları, notlar, özel rol adları, bildirim şablonları ve diğer kayıt içerikleri otomatik çevrilmez. Ödeme sağlayıcılarının kendi sayfaları uygulamanın dışında kalır.

## Güncelleme

Bu değişiklik için yeni migration veya seeder gerekmez. Güncel dosyalar alındıktan sonra proje klasöründe:

```bash
php artisan optimize:clear
```

Tarayıcıda Ctrl+F5 ile sayfayı yenileyin. Kurulumunuz yapılandırma önbelleği kullanıyorsa ardından `php artisan config:cache` çalıştırabilirsiniz.

## Kontrol

`php vendor/bin/phpunit -c phpunit.qr.xml` QR, kategori, stok/muhasebe ve Türkçe arayüz testlerini çalıştırır. Türkçe testleri eski dil tercihini, kaldırılmış dil rotasını, varsayılan kullanıcı olmadan açılan misafir ekranını, doğrulama metinlerini ve tarih/tutar gösterimini kapsar.
