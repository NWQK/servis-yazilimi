# Araç QR sistemi

## Akış

Her işletmenin (`parent_id`) 10 **atanmamış** QR etiketi vardır. Kodlar 32 bayt kriptografik rastgele değerden üretilir; tahmin edilebilir etiket numarası müşteri erişim anahtarı değildir. Her QR, `APP_URL/q/{token}` adresini taşır.

1. Araçlar → **QR Etiketleri** ekranını açın.
2. Basılmayı bekleyen kodları seçin; **Seçilenleri yazdır / PDF kaydet** ile etiketleri hazırlayın. A4 baskı, %100 ölçek, tarayıcı üst/alt bilgileri kapalı önerilir.
3. Fiziksel baskıyı kontrol ettikten sonra aynı kodları **Basılmış olarak işaretle** ile hazır stoka alın. Yazdırma penceresini açmak bu durumu değiştirmez.
4. **Yeni araç** formunda veya **Yeni müşteri** sihirbazının araç adımında teslim edeceğiniz etiketteki `QR-000001` benzeri numarayı seçin.
5. Kayıt tamamlandığında etiket kalıcı olarak araca bağlanır; boş stok otomatik olarak 10'a tamamlanır. Yeni üretilen etiket basılmayı bekler. Fiziksel etiket stokunu tamamlamak için onu da bastırın.
6. Müşteri QR'ı okuttuğunda şifre girmeden yalnızca bu aracın servislerini ve faturalarını görür. Fatura ayrıntıları, vergi, ödemeler ve kalan tutar görüntülenir; tarayıcıdan yazdırılabilir/PDF kaydedilebilir.

Eski araçlar için **Araç ayrıntıları → Araca QR etiketi ata** kullanılabilir. Atanmış etiket yeniden yazdırıldığında aynı bağlantı korunur. QR başka araca atanamaz. Araç silinirse eski QR erişimi kapanır ve etiket boş stoka geri dönmez.

## Kurulum

Mevcut uygulamanın veritabanı yedeğini alın ve normal Laravel dağıtım prosedürünü uygulayın. Bu değişiklik mevcut araç, servis ve faturaları yeniden oluşturmaz.

```sh
composer install --no-interaction --prefer-dist
php artisan migrate --force
php artisan vehicle-qr:replenish
```

Yeni migration mevcut işletmeler için ilk 10 kodu oluşturur. Yeni işletmelerin havuzu QR ekranı veya kayıt formu ilk açıldığında oluşturulur. `vehicle-qr:replenish` komutu tekrar çalıştırılabilir; mevcut etiketleri değiştirmeden eksik stoku tamamlar.

**Etiketleri bastırmadan önce `APP_URL` gerçek ve kalıcı HTTPS adresi olmalıdır.** Alt dizine kurulu uygulamalarda alt dizini de dahil edin. Yapılandırma önbelleği kullanılıyorsa güncelleyin. Basılmış etiketlerdeki adres değiştirilemez; alan adı değişirse eski adresleri yönlendirmeye devam edin. Localhost ile üretilen etiketler müşterilerin telefonlarında çalışmaz.

QR'lar mevcut `bacon/bacon-qr-code` paketiyle yerel SVG olarak üretilir; dış QR servisine veri gönderilmez. Ek PHP görsel uzantısı veya JS derlemesi gerekmez. Belgelenen yerel çalışma PHP 8.2.12 / Laravel 9.52.14 üzerinde doğrulandı. Veritabanında işlem ve satır kilitlerini destekleyen InnoDB kullanın.

## Erişim ve tutarlılık

