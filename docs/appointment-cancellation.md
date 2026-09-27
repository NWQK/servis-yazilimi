# Randevu iptali ve yanıt süresi

Güncellemeden sonra `php artisan migrate` çalıştırın. Mevcut veritabanını silmeyin.

Randevu ayarlarında iki yeni seçenek bulunur:

- Müşterinin iptal süresi: varsayılan olarak randevudan 2 saat öncesine kadar. 0 seçilirse randevu başlangıcına kadar iptal edilebilir.
- İşletmenin yanıt süresi: varsayılan 12 saat. Randevu daha erken başlayacaksa son yanıt zamanı randevunun başlangıcıdır.

Bu süreler her yeni talebe kaydedilir. Ayarları değiştirmek mevcut taleplerin sürelerini değiştirmez. İlk migration eski kayıtların sürelerini oluşturulma ve başlangıç zamanlarından hesaplar; eski bekleyen talepler sonraki kontrolde kapanabilir. Onaylanmış kayıtlar otomatik kapanmaz.

Müşteri kişisel takip bağlantısından bekleyen veya onaylanan randevusunu izin verilen süre içinde iptal edebilir. İptal edilen saat tekrar açılır. İşletmenin bildirim kutusunda iptal görünür; ilgili kayıt randevu listesinde görüntülendiğinde bildirim okundu sayılır. İptal işlemi yeni SMS göndermez ve fatura/ödeme kaydı oluşturmaz.

Süresi dolan bekleyen talepler “Yanıt süresi doldu” durumuna geçer ve saatleri boşalır. Bu kayıtlar tekrar onaylanamaz; yeni talep gerekir.

## Zamanlanmış işlem

Canlı sunucuda Laravel zamanlayıcısını dakikada bir `php artisan schedule:run` çalıştıracak şekilde kurun. XAMPP ile yerel testte ayrı terminalde `php artisan schedule:work` çalıştırılabilir. Bu işlem ziyaretçi olmasa da süresi dolan talepleri kapatır.

Tek seferlik kontrol: `php artisan appointments:expire`.

Zamanlayıcı çalışmasa da uygun saatler, randevu listesi, bildirimler, takip sayfası ve ilgili işlemler açıldığında süre kontrol edilir. Dolmuş talepler boş saatleri engellemez.
