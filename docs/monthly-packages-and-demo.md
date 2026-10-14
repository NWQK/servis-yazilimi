# Aylık paketler ve demo yaşam döngüsü

- Demo: ücretsiz, 50 araç, ilk atamadan itibaren tam 14 gün. Giriş veya tekrar atama süreyi yenilemez.
- 500 / 1.000 / 2.000 / 3.000 araç: aylık 800 / 1.400 / 2.200 / 3.000 TL.
- 3.000 araç paketinde her ek 500 araç aylık 500 TL. Ek blok sayısının üst sınırı yoktur; 3.500 → 4.000 → 4.500 → … olarak artar. Örneğin iki aktif blok: 4.000 araç / aylık 4.000 TL. Onaydan itibaren bir takvim ayı geçerli; otomatik tahsilat yok. Tekrar talep/ödeme/onay ile yenilenir. Süre dolunca araçlar korunur; toplam kapasite doluysa yeni araç eklenemez.
- Demo sona erdiğinde işletme paket ve destek ekranlarına erişebilir, işlem paneline erişemez. Personel üzerinden demo kısıtı aşılamaz. Pazarlama ve mevcut müşteri takip linkleri bu panel kısıtından etkilenmez. Süresi dolan demo işletmesi genel randevu listesinde gösterilmez ve yeni randevu saati açılmaz.
- Demo bitmişse ve son işletme girişinin üzerinden en az 30 gün geçmişse, yalnızca hiç ücretli paket kullanmamış demo işletmesi ve ona bağlı veritabanı kayıtları kalıcı silinir. Faturalar da demo verisi kapsamında silinir. İşletme veya personel girişi süreyi günceller. Önceden ücretli paket kullanımı ve başarılı/pending ücretli ödeme kaydı koruma sağlar.
- Giriş ve silme aynı owner satırını kilitler; temizlemeden hemen önce uygunluk tekrar kontrol edilir.
- Eski demolar güncellemede 14 gün kazanır; 30 günlük bekleme güncelleme tarihinde başlar. Eski onaylı ek kapasiteler bir ay korunur. Önceki ödeme kayıtları değişmez.
- Açık tablo listesiyle tenant verileri silinir; global kataloglar, diğer işletmeler ve platform ayarları korunur. Yüklenmiş dosyalar ve eski yedek arşivleri bu veritabanı temizliğinde silinmez; mevcut yedek saklama politikası geçerlidir.

## Kurulum ve kontrol

Önce yedek alın, ardından `php artisan migrate --force` ve `php artisan optimize:clear` çalıştırın. Sunucuda mevcut her dakikalık `schedule:run` cron kaydı gerekir.

`php artisan subscriptions:purge-demos` yalnızca uygun hesap sayısını gösterir; silmez.

`php artisan subscriptions:purge-demos --execute` kalıcı siler. Zamanlayıcı her gün Europe/Istanbul 04:40'ta bu işlemi çalıştırır. Bu komut test haricinde yerel/canlı veritabanında geliştirme sırasında çalıştırılmamıştır.