- Etiket yönetimi personel oturumu ve `create vehicle` yetkisi gerektirir. Müşteri hesapları bu ekranı kullanamaz. Eski araca atama ayrıca `edit vehicle` gerektirir.
- Yeni müşteri sihirbazı araç da oluşturduğundan hem `create client` hem `create vehicle` gerektirir.
- Atama öncesi işletme kaydı kilitlenir; araç oluşturma, QR atama ve stok tamamlama tek transaction içinde gerçekleşir. Kullanılmış, basılmamış ve başka işletmeye ait etiketler reddedilir.
- Araç başına tek etiket ve benzersiz token veritabanı kısıtlarıyla korunur. Silinen araçlarda `assigned_at` saklanır.
- Müşteri fatura isteği hem işletme hem servis→araç ilişkisi ile doğrulanır. Başka fatura ID'si yazmak erişim sağlamaz.
- QR bağlantısı erişim anahtarıdır; bağlantıyı veya etiketi elinde tutan kişi kayıtları görebilir. Bu sürüm SMS/PIN doğrulaması veya kayıp etiket iptal/değişim ekranı içermez.
- Araç satışında geçmiş servis ve faturalar araçta kalır. Kod, kişiye değil araca bağlıdır.
- Portal salt okunurdur; mevcut yönetici fatura ekranına erişim vermez. Yanıtlar `no-store`, `no-referrer`, `noindex` ve içerik güvenlik politikası kullanır. Dış kaynak veya analiz betiği yüklemez.
- Servis notları müşteriye görünür; iç personel notları için ayrı alan bulunmuyor. Mevcut notlar dağıtımdan önce bu kullanım açısından kontrol edilmelidir.

## Doğrulama

```sh
php vendor/bin/phpunit -c phpunit.qr.xml
```

Testler bellekte SQLite kullanır; repodaki `.env` veritabanına bağlanmaz. Eski `version_1_7_filled` migration'ındaki MySQL'e özel `ALTER ... CHANGE` komutları SQLite testinde atlanır; sihirbazın kullandığı `service_items.tax` sütunu test kurulumunda eklenir. Tam migration zinciri ayrıca yalıtılmış yerel MySQL/MariaDB veritabanında çalıştırıldı.

Kapsam: 10'luk havuz, benzersizlik, tekrar çalıştırma, transaction geri alma, çift atamayı reddetme, basım durumu, iki kayıt akışı, işletme/araç izolasyonu, silinen etiketler, servis/fatura sayfalama, fatura hesapları ve HTML kaçışları. Ayrı MySQL denemesinde aynı etiket için dört eşzamanlı işlem çalıştırıldı: 1 başarı, 3 ret; sonuç 1 araç + 1 atanmış QR + 10 boş QR oldu.

## Mevcut sistem incelemesinde görülen diğer konular

İnceleme; route/middleware yapısını, kullanıcı/yetki modelini, araç ve müşteri kayıt yollarını, servis/fatura/ödeme modellerini, ilgili şablonları, migration'ları ve bağımlılıkları kapsar. Tüm uygulama için penetrasyon testi yapılmadı.

- `.env`, `vendor/` ve derlenmiş `storage/framework/views/` dosyaları Git'te takip ediliyor. `.env` için sır yönetimi ve Git geçmişi incelemesi gerekir; dosyada gerçek sır varsa yalnızca silmek yeterli değildir. Bu değişiklikte dosya içerikleri yayımlanmadı veya değiştirilmedi.
- `InvoiceController::show`, `ServiceController::update` ve `ClientController::getVehicle/getService` gibi mevcut yönetim yollarında kayıt bazlı işletme/yetki denetimleri eksik. Yeni QR portalı bu yolları kullanmıyor. Araç controller'ındaki gösterme/düzenleme/silme erişimleri bu iş kapsamında sınırlandırıldı; diğer modüller ayrıca ele alınmalı.
- Bazı rapor route'larında `auth` middleware yok; controller doğrudan `Auth::user()->can(...)` çağırıyor. Anonim isteklerde hata riski var.
- Mevcut vergi/fiyatlar canlı ilişkilerden hesaplanıyor; vergi oranı veya servis tipi değişince eski faturanın gösterimi değişebilir. Fatura satırlarında tarihsel vergi/fiyat tanımı için ayrı çalışma gerekir.
- Fatura toplamları mevcut uygulamada kayan noktalı alanlarda tutuluyor; bu QR çalışması muhasebe veri modelini değiştirmiyor.
- `vendor/srmklive` Git dışında bırakılmış, fakat autoload dosyaları repoda. Temiz klonda eksik PayPal paketi `composer install` ile tamamlanmalı.

Geliştirme dalı: `feature/vehicle-qr-pool`. Canlı veritabanında migration veya dağıtım yapılmadı.
