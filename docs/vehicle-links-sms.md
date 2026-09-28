# Araç takip bağlantıları ve SMS seçenekleri

Güncellemeden sonra `php artisan migrate` ve `php artisan optimize:clear` çalıştırın. Veritabanını silmeyin.

## Araç kaydı

Hem yeni müşteri sihirbazında hem ayrı araç ekleme formunda QR etiketi isteğe bağlıdır. Etiket seçilirse onun mevcut adresi kullanılır. Boşsa tahmin edilemeyen 64 karakterlik token ile aynı müşteri portalına ait kalıcı bağlantı oluşturulur. Dijital bağlantı hazır etiket havuzundan tüketmez; boş QR sayısı 10 olarak korunur. Araç detayındaki **QR olarak yazdır / PDF** düğmesi aynı bağlantıyı basılabilir QR'a dönüştürür; adres değişmez.

Müşteri portalının mevcut fatura odaklı görünümü korunmuştur. Fatura içindeki hizmet/ürün detayları görüntülenebilir. Önceden kaldırılan ayrı servis geçmişi yeniden açılmamıştır.

## Merkezi SMS

Süper adminin mevcut SMS ayarlarında:

- Merkezi SMS gönderimi: sağlayıcı hesabını açıp kapatır.
- Randevuda SMS doğrulaması zorunlu olsun: kapatılırsa müşteri doğrulama kodu beklemeden talep oluşturur. Sağlayıcı hesabı kapalıyken de bu akış kullanılabilir. Esnaf bildirimleri, saat rezervasyonu, iptal ve süre dolumu çalışmaya devam eder.
- Araç kaydında takip bağlantısını gönder: yeni araç sahibi için mesaj oluşturur. `{isletme}`, `{link}`, `{plaka}`, `{marka}` değişkenleri kullanılabilir. `{link}` zorunludur.

Doğrulamasız randevular esnaf listesinde belirtilir; doğrulanmış telefon olarak işaretlenmez. Merkezi SMS hazırsa bu randevular işletme tarafından onaylandığında onay SMS'i gönderilebilir. SMS kapalıysa durum takip sayfasından izlenir.

Araç kaydı commit edildikten sonra SMS denenir. Aynı araç için mükerrer gönderim yapılmaz. Sağlayıcı hatası araç kaydını geri almaz; son araç SMS sonuçları süper admin panelinde bulunur. Belirsiz gönderimler otomatik tekrarlanmaz. API kabulü teslim garantisi değildir.

Mesajdaki adres `APP_URL` üzerinden oluşur. Müşterinin telefonundan erişmesi için gerçek HTTPS site adresi kullanılmalıdır; localhost adresi yalnızca yerel test içindir.

## Telefon alanları

Müşteri, kullanıcı, çalışan, hesap ve işletme formlarında +90 sabittir; başında 0 olmadan 10 hane girilir. Kişisel telefonlar 5 ile başlar. İşletme alanı sabit hattı da kabul eder. Eski kayıtların +90/0 ve boşluk içeren numaraları formda sıfırsız gösterilir; SMS gönderiminde geçerli cep numarası +90 biçimine çevrilir. Geçersiz/eski numara için SMS denenmez.

106 otomatik test geçti. Gerçek SMS gönderilmedi; sağlayıcı yanıtları testlerde taklit edildi.
