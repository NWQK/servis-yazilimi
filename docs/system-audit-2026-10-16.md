# Sistem denetimi — 16 Ekim 2026

## Kapsam ve sınır

Müşteri bağlantıları, esnaf, personel, şube yöneticisi ve süper admin açısından yetki sınırları, eski formlar/oturumlar, tekrar istekleri, stok ve ödeme tutarlılığı, kayıt olma/doğrulama, yükleme kontrolleri ve bağımlılıklar incelendi. Yeni güvenlik senaryoları gerçek roller/izinlerle çalışır; tüm kullanıcılara izin veren bir Gate kısayolu kullanılmaz.

Bu çalışma tüm olası hataların bulunacağına dair garanti değildir. Canlı VDS'ye, gerçek müşteri verilerine ve gerçek SMTP/SMS sağlayıcılarına test isteği gönderilmedi. VDS'nin CPU/RAM, disk dolması, ağ, Nginx/PHP-FPM ve uzun süreli çok kullanıcılı trafik sınırları bu yerel testin sonucu olarak kabul edilmemelidir.

## Bulunan ve düzeltilen senaryolar

| Alan | Önceki risk / hata | Uygulanan koruma |
|---|---|---|
| Özel roller | Rol adının hesap türü olarak kullanılması, eski veya hatalı rol kayıtlarıyla sistem hesap türlerinin atanabilmesi | Müşteri, personel, işletme sahibi, süper admin ve şube yöneticisi adları özel rol oluşturma/yeniden adlandırma ve kullanıcıya atamada korunur |
| Kullanıcı düzenleme | Süper admin kimlik formuna eklenen alanların parola, 2FA, paket veya aktiflik bilgisini de değiştirmesi | Bu form yalnızca ad, e-posta ve telefonu kaydeder; logo/dosya ayrı işlenir |
| Giriş geçmişi | Tahmin edilen kayıt numarasıyla başka işletmenin IP/cihaz kaydının okunması veya silinmesi | Her sorguda işletme kapsamı ve işlem izni kontrol edilir |
| Notlar | Ham kayıt numarasıyla başka işletmenin notuna erişim; işletmenin üst hesabının notlarının da listelenmesi | Liste, okuma, düzenleme ve silme yalnızca ilgili işletmeyle sınırlandırıldı |
| Hizmet türü | Düzenleme ekranında kapsam/izin eksikliği; başka işletmenin vergi numarasını veya bozuk vergi dizisini gönderme | İşletme, izin, vergi sahipliği ve sayısal tutar doğrulanır |
| Ürün | Başka işletmenin ürün birimi veya vergisinin kullanılabilmesi | Formdan gelen birim/vergi kimlikleri işletmeye ait olmak zorunda; bozuk diziler reddedilir |
| Fatura yardımcı uçları | Hizmet numarasıyla başka dükkânın hizmet türlerini sorgulama; ürün yanıtında gereksiz alış maliyeti/snapshot verisi | İşlem izni, işletme ve personel ataması kontrol edilir; yalnızca gereken alanlar döner |
| Personelin servis erişimi | Detay sayfası korunduğu halde düzenleme, silme, günlük liste veya iş panosundan başka personele ait servise erişim | Bu yollar da personel atamasıyla sınırlandırıldı; açıkça verilmiş şube yetkileri ayrı değerlendirilir |
| Personel bilgisi | Düzenlemede mevcut detay kaydının yerine yeni, kullanıcı bağlantısı eksik bir satır oluşturulması | Mevcut personel detay kaydı güncellenir; belge ve tarihsel kimlik korunur |
| Paket değişikliği | Paket atanırken ayrılmış personelin veya arşivlenmiş müşterinin yeniden aktif edilmesi | Bu kayıtlar otomatik aktif etme işleminden hariç tutulur |
| Oturumlar | Aktifliği kapatılan özel rollerin açık oturumu; parola değişiminden/sıfırlamadan önceki oturumlar | Aktiflik ve oturum sürümü her istekte kontrol edilir. Parola değişimi/sıfırlama eski oturumları ve hatırlama anahtarını geçersiz kılar |
| Kayıt olma | Kayıt sayfası kapalıyken POST göndererek hesap açma; kurulum sırasında kısmi kayıtlar | POST da anahtarı kontrol eder; işletme ve başlangıç kayıtları tek işlemde oluşturulur |
| E-posta doğrulama | `SHA-1(e-posta)` ile tahmin edilebilir bağlantı | 256 bit rastgele ve tek kullanımlık bağlantı. Eski tahmin edilebilir bağlantılar kabul edilmez; doğru parolayla giriş denemesinden sonra yeniden gönderme mümkündür |
| E-posta başarısızlığı | Doğrulama maili başarısız olduğunda sadece kullanıcıyı silip başlangıç kayıtlarını yetim bırakma | Hesap doğrulanmamış durumda korunur, giriş açılmaz ve yeniden gönderme sunulur |
| İstek sınırı | Kayıt olma ve şifre sıfırlama uçlarına sınırsız tekrar | Kayıt, sıfırlama ve doğrulama yeniden gönderme uçlarında hız sınırları |
| Ayar formları | Tema/işletme/SEO/footer formuna eklenen farklı anahtarların ayarlara yazılması | Alan listeleri sınırlandırıldı. Merkezi SEO, reCAPTCHA ve footer işlemleri sunucuda da yalnızca süper admine açık |
| Kurulum/güncelleme | Eski tarayıcı kurulum/güncelleme yollarının erişilebilir kalması | Web kurulum ve güncelleme yolları kapalı. Migration yalnızca terminalde açık deployment adımı |
| Kaldırılmış entegrasyonlar | Menüden kaldırılmış OpenAI/n8n uçlarının çağrılabilmesi | Eski uçlar 410 döndürür |
| Misafir rapor erişimi | Giriş yapmadan rapor açınca 500 hatası | Giriş ekranına yönlendirilir |
| Müşteri faturası | Bekleyen/reddedilen ödemelerin müşteri bağlantısında ödenmiş tutara eklenmesi | Yalnızca muhasebeleştirilmiş ödemeler toplanır; negatif düzeltmeler dahil edilir |
| Vergi değişikliği | Kullanılmış verginin oranını değiştirince eski faturanın tutarının da değişmesi; kullanılan verginin silinmesi | Faturada kullanılan oran değiştirilemez; yeni oran için ayrı vergi açılır. Kullanılan vergi silinemez |
| Servis vergisi | Toplu oluşturulan servis satırında seçilen verginin fillable alanlarda olmadığı için kaybolması | Vergi satırda korunur |
| Teklif dönüşümü | Tekrar veya eşzamanlı istekle aynı tekliften birden fazla servis; isteğe bağlı satırlar yokken hata | İşletme/teklif kilidi, dönüşüm durumunun yeniden kontrolü, güncel müşteri/araç/personel kontrolü ve eksik diziler için boş liste |
| İşlem sıralaması | Personel ayrılırken teklif dönüşümü veya servis atama/değişikliğinin araya girmesi | İlgili mutasyonlar aynı işletme kilidiyle sıralanır |
| Paket ödeme onayı | GET ile durum değiştirme; aynı isteğin tekrarında yeniden paket atama; onaydan sonra reddetme | CSRF korumalı POST, işletme/ödeme kilidi ve tek yönlü sonuçlandırma; aynı sonuç tekrarı etkisiz |
| Paket dekontu | Dosya yerine düz metin gönderince hata; geçersiz paket için gereksiz dosya yazma | Yalnızca işletme sahibi, hız sınırı, önce paket doğrulaması, gerçek dosya ve MIME/boyut denetimi |
| Parasal alanlar | MySQL/MariaDB'de eski DOUBLE(8,2) alanlarının 1 milyon üzerindeki tutarı reddetmesi | Mevcut null/default özellikleri korunarak DECIMAL(15,2)'ye genişletme; gerçek veritabanında eski ödemeler korunarak doğrulandı |
| Aşırı tutarlar | Çok büyük adet × fiyat değerinde taşma veya sunucu hatası | Kontrollü 9.999.999.999,99 TL işlem sınırı; kuruş üzerinden çarpım; geçersiz işlem stok/gider hareketini geri alır |
| Fatura önizlemesi | İlgisiz bütün stokların okunması | Yalnızca ilgili ürün kimlikleri ve iade snapshot stok kimlikleri okunur; silinmiş ürünü yeniden oluşturma akışı korunur |
| E-posta başlıkları | Varsayılan e-posta doğrulama kuralındaki CRLF riski | Bütün `email` kuralları, iç içe alanlar dahil, kontrol karakterlerini reddeder; Symfony Mailer/Mime düzeltme sürümleri güncellendi |
| Hata ayrıntıları | Üretimde yanlışlıkla açık debug | Production ortamında debug kapalı tutulur |
| Host ve oturum çerezi | İstek Host başlığının linklere taşınması; HTTPS kurulumunda Secure işaretinin varsayılan olmaması | Üretimde APP_URL alan adı ve www karşılığı kabul edilir; doğrulama linki APP_URL'den oluşturulur; HTTPS APP_URL için oturum çerezi varsayılan olarak Secure olur |

