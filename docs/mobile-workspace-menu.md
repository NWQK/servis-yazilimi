# Mobil işletme menüsü — 19 Ekim 2026

Branch: feature/mobile-workspace-menu. Mobil menü native Flutter Drawer ve
ExpansionTile kullanır; alt gezinme korunur. WebView veya web sayfası açılmaz.

`GET /api/v1/workspace/menu` sabit kayıt defterinden kullanıcının izinli modüllerini
döndürür: id, label, group, kind. Flutter bilmediği kimlik/türleri açmaz; sunucudan
gelen URL, tablo veya kod çalıştırmaz. Eski mobil istemcilerin uçları değişmez.
Menü yükleme hatası mevcut müşteri/araç/servis/oturum akışını engellemez.

## Gerçek API ile eklenen alanlar

- Faturalar: liste/numara arama, detay, sunucuda hesaplanan toplam ve kalan borç,
  fatura sürümü, ilk 100 ürün/servis/ödeme satırı. Liste `manage invoice`, detay
  ayrıca `show invoice` ister. Fatura oluşturma/düzenleme/tahsilat bu kapsamda yok.
- Teklifler: liste/numara arama/detay/toplam/satırlar; oluşturma ve dönüşüm yok.
- Ürünler ve stok: ürün, tedarikçi, satın alım geçmişi, stok hareket geçmişi,
  kategori ve birim listeleri/detayları. Satın alımda webdeki iki yıllık saklama
  filtresi korunur. Yeni stok hareketi/satış/ödeme oluşturulmaz.
- Tanımlar: servis türleri, araç markaları/modelleri, vergi oranları.
- Gider kayıtları ve personel listesi/detayı. Ürün ve personel detayında webdeki
  ayrı `show item` / `show employee` izni de aranır. Personel yanıtında parola,
  2FA sırrı, kurtarma kodu veya token bulunmaz.
- Raporlar: tarih filtreli ürün/servis ilk 10 istatistiği doğrudan UsageStatistics;
  günlük kanal bazlı kasa doğrudan CashRegister. Dart hesaplama yapmaz. Kasa
  kapatma, gelir/gider/kâr-zararın diğer rapor ekranları bu kapsamda yok.
- Ayarlar: InvoiceBilling alanlarıyla işletme fatura bilgileri görüntüleme ve
  kaydetme, hesap bilgileri, kendi cihaz oturumlarını listeleme ve diğer cihazı iptal.

## API sözleşmesi

`GET workspace/catalog/{module}?q=...&page=1&per_page=20` ve
`GET workspace/catalog/{module}/{id}` sabit CATALOGS listesiyle sınırlıdır.
Listeler en fazla 50 kayıt, ilk üç özet alan ve can_open döndürür. Yanıtlar
private/no-store'dur. Her sorgu MobileSession'ın belirlediği işletmeye sınırlı;
arama OR koşulları bu kapsamın dışına çıkamaz. Yalnız okuma uçları vardır.
Başka işletme detayı 404, izinsiz modül 403. Detayda gerekli ekstra izin sunucuda
tekrar kontrol edilir; menü gizlemek güvenlik kontrolü sayılmaz.

`GET workspace/reports/usage?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD`
en fazla 366 günlük aralık ister. `GET workspace/reports/cash?date=YYYY-MM-DD`.
Nakit/kart/havale/belirtilmemiş ayrıdır; farklı ürün birimleri toplanmaz.
CashRegister eski ödeme-status kolonu bulunmayan test/legacy şemada,
InvoicePayment::accounted ile aynı eski kayıt kabulünü kullanacak şekilde korundu.
Güncel şemada başarısız/bekleyen ödeme filtresi değişmez.

`GET/PUT workspace/business-settings`: manage company settings gerekir.
PUT gövdesi billing ve 64 karakter expected_version içerir. InvoiceBilling'in
ortak doğrulama/alan listesi kullanılır; SMTP, APP_KEY vb. alınmaz/döndürülmez.
Mevcut profil satırı kilitlenir; değişmiş sürüm 409 verir. Aynı değerlerle tekrar
istek yeni kayıt veya yan etki oluşturmaz. Kurtarma/işletme yazma kilitleri
MobileSession üzerinden uygulanır. Eski faturaların işletme snapshot'ı değişmez.
Flutter belirsiz yanıtta formu sabitler, yalnız aynı payload ile kullanıcı
kontrollü tekrar veya güncel kayıt yükleme sunar; otomatik çevrimdışı kuyruk yoktur.

`GET workspace/account` yalnız mevcut hesabın güvenli alanlarını gösterir.
Mevcut `GET devices` yanıtına is_current eklendi. Cihaz ekranı mevcut oturuma
silme sunmaz; diğer oturumlar onay diyaloğundan sonra mevcut DELETE devices/{id}
ucuyla iptal edilir. Cihaz tokenlarının sunucu tarafındaki FK temizliği korunur.

## Yerel doğrulama

Yeni izole worktree'de tools/mobile-local.php seed ile DB yoksa oluşturulur;
seed-workspace mevcut sentetik DB'yi silmeden demo izinleri ve ortak
InventoryAccounting servisinden stok/fatura verisi ekler. Tekrarlanabilir.
Gerçek e-posta/SMS/push ve dış HTTP kapalıdır. Canlı VDS'te bu yardımcı çalıştırılmaz.

Henüz tüm web eşdeğerliği yok: servis/fatura/teklif oluşturma ve düzenleme,
tahsilat, stok yazmaları, kasa kapatma, borç takibinin tüm filtreleri, randevu
çalışma saatleri, şube geçişleri, destek, değerlendirmeler, duyurular, abonelik,
personel/rol yönetimi, QR etiket ve webdeki diğer yönetim formları ayrı işlerdir.
Platform yöneticisi bakım/yedek/kurtarma ekranları esnaf menüsüne açılmadı.
Performans ölçümü, MySQL eşzamanlılık ve yeni APK cihaz testi yapılmadı.
