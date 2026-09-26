# İleti Merkezi ile randevu SMS sistemi

## Kurulum
`composer install` ardından `php artisan migrate` ve `php artisan optimize:clear` çalıştırın.
Geçiş migration'ı eski sağlayıcının kimlik bilgilerini temizler, SMS alımını kapatır. Randevular, şablonlar ve geçmiş SMS kayıtları korunur. Eski sütunlar yalnızca migration uyumluluğu için kalır; gönderimde kullanılmaz.

Süper admin → Randevu SMS ayarları: API Anahtarı, panelin verdiği API Hash ve onaylı gönderici başlığını girin, etkinleştirin. Her iki gizli değer APP_KEY ile şifrelenir; boş bırakılması mevcut değeri korur. APP_KEY değiştirilmemelidir. Anahtarları Git'e veya sohbete yazmayın.
İleti Merkezi panelinde API kullanımına izin verilmelidir. IP kısıtlaması varsa sunucunun çıkış IP'si izinli olmalıdır.
APITEST başlığı mesaj metnini değiştirdiği için gerçek OTP akışı bu başlıkla etkinleştirilemez.

## Akış ve sınırlar
Telefon formu +90 sabit önek ve 5 ile başlayan 10 hane kullanır; sağlayıcıya 905XXXXXXXXX gönderilir.
Altı haneli kod beş dakika geçerlidir. Yanlış kod sınırı beş, yeniden gönderme aralığı 60 saniye, talep başına SMS sınırı üçtür. Telefon/IP ve günlük merkezi limitler korunur.
Kod doğrulanana kadar randevu ve esnaf bildirimi oluşmaz. Esnaf onayında tek onay SMS'i gönderilir. JSON send-sms API, bildirim amaçlı iys=0 kullanır; şablonlar reklam için kullanılmamalıdır.

## Hatalar
Yönetim ekranında son 20 doğrulama denemesi, hata kodu ve sipariş numarası görünür. API'nin ham hata metni, anahtarları, telefon veya OTP kodu günlüğe yazılmaz.
Kabul edildi bilgisi operatöre teslim garantisi değildir. 451 tekrar eden sipariş, ağ hatası, geçersiz yanıt veya sunucu hatasında sonuç belirsiz sayılır; otomatik yeniden gönderilmez. Onay mesajlarında yalnızca kesin başarısız/bekleyen kayıtlar yönetici tarafından tekrar denenebilir.
Eski işletme bazlı SMS ve WhatsApp gönderimleri kaldırılmıştır. Yeni merkezi hesap randevu doğrulama ve onay SMS'leri içindir.

## Kaynaklar
- https://www.iletimerkezi.com/docs/api/authentication
- https://www.iletimerkezi.com/docs/api/send-sms

Otomatik testler sahte HTTP yanıtları kullanır; gerçek SMS gönderilmez. Canlı doğrulama için hesap anahtarları ve onaylı başlık gerekir.
