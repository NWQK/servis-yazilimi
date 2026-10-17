# Şube işlemleri ve doğrulama

## Personel erişimi

Merkez yöneticisi veya süper admin, aynı grubun çalışanına başka bir şube için modül bazında erişim verebilir. Görüntüleme, yazma ve silme ayrıdır. Çalışanın mevcut ana şube rolü değiştirilmez. Ek erişim; müşteri, araç, servis, stok, fatura ve finans modülleriyle sınırlıdır. Platform, rol ve güvenlik ayarları devredilmez. Erişimin kaldırılması veya şubenin ayrılması eski formları geçersiz kılar.

## Transfer ve para dengesi

Transfer bir satın alma veya satış değildir. Gönderim kaynak şubenin kullanılabilir stoğunu azaltır. Teslim alınana kadar miktar bekleyen transferde tutulur; teslim alındığında hedef stok artar. İptal/iade onayı kaynak stoğu bir kez geri ekler. Alış gideri, tahsilat, satış veya ikinci bir gider üretilmez. İlk satın almanın gideri satın alan şubede kalır.

Örnek: A şubesi 1.000 TL maliyetle aldığı ürünü B şubesine aktarır. Merkezin gideri hâlâ 1.000 TL'dir. B müşteriden 1.500 TL tahsil ederse merkezde tahsilat–gider farkı 500 TL olur. Şube bazında maliyet dağıtımı yapılmadığından B'nin gerçek kârının 1.500 TL olduğu sonucu çıkarılamaz. Karşılaştırmalı rapor bu nedenle muhasebesel kâr yerine tahsilat–gider farkını gösterir. Satılan malın maliyetini şubeye dağıtan ayrı bir muhasebe sistemi bu kapsamda yoktur.

Tekrarlanan aynı istek aynı sonucu verir; miktar ikinci kez düşmez/artmaz. Değişmiş içerikle aynı işlem anahtarı reddedilir. Teslim ve iptal yarışında yalnızca bir sonuç uygulanır. Hedef şubedeki ürün/birim o şubeye ait olmalıdır; kaynak kategori, vergi ve tedarikçi kimlikleri taşınmaz. Birimler uyuşmadığında işlem reddedilir; otomatik birim dönüşümü yapılmaz. Bekleyen transfer varken kaynak ürün silinemez ve şube ayrılamaz.

## Rapor ve geçmiş

Rapor mevcut grup üyelerini kapsar. Dönemlik tahsilat ve giderler, güncel ödenmemiş bakiye ile ayrı gösterilir. Onay bekleyen ödemeler tahsilata dahil edilmez. İlişkili fatura kayıtları işletme kimliğiyle ayrıca sınırlandırılır. İşlem geçmişi 180 gün tutulur; ham form, şifre, müşteri iletişim bilgisi ve erişim belirteci kaydedilmez. Geçmiş transferlerde ayrılmış işletmenin güncel bilgisi yerine işlem anındaki ad kullanılır.

## Eşzamanlılık ve kurtarma sınırları

Kilit sırası veri kurtarma dosya kilitleri, grup satırı, işletme ve stok satırlarıdır. İki işletme kimlik sırasıyla kilitlenir. MySQL/MariaDB şube işlemlerinde yalnızca ilgili işlem için READ COMMITTED kullanılır; oturumun kalıcı izolasyon ayarı değiştirilmez. Böylece kilit bekleyen tekrar istek, önceki işlemin yeni kaydını görebilir. İşlem anahtarı aramaları ayrıca kilitli okunur.

Transfer geçmişi bulunan işletmelerde tek işletme kurtarması, diğer işletmeyle stok tutarlılığı bozulmasın diye engellenir. Bu kural iptal edilmiş transfer geçmişinde de geçerlidir. Grup tutarlılığı sağlanan tam yedek kurtarması gerekir. Dosya kilitleri ortak yerel dosya sistemi varsayar; birden fazla uygulama sunucusuna geçişte dağıtık kurtarma kilidi ayrıca tasarlanmalıdır.

## Denemeler — 15 Ekim 2026

- Tam otomatik paket: 381 test, 4.968 doğrulama.
- Gerçek MariaDB, canlıdan ayrı sentetik veri: işlem sürerken ayırma bekler; eski bağlam reddedilir; iki gruba eşzamanlı bağlama tek üyelik bırakır.
- Stok yarışları: yetersiz stokta ikinci gönderim reddedilir; aynı gönderim/teslim bir kez uygulanır; teslim/iptal yarışında tek sonuç; ters yönlü transferde stok korunur.
- İki eşzamanlı elden satış/ödeme isteği: tek satış, tek ödeme, tek ödeme olayı; stok 20'den 16'ya, ödeme toplamı 100.
- Yetki, başka işletme kimliği, eski form, birim uyuşmazlığı, işlem ortasında hata/geri alma, rapor izolasyonu ve geçmişte veri sızıntısı senaryoları otomatik testlerde denendi.
- Yedi yeni ekran 390 ve 1440 pikselde yatay taşma olmadan görüntülendi.

Bu kontroller test edilen senaryolara ilişkin kanıttır; tüm olası güvenlik açıklarının bulunmadığına dair garanti değildir.