## Testler

- Yeni denetim sınıfı: `tests/Feature/AdversarialSystemAuditTest.php`. Rol/izin atlatma, başka işletmenin kimlikleri, eski oturumlar, tekrar işlemleri, vergi tutarlılığı, e-posta başlıkları, aşırı tutarların geri alınması ve normal akışların devam etmesi test edilir.
- Ürün formu testlerine gerçek işletme ürün birimi eklendi. Eksik test kaydı nedeniyle doğrulamayı gevşetme yapılmadı.
- 10.000 ilgisiz ürünle önizleme senaryosu: sadece ilgili stoğu okuyan iki stok sorgusu, doğru iade özeti ve önizleme sırasında değişmeyen stok kontrol edilir. Bu, gerçek VDS kapasite ölçümü değildir.
- Sentetik verili ayrı MariaDB 10.4 sunucusunda iki eşzamanlı yönlendirme kabulü: tek hedef araç ve tek servis.
- Personel işlem yaparken ayrılış: bekleyen ayrılış yeni açılan işi de görür ve devreder.
- İki cihazdan aynı fatura sürümünü kaydetme: biri başarılı, diğeri eski sürüm nedeniyle reddedilir; tek stok iadesi ve tek ödeme düzeltmesi.
- İki eşzamanlı paket ödeme onayı: tek yönetici işlem kaydı. İki eşzamanlı teklif dönüşümü: tek servis, ikinci istek 409.
- Parasal migration mevcut ödeme toplamını korur; iki kez uygulanması da mevcut tutarları değiştirmez. Önceden 1264 hatası veren 1.000.000,99 TL yeni alanda doğru saklanır.
- Son tam paket: **453 test, 5.495 doğrulama, başarılı**. Güncellenmiş Composer bağımlılıklarıyla çalıştırıldı.

