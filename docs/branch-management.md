# Şube merkezleri

## Kullanım

- Süper admin → Şube yönetimi → Şube merkezlerini yönet.
- Grup adı, merkez yöneticisinin adı, ayrı e-postası ve en az 12 karakterli şifreyle merkez hesabı oluşturulur. Bu hesap demo veya ücretli işletme paketi oluşturmaz.
- Mevcut bağımsız işletmeler seçilip onayla merkeze bağlanır. Yeni bir şube için önce normal işletme hesabı açılır, sonra merkeze bağlanır.
- Merkez yöneticisi kendi hesabıyla giriş yapınca Şubelerim ekranını görür. Bir şube açılarak müşteri, araç, servis, stok, fatura, randevu ve işletme ayarları yönetilebilir.
- Grup toplamları müşteri **kayıtlarını** sayar; farklı işletmelerdeki aynı kişi tek bir müşteri olarak birleştirilmez.
- Şube sahipleri ve personeli mevcut hesaplarıyla çalışmaya devam eder. Personel otomatik olarak kardeş şubelere erişim kazanmaz.
- Paket, kapasite ve abonelikler ayrı kalır. Paket satın alma/değiştirme şubenin kendi hesabından yapılır; merkez hesabı ödeme sağlayıcılarına veya platform yönetimine erişmez.
- Süper admin bir şubeyi ayırabilir. Bu işlem veri silmez, kopyalamaz veya taşımaz. Merkez erişimini askıya almak da şubelerin kendi hesaplarını kapatmaz.

## Veri sınırları

Mevcut tablolardaki işletme kimlikleri (parent_id / owner_id), araç/müşteri/fatura kimlikleri, QR bağlantıları, şifreler ve abonelikler değiştirilmez. Grup üyelikleri, ek personel erişimleri, işlem geçmişi ve stok transferleri ayrı tablolarda tutulur. Stok yalnızca açıkça onaylanan stok işlemleriyle değişir.

Her merkez isteğinde açıkça şube bağlamı taşınır; oturumdaki ortak bir “son seçilen şube” kullanılmaz. Böylece ayrı sekmelerdeki formlar kendi şubesinde kalır. Bağlam; giriş yapan kişi, grup sürümü, işletme ve veri kurtarma sürümüne bağlıdır. Bağlama/ayırma/askıya alma sonrası eski formlar 409 ile reddedilir; merkezden yeniden şube açılır.

İşlem kilit sırası: işletmenin veri kurtarma dosya kilidi → grubun veritabanı kilidi → mevcut işletme/finans kilitleri. Merkez isteği grubu paylaşımlı kilitle okur; bağlama/ayırma işlemi aynı grubu özel kilitler. Devam eden yetkili işlem tamamlandıktan sonra bağlantı kaldırılır; eski bağlamla yeni işlem başlatılamaz. Veritabanında owner_id tekildir; bir işletme aynı anda iki merkeze bağlanamaz.

İşlemi yapan gerçek kullanıcı korunur. Fatura ve satış geçmişi merkez yöneticisinin kimliğini kaydeder; şube sahibinin hesabı taklit edilmez.

Şubeye bağlı hesaplar yanlışlıkla normal hesap silme ekranından silinemez ve bağlı ücretsiz demolar otomatik terk edilmiş demo temizliğine alınmaz. Şubeyi ayırmak, normal demo süresi/temizlik kurallarını kaldırmaz.

İşletme veri kurtarma işlemleri grup üyeliklerini içermez. Kurtarma sonrası eski merkez formları geçersizleşir; diğer şubelerin kayıtlarına veya grup bağlantılarına dokunulmaz. Stok transferi geçmişi olan işletmelerde tek işletme kurtarması engellenir; stokların diğer şubeyle tutarlı biçimde kurtarılması gerekir.

## Kurulum / doğrulama

Yeni migration çalıştırılır: `php artisan migrate --force`. Veri taşınması ya da seed gerekmez.

Test: `php -d extension=gd vendor/bin/phpunit -c phpunit.qr.xml`.

Şube testleri; yetkisiz merkez oluşturma, normal kayıtta merkez türü gönderme, iki sekme, başka şubenin kaydı, ayrılıp yeniden bağlanma, eski admin formları, abonelik askısı, veri kurtarma sürümü, müşteri bağlantıları, gerçek kullanıcıyla ödeme geçmişi ve bağlama/ayırmada veri korunması senaryolarını kapsar.

15 Ekim 2026 doğrulaması: SQLite üzerinde 381 test / 4.968 doğrulama geçti. Ayrı bir sentetik MariaDB veritabanında eşzamanlı bağlama/ayırma, stok transferi, teslim/iptal, elden satış ve ödeme denemeleri de tamamlandı. 390 ve 1440 piksel ekranlarda yeni sayfalarda yatay taşma görülmedi. PHP sözdizimi ve route cache kontrolleri geçti. Kapsam ve sınırlar için [şube işlemleri denetim notlarına](branch-operations-security.md) bakın.
