# Ürün ve servis istatistikleri

Menü: **İstatistikler → Ürün ve servis istatistikleri**. Varsayılan tarih aralığı içinde bulunulan ayın başından bugüne kadardır. Geçen ay, son 90 gün ve bu yıl kısayolları; en fazla 366 gün aralığında özel tarih seçimi vardır.

Ekranda tamamlanan servis sayısı, kullanılan farklı ürün sayısı, en çok kullanılan ürün ve en sık seçilen servis türü gösterilir. Ürünler miktara, servis türleri kaç farklı tamamlanmış işte seçildiklerine göre sıralanır; iki listede ilk 10 sonuç gösterilir.

## Hesaplama

- Ürün miktarı, işletmeye ait güncel fatura ürün satırları + elden satış kayıtlarından gelir. Ürün satırı fatura ile birlikte aynı işletmeye ait olmalıdır. Araçlı/araçsız faturalı satışlar dahildir; `counter_sales` yalnızca elden satış sistemidir ve aynı faturalı satışı tekrar saymaz.
- Tarih, faturanın `invoice_date` alanıdır; eski boş tarihlerde kaydın oluşturulma günü kullanılır. Elden satışta `sale_date` kullanılır. Ödemeler birleştirilmez, dolayısıyla ödeme sayısı ürün miktarını çoğaltmaz ve ödenmemiş fatura da sayılır.
- Aynı stok kimliği birlikte sayılır; aynı isimli farklı ürünler birleştirilmez. Farklı birim kimlikleri ayrı kalır. Litre ve adet toplamı şeklinde yanıltıcı genel miktar oluşturulmaz. Katalogdan silinmiş üründe fatura snapshot'ındaki isim ve birim kullanılır; elden satışın geçmiş birimi artık belirlenemiyorsa açıkça “Birim belirtilmemiş” gösterilir.
- Fatura düzenlemesinde güncel satır miktarı esas alınır. Satır çıkarılırsa ya da fatura silinirse ilgili kullanım rapordan çıkar. Bu bir güncel kayıt raporudur; silinen fatura için stok iade edilmemesi raporu stok hareket defterine dönüştürmez.
- Satın alım, stok düzeltme, şubeler arası transfer ve ödeme hareketleri ürün kullanımı değildir.
- Servis türleri `service_items` üzerinden, `completed` durumundaki işler için hesaplanır. Her servis/tür çifti bir defa sayılır. İki ayrı işte yağ bakımı seçilirse iki bakım sayılır; bir işte aynı türün iki satır olması iki bakım sayılmaz.
- Servislerin kendi `service_date` alanı kullanılır; eski boş tarihlerde oluşturulma günü esas alınır. İşin sonradan tamamlanma günü kullanılmaz. Faturaya ayrıca eklenen işçilik satırı servis türü kullanımına ikinci kez eklenmez. Randevu hizmet kataloğu ile işletmenin servis türü kataloğu birbirinden ayrıdır; randevu talebi tek başına tamamlanmış iş sayılmaz.
- Servis silinirse veya yeniden açılırsa istatistik güncellenir; planlanan, devam eden ve iptal edilen işler dahil değildir.

## Erişim ve performans

Kullanıcıda hem `manage item` hem `manage service report` yetkisi gerekir. Müşteri ve platform yönetici hesabına doğrudan işletme raporu açılmaz. Özel personel rolleri de bu yetkilerle erişebilir. Şube merkezinde seçili şubenin mevcut `_branch` bağlamı korunur; formda da açık hidden alan vardır. İşletme kimliği URL'den alınmaz, `parentId()` kullanılır. Yanıt private/no-store'dur.

İstatistik için yeni kayıt tablosu, olay kaydı veya günlük snapshot oluşturulmaz. Sorgular veritabanında gruplanır, ekrana sadece ilk 10 satır alınır. Başka işletmelerin hatalı bağlanmış fatura/ürün/servis türü satırları isim veya miktar sızdırmaz. Bu ekran gelir/gider, fatura, servis veya stok değiştirmez.

`2026_10_17_000002_add_usage_statistics_indexes` migration'ı mevcut dört tabloya arama indeksleri ekler. Elden satışların mevcut işletme/tarih indeksi kullanılır. Migration mevcut satırları değiştirmez; büyük tablolarda indeks oluşturma için uygun bakım penceresi seçilmelidir.

## Kontrol

- Yeni 12 senaryo: ücretsiz/ödenmemiş fatura, elden satış isteği tekrarı, ödeme bağımsızlığı, miktar azaltma, satır/fatura silme, katalog silme, aynı isimli ürünler, tarih sınırları, eski tarihler, tekrarlanan servis türleri, yeniden açılan işler, işletme izolasyonu, izinler, geçersiz filtreler, şube bağlamı, farklı birimler, ilk 10 sınırı ve HTML kaçışı.
- Stok, finans ve şube testleriyle birlikte 119 test / 1.137 doğrulama başarılı. Özel rol erişimi doğrulaması eklendikten sonra istatistik testleri yeniden çalıştırıldı: 12 test / 67 doğrulama başarılı.
- Gerçek MariaDB'de ONLY_FULL_GROUP_BY ve strict SQL modlarıyla sorgular çalıştırıldı: 5 faturalı + 2 elden = 7 ürün; yinelenen tür satırlarına rağmen 2 tamamlanmış iş = 2 bakım. İndeks migration'ının tekrar uygulanması kayıtları korudu.
- 390 px mobil ve 1.440 px masaüstü görünümünde yatay taşma yok; mobilde listeler tek sütuna geçer. Önizleme sentetik verilerle yapıldı.

Yayın branch'i: `urun-servis-istatistikleri`. Bu branch Laravel 12 yükseltmesini ve önceki test edilmiş değişiklikleri de içerir. Sunucuda en az PHP 8.2 ve intl uzantısı gerekir; Laravel 9'dan geçiliyorsa `docs/laravel-12-upgrade.md` adımları uygulanmalıdır. Mevcut `.env`, APP_KEY ve storage korunmalıdır. Güncel yedek doğrulandıktan sonra Composer kurulumu, `php artisan migrate --force` ve cache yenileme adımları tamamlanmalıdır. Sunucuda `migrate:fresh`, yeniden `key:generate` veya `composer update` kullanılmamalıdır.