## Bağımlılıklar ve açık kalan konu

Composer kilit dosyası ve yerel test bağımlılıkları güncellendi. Üretim HTTP, dosya, Markdown, tarih ve Symfony bileşenlerinin mevcut ana sürümlerle uyumlu güvenlik düzeltmeleri alındı. Geliştirme bağımlılığındaki Symfony YAML da güncellendi. Sürüm seçimi kararlı sürümlere sınırlandırıldı; geliştirme dalı kilitlenmedi. Çözüm PHP 8.1 tabanıyla yapılır; PHP 8.2 gerektiren geçişli paketlere istemeden geçilmez. QR bağımlılığının da gerektirdiği gerçek alt sınır Composer'da PHP 8.1 olarak belirtilir.

Depo başlangıçta `vendor` altında 8.232 üçüncü taraf dosyasını Git'te takip ediyordu. Dolayısıyla Composer kurulumu uygulama kaynaklarına ek olarak çok sayıda üçüncü taraf dosyasında değişiklik oluşturur. Bunlar elle yazılmış uygulama değişiklikleri değildir; paketler kilit dosyasındaki sürümlerden kurulmuştur. Git takibindeki bağımlılık politikası bu denetimde değiştirilmedi. Kod incelemesinde uygulama değişiklikleri ile Composer/vendor değişiklikleri ayrı değerlendirilmelidir; yayında kilit dosyasından `composer install` çalıştırılmalıdır.

