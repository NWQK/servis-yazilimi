# Herkese açık şasi sorgulama

- `/arac-gecmisi`: herkesin kullanabildiği sorgu sayfası. İşletme hesabı veya SMS doğrulaması gerektirmez. Şasi numarasını bilen herkes paylaşılan özetleri görebilir.
- Araçların `parent_id` sahipliği ve araç tablosunun yapısı değişmez. Ayrı `vehicle_history_index` tablosunda araç/işletme kimliği, normalize şasinin SHA-256 özeti ve paylaşım tercihi tutulur. Araçlar ve müşteri hesapları birleştirilmez.
- Yeni araç ve servisle müşteri kaydında şasi zorunludur. Mevcut boş şasili kayıtlar korunur; geçerli şasi olmadan sorguya dahil edilmez. Boşluk/tire temizliği ve büyük harf dönüşümü yapılır. Eski araçlar için 4–32 alfanümerik karakter kabul edilir; 17 karakterde I/O/Q reddedilir. Bu kontrol, numaranın gerçek araca ait olduğunu doğrulamaz.
- Aynı şasi farklı işletmelerde bulunabilir; aynı işletmede ikinci aktif kayıt engellenir. Yeni araç akışında işletme satır kilidi ve tekrar kontrol ile yarış durumunda müşteri/araç/QR kayıtlarının tamamı geri alınır.
- Paylaşım araç oluşturma/düzenleme formundan veya araç detayındaki ayrı formdan açılır/kapatılır. Varsayılan kapalıdır; eski kayıtlar otomatik yayınlanmaz. Açıldığında geçmiş ve sonraki tamamlanmış servisler görünür. Şasi değişikliği ayrıca onay gerektirir ve paylaşımı kapatır; yeniden açmak için kaydı tekrar düzenlemek gerekir.
- Sonuçta yalnızca işletme adı, servis tarihi, servis türleri ve serviste kayıtlı kilometre seçilir. Fatura, tutar, borç, müşteri, plaka, şasi, adres, telefon, e-posta ve özel not alanları sonuç sorgusuna alınmaz. Servis türü adları işletme tarafından girildiği için bunlara özel not yazılmamalıdır.
- Sorgu POST ile alınır; URL'lere tam şasi eklenmez. Oturumda 15 dakika boyunca yalnızca hash ve son dört hane tutulur. Sonuçlar sayfa başına 20 kayıtla sunucuda sayfalanır. Arama 10/dakika, görüntüleme 60/dakika ile sınırlandırılır. `no-store`, `no-referrer` ve `noindex` başlıkları kullanılır.
- Tamamlanmamış, paylaşılmamış, silinmiş araçlara ait ve işletme sınırları tutarsız servisler görünmez. Paylaşımın kapatılması anında geçerlidir. Silinen araç geri getirildiğinde yeniden paylaşım gerekir. Demo temizliğinde indeks de silinir.
- Yeni servislerde `mileage_at_service` anlık kilometreyi saklar. Eski servislere aracın bugünkü kilometresinden değer uydurulmaz; bilinmiyorsa belirtilmedi gösterilir.
- Uyarı sonuçlarda ve arama ekranında kalıcıdır: işletme girişleri hatalı olabilir; %100 doğruluk veya resmî/eksiksiz geçmiş iddiası yoktur. Uyarı, araç kimliği doğrulaması veya paylaşım izni yerine geçmez.

## Liste boyutları

Araç, servis, müşteri ve fatura ana listelerinde alt sayfalama satırına 30/50/100 seçimi eklendi; başlangıç 30 kayıttır. Arama ve sıralama korunarak DataTables'ın kendi sayfalama API'si kullanılır. Diğer tabloların görünümü değiştirilmez. Bu dört mevcut liste hâlâ tarayıcı tarafında sayfalanır; bu değişiklik sunucudan çekilen toplam satır sayısını azaltmaz.

## Yayına alma

`php artisan migrate --force` gerekir. `.env`, mevcut araç sahipliği, müşteri/fatura kayıtları değişmez.
