# Fatura, stok geçmişi ve eşzamanlı işlem koruması

## Ekranlar

- Ürünler ve stok → Stok hareket geçmişi: mevcut `stock_movements` kayıtları kullanılır. Ürün adı, miktar farkı, işlem sebebi, personel ve ilgili fatura gösterilir. İkinci bir stok hareket tablosu oluşturulmaz.
- Faturalar → Fatura işlem geçmişi: oluşturma, değiştirme, ürün/ödeme ekleme-silme, iskonto, müşteri fatura bilgileri ve silme işlemleri. Yalnızca değişen alanların önceki/sonraki değerleri saklanır; faturanın tamamı her işlemde kopyalanmaz. Boş kaydetme geçmiş veya yeni sürüm üretmez.
- Süper admin → Ayarlar → Sistem sağlığı: son bilinen SMTP/SMS sonucu, zamanlayıcı çalışması, VDS/Drive yedeği gecikmeleri ve başarısız kuyruk işi sayısı. Kabul edildi sonucu alıcıya teslim edildiğini kanıtlamaz. Drive için sadece yeni aktarım saati değil, doğrulanmış kopyanın oluşturulma yaşı da kontrol edilir.

## İşlem korumaları

- Aynı işletmedeki stok/fatura/ödeme işlemleri önce işletme satırını, sonra faturayı kilitler. MySQL InnoDB üzerinde iki cihaz aynı anda işlem yaptığında bu kilit sırası işlemleri sıraya alır. İkinci işlem güncel stok ve borç tutarını kontrol eder.
- Formlar fatura sürümünü taşır. Bir personel değişiklik yaptıktan sonra diğer personelin eski sürümden kaydetme/silme/ödeme isteği engellenir; ekranı yenilemesi istenir. Servis kaynaklı otomatik fatura/ödeme güncellemeleri de sürümü ilerletir.
- Her finansal form kullanıcı/işletmeye bağlı, şifreli ve 7 gün geçerli işlem anahtarı taşır. Başarılı işlemin anahtarı mevcut fatura geçmişi satırında tutulur; ayrı idempotency tablosu açılmaz. Aynı form/payload tekrarı yeni ödeme veya stok hareketi üretmez. Aynı anahtarın farklı bilgilerle kullanılması reddedilir. İşlem anahtarı için işletme bazında benzersiz veritabanı kısıtı vardır; hash tekrar isteğinin içeriğini karşılaştırır.
- İkinci silme isteği stok iadesini tekrar yapmaz; silinmiş faturaya ait eski form yenileme mesajı verir. Başka işletmenin ürün/fatura/ödemesi değiştirilemez.
- Ödeme tutarı kilit alındıktan sonra güncel borçla karşılaştırılır. Bekleyen/reddedilen dekontlar tahsilat/gelir hesabına katılmaz. Eski şemadaki durumu boş ödeme kayıtları mevcut davranış korunarak tahsilat kabul edilir.
- Ödeme ve durum değiştiren eski GET bağlantıları POST formlarına dönüştürülmüştür. Finansal işlem tamamlanmadan müşteri fatura e-postası veya ödeme bildirimi gönderilmez.
- Elden satış ve stok tamamlama akışlarındaki mevcut benzersiz işlem anahtarları korunur. Elden satışın tekrar isteğinde ürün, adet, fiyat ve işlemi yapan kişi de karşılaştırılır.

## Saklama ve boyut

- Stok/fatura ayrıntılı geçmişi 2 yıl; teknik hata özetleri 30 gün. Yeni özellik devreye girmeden önce silinmiş faturalar veya bilinmeyen eski personel bilgileri için geçmiş üretilmez.
- Teknik sağlık durumu bileşen başına tek satırdır. Aynı teknik hata aynı gün bir sayaçta birleştirilir; alıcı, mesaj içeriği, şifre/API bilgileri veya ham sağlayıcı hata yanıtı kaydedilmez.
- Geçmiş listeleri 25 satırlık sayfalarla açılır. İşletme/tarih ve işlem anahtarı indeksleri kullanılır.
- `platform:purge-history` her gece 03.20, 04.20, 05.20 ve 06.20 saatlerinde 500 satırlık gruplarla çalışır. Her tablo için yaklaşık 30 saniyelik çalışma bütçesi kullanır; sabit günlük satır sınırı nedeniyle birikme oluşmasını önler. Büyük eski birikimler birkaç çalışmada aşamalı temizlenebilir; ekranlar her durumda yalnızca saklama süresindeki kayıtları gösterir.
- Temizlik faturaları, ödeme kayıtlarını, giderleri, elden satış gelirlerini ve mevcut stok miktarını silmez. Eski stok hareketi numarası gider/satış kaydında referans olarak kalabilir; bu referans kâr-zarar hesabının kaynağı değildir.
- Form anahtarı 7 günde sona erdiği için iki yıllık geçmiş temizlendikten sonra eski bir form tekrar işleme alınamaz.

## Yayına alma

Bu güncelleme migration içerir. Güncel veritabanı yedeğinden sonra bakım penceresinde kod, migration ve önbellek birlikte güncellenmelidir. Önceden açık fatura formları yenilenmelidir; yeni güvenlik alanları olmayan eski formlar kabul edilmez.

```bash
php artisan down
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

Laravel zamanlayıcısı mevcut cron satırından çalışmaya devam eder. İkinci cron satırı eklemeyin. İlk kontrol için:

```bash
php artisan platform:heartbeat
php artisan schedule:list
```

Zamanlayıcı 5 dakika görünmezse, etkin yedeklerin VDS/Drive kopyası 26 saatten eskiyse sağlık ekranı uyarı gösterir. İlk SMTP/SMS sonucu gerçek bir gönderim yapılınca oluşur. Otomatik testler sağlayıcıları sahteleyerek çalışır; canlı müşterilere test mesajı gönderilmez.

Test kapsamı tekrar gönderim, eski fatura sürümü, farklı personel/işletme, ödeme fazlası, ödeme silme, stok iadesi, geçmiş temizliği ve SMTP hata gizliliğini içerir. Yerel test veritabanı SQLite'tır; gerçek eşzamanlı satır kilitleri için VDS'nin MySQL/InnoDB olması gerekir. Yeni geçmiş olmadan eski kod sürümüne dönmek, yeni korumaları devre dışı bırakır.
