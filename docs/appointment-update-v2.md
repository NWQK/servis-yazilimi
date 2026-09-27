# Appointment update v2.0

## Kurulum

Mevcut veritabanını koruyun. `php artisan migrate` ve ardından `php artisan optimize:clear` çalıştırın. Yeni migrationlar randevu iptal/süre dolumu alanlarını ve genel randevu kataloğunu ekler. Composer veya npm kurulumu gerektiren yeni bağımlılık yoktur.

- Genel müşteri sayfası `/randevu`; giriş yapmamış ziyaretçiler ana sayfada da bu ekranı görür. Giriş yapmış kullanıcıların ana sayfası mevcut paneldir.
- Süper admin menüsündeki **Randevu hizmetleri** ekranı hizmet adlarını, desteklenen taşıtları ve aktiflik durumunu yönetir. İlk kurulumda altı hizmet hazır gelir.
- Esnaf **Randevu ayarları** altında il/yaka, adres ve her taşıt için sunduğu hizmetleri seçip genel listede görünürlüğü açar. Randevu alımı da açık olmalıdır. Mevcut işletmeler kendiliğinden yayımlanmaz.
- İstanbul Avrupa ve Anadolu ayrı bölgelerdir. Diğer iller tek seçenektir. İlçe filtresi yoktur.
- Seçimler ayrı sayfalarda kartlarla yapılır. Hizmet ve taşıt bilgisi tarih seçimi, SMS doğrulaması ve randevu kaydı boyunca korunur; işletme hizmeti doğrulama sırasında kaldırmışsa talep oluşturulmaz.
- Eski özel randevu bağlantıları çalışmaya devam eder. Yeni katalog mevcut araç markası/modelini değiştirmez.
- Sonuçlar 12 işletmelik sayfalar hâlinde, işletme adına göre sıralanır. Puanlama yoktur. Hizmet eşleşmeleri indeksli ayrı tabloda tutulur; sonuç sayfası işletme başına sorgu yapmaz.

## Konum

Konum yalnızca kullanıcı düğmeye basıp tarayıcı iznini verince alınır. Tarayıcı koordinatları doğrudan BigDataCloud ücretsiz istemci reverse-geocoding API'sine iletir; uygulama koordinatları kaydetmez. Ekranda bu aktarım açıklanır. Sağlayıcı belgeleri: https://www.bigdatacloud.com/docs/article/why-is-reverse-geocoding-api-free ve https://www.bigdatacloud.com/docs/api-domains . Canlı ortamda HTTPS gerekir. İzin reddi, zaman aşımı, Türkiye dışı veya bilinmeyen konumda manuel il seçimi her zaman kullanılabilir. İstanbul bulunursa yaka müşteriye sorulur.

## Kontroller

`php vendor/bin/phpunit -c phpunit.qr.xml`: 97 test, 1542 assertion. Genel liste filtreleri, görünürlük, rol sınırları, geçersiz seçimler, SMS sonrası hizmet aktarımı ve eski randevu/stok/fatura senaryoları kontrol edildi. İzole MariaDB veritabanında migrationlar çalıştırıldı; tarayıcıda mobil kart akışı ve işletme sayfasına geçiş kontrol edildi. Gerçek SMS gönderilmedi, gerçek konum paylaşılmadı.

Randevu iptali ve yanıt süresi için `docs/appointment-cancellation.md` içindeki zamanlayıcı kurulumunu uygulayın. Daha önce belirlenen fatura/tahsilat açıkları bu sürümde ele alınmadı.
