# İşletme bazında veri kurtarma

Süper admin → İşletme veri kurtarma. Yedekler sayfasından da erişilir.
Mevcut tam veritabanı + yükleme dosyası yedeklerinin VDS veya doğrulanmış
Google Drive kopyaları kullanılabilir. Yeni yedek biçimi gerekmez.

## İki yöntem

- **Silinen kayıtları kurtar:** eksik satırları ekler, silinen araçları ve arşivlenmiş
  müşterileri geri getirir. Mevcut kayıtların güncel alanlarını değiştirmez.
  Eksik mali kayıt varsa stok/finans/vergi farkları veya yedek sonrasındaki yeni
  mali hareketler otomatik birleştirmeyi engeller. Bu durumda ön izlemede nedeni
  gösterilir; mali tutarsızlığı zorla geçiren bir düğme bulunmaz.
- **İşletmeyi yedek tarihine döndür:** yalnızca seçilen işletmenin müşteri,
  araç, servis, ürün, stok hareketi, fatura, ödeme, gider, elden satış, satın alım,
  işletme profili, randevu ve ilgili ticari ayarlarını birlikte geri yükler.
  Yedekten sonraki ticari değişiklikler kaldırılabilir.

İşletme sahibi hesabının kimliği, giriş bilgileri, iki faktörlü doğrulaması,
aboneliği ve kapasitesi korunur. Mevcut personel hesapları, güvenlik bilgileri ve
yetkileri geçmişe döndürülmez. Yedekten geri getirilen eksik personel hesapları
rastgele parola ve devre dışı durumuyla oluşturulur; güncel işletmede aynı isimli
rol varsa atanır. Yönetici gerekirse hesabı ayrıca etkinleştirmelidir.
Merkezi SMTP/SMS, yönetici işlem geçmişi, destek konuşmaları, duyurular ve
kimlik doğrulama günlükleri bu kurtarmanın kapsamı dışındadır.
İşletme sahibi hesabı tamamen silinmişse bu ekranla yeniden oluşturulmaz.

## Uygulama ve güvenlik

1. İşletme, saklanan yedek ve yöntem seçilir.
2. Arka planda özel, disk tabanlı SQLite hazırlık alanında ilgili işletme ayrıştırılır.
   SQL dosyasının CREATE/DROP/USE ifadeleri canlı veya geçici MySQL üzerinde
   çalıştırılmaz. Yalnızca standart mysqldump tablo sütunları ve veri sabitleri okunur.
   Özel INSERT biçimleri/ifadeler ve eksik yedekler güvenli şekilde reddedilir.
3. Ön izleme eklenecek, değiştirilecek, silinecek kayıt ve dosya sayılarını gösterir.
   Kimlik, işletmeler arası ilişki, benzersiz alan ve dosya çakışmaları işlemi engeller.
4. İşletmenin hesap adı, mevcut süper admin şifresi ve açık onay istenir.
   Ön izleme 24 saat geçerlidir; onaydan sonra veri veya dosyalar değişmişse
   uygulama durur ve yeni ön izleme gerekir.
5. Uygulama öncesi işletmenin güncel durumu şifreli kurtarma kopyasına alınır.
   İşletme geçici durdurulur; diğer işletmeler çalışmaya devam eder.
   Veritabanı tek transaction içinde değiştirilir; dosyalar ayrı geri alma günlüğüyle
   korunur. Hata halinde DB geri alınır ve değişen dosyalar eski haline döndürülür.
6. Başarı sonrası eski işletme oturumları yeniden giriş yapmak zorundadır.
   SMS/e-posta yeniden gönderilmez; eski herkese açık şasi paylaşımı otomatik
   yayımlanmaz. Fatura işlem sürümleri yenilenir.

İşlem öncesine dönüş düğmesi **7 gün** boyunca ayrı bir ön izleme ve yeniden onay
ile çalışır. Bu dönüş de işlem sonrası ticari kayıtları değiştirebilir.
Zamanlayıcının sonraki çalışmasında tamamlanan işlemlerin çalışma dosyaları
temizlenir; yalnızca şifreli işlem öncesi kopya 7 gün saklanır.
Süresi dolan ön izlemeler ve iptal edilen işler temizlenir. Artık kurtarma kopyası
ve bağımlı işi bulunmayan kapanmış işlem özetleri 90 gün sonra temizlenir.
Dosya geri alma başarısızsa işletme kilitli ve kayıt “Müdahale gerekli” kalır;
otomatik temizlik bu işlemin kopyalarını silmez. Kopyaları manuel silmeyin.

Bu ek kurtarma kopyaları VDS üzerinde tutulur; rutin tam yedeklerin mevcut
3 günlük + ayın ilk günü aylık VDS/Drive saklama düzeni değişmez.

## Sunucu gereksinimleri

- PHP PDO SQLite (MySQL uygulamasında da hazırlık alanı için gerekir), PDO MySQL,
  AES-256 destekleyen ZipArchive ve mevcut yedek şifresi.
- İlgili MySQL tabloları InnoDB olmalıdır; işlem başlamadan kontrol edilir.
- PHP-FPM ve zamanlayıcı aynı sunucunun aynı özel storage alanını kullanmalıdır;
  işletme yazma koordinasyonu flock ile sağlanır. Çok sunuculu dağıtım için
  dağıtık kilit uyarlaması gerekir.
- Diskte SQL açılımı, hazırlık SQLite dosyaları ve işlem öncesi kopyaya yer bulunmalıdır.
  SQL açılımı öncesinde alan kontrol edilir; yetersizlik halinde işlem durur.
- Drive kopyası için mevcut rclone bağlantısı ve okuma yetkisi gerekir.
- Mevcut Laravel zamanlayıcısı her dakika çalışmalıdır. Ek cron gerekmez:
  `platform:recover` her dakika sıradaki hazırlığı/onaylı uygulamayı işler.
  Manuel çalıştırma: `php artisan platform:recover`.
  Komut kendi başına bir işletmeyi geri yüklemez; sadece panelden sıraya alınmış
  ve uygulama için onaylanmış işleri işler.

Ubuntu'da kurulu PHP sürümüne uygun sqlite3 paketini etkinleştirin
(örneğin PHP 8.3 için php8.3-sqlite3), FPM hizmetini yeniden başlatın.
`php -r "var_export(PDO::getAvailableDrivers());"` çıktısında sqlite görünmelidir.
Ardından normal dağıtım adımlarındaki migrate ve cache yenilemelerini uygulayın.

## Doğrulama sınırı

Yerel testler gerçek uygulama şemasıyla SQLite üzerinde, mysqldump biçimli veriler
ve şifreli ZIP dosyalarıyla yapılır. Canlı VDS veritabanına geri yükleme yapılmaz.
İlk üretim kullanımı öncesinde mevcut MySQL yedeğinin bir kopyasıyla ayrı bir
test ortamında ön izleme, uygulama ve geri alma denenmelidir.