**Laravel 9 için Composer'ın bildirdiği uyarıların tamamı kapanmış değildir.** Son taramada tek paket üzerinde beş bildirim (dört farklı GHSA) kalır: e-posta doğrulama, wildcard dosya doğrulama, local-driver geçici URL ve debug sayfası. Bildirimler kalıcı audit-ignore ayarıyla gizlenmedi. Laravel 9'un son kararlı düzeltme sürümü kullanılır; ilgili korumalar uygulama katmanında güçlendirildi.

- [Laravel'in e-posta doğrulama güvenlik bildirimi](https://github.com/laravel/framework/security/advisories/GHSA-5vg9-5847-vvmq): genel e-posta doğrulayıcı kontrol karakterlerini reddeder ve e-posta taşıma bağımlılıkları günceldir.
- [Wildcard dosya doğrulama bildirimi](https://github.com/laravel/framework/security/advisories/GHSA-78fx-h6xr-vch4): mevcut `SecureUploads` dosyaları alan doğrulamasından ayrı olarak MIME/uzantı/boyut üzerinden denetler; kabul fotoğrafları da görsel olarak yeniden çözülür/kodlanır.
- [Geçici local dosya URL bildirimi](https://github.com/laravel/framework/security/advisories/GHSA-crmm-hgp2-wgrp): uygulamada local-driver temporary URL/upload URL özelliği kullanılmıyor; müşteri dosyaları ayrı yetki kontrollü uçlardan sunuluyor.
- Debug production ortamında kapalıdır.

Bu uygulama korumaları, framework'ün resmi yamasını almakla aynı şey değildir. Kalan uyarıların kalıcı çözümü, uygun PHP ve paket uyumluluğuyla desteklenen ve yamaları içeren Laravel ana sürümüne kontrollü geçiştir. Büyük sürüm geçişi bu denetim sırasında sessizce yapılmadı. `laravelcollective/html` için de bakım/değişim ihtiyacı Composer tarafından bildiriliyor.

## Yayına alma

Bu değişiklik **Composer kurulumu ve migration** içerir. Sadece Git kodunu çekmek yeterli değildir. VDS'deki proje klasöründe, mevcut yedeği doğruladıktan sonra bakım penceresinde:

```bash
php -v
php artisan down
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

Kurulum veya migration başarısızsa hatayı çözmeden `up` çalıştırılmamalıdır. Parasal alanların genişletilmesi büyük tablolarda DDL kilidi gerektirebilir; test veritabanındaki süre gerçek veritabanının süresi değildir. Money migration'ın `down` adımı alanları tekrar daraltmaz; yeni geçerli tutarların kesilmesi önlenir. Veri geri dönüşü gerekiyorsa doğrulanmış yedek planı kullanılmalıdır.

Eski açık formlar yenilenmelidir. Parola değiştiren kullanıcı dışındaki eski oturumlar yeniden giriş yapar. Eski tahmin edilebilir e-posta doğrulama bağlantısını kullanan kişi, doğru parolayla giriş denemesi sonrasında yeni bağlantı gönderebilir. Web `/install` ve `/update` kullanılmaz.

## Sonuç

- Tam test paketi: 453 test / 5.495 doğrulama başarılı.
- Gerçek MariaDB üzerinde eşzamanlı yönlendirme, ayrılış, fatura, paket ödeme ve teklif senaryoları başarılı.
- Parasal alan dönüşümü mevcut ödemeleri korur; 1.000.000,99 TL doğru saklanır.
- PHP kaynak sözdizimi, uygulama diff kontrolü, route cache ve Blade derleme kontrolleri başarılı. Composer dosyası geçerli.
- Son tam Composer taraması (geliştirme paketleri dahil): yalnızca `laravel/framework` üzerinde beş bildirim kalır. Bunlar gizlenmemiştir; resmi framework yamaları için ana sürüm geçişi gerekir.
- Windows test ortamının `is_writable` sonucuyla gerçek dosya yazma sonucu uyuşmadığından paket/servis manifestleri yerel test yardımcı dosyasıyla oluşturuldu. Bu yardımcı dosya uygulamaya veya vendor kaynaklarına eklenmedi; üretim kurulumunda normal Composer/Artisan akışı kullanılmalıdır.

Sıfır güvenlik açığı veya sınırsız yük kapasitesi iddiası yoktur. Canlı veritabanı değiştirilmedi; yayın için yukarıdaki kurulum ve migration adımları gereklidir.
