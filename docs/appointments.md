# Randevu sistemi

Güncellemeden sonra mevcut veritabanında çalıştırın:

```sh
php artisan migrate
php artisan optimize:clear
```

Veritabanını yeniden oluşturmanız gerekmez. İki yeni tablo eklenir; mevcut servis ve faturalar değişmez.

## Kullanım

- İşletme sahibi menüde **Randevu ayarları** sayfasını açar, işletme adını girer ve talep almayı etkinleştirir.
- Haftanın her günü için 24 saatlik tabloda yeşil seçilen kutular randevuya açılır. Bir kutu bir saatlik, tek randevuluk kapasitedir. Seçim yapılmayan gün kapalıdır. Zaman dilimi İstanbul'dur.
- Sayfadaki işletmeye özel bağlantı müşterilerle paylaşılır. Müşteri üye olmadan bugünden itibaren en fazla 7 gün sonrası (son gün dahil) için uygun tarih ve saat seçip adını ve telefonunu yazar; plaka ve not isteğe bağlıdır. Daha önce alınmış ileri tarihli randevular korunur.
- Bekleyen talepler de saati ayırır. İşletme sahibi **Randevular** sayfasında talepleri onaylar veya reddeder; onaylanmış randevuyu iptal edebilir, saati geldikten sonra tamamlandı veya müşteri gelmedi olarak işaretleyebilir. Tarih ve durum filtreleri vardır.
- Ret veya iptal saati yeniden açar. Çalışma saatlerini değiştirmek mevcut randevuları iptal etmez. Bekleyen talepler otomatik sona ermez; işletme tarafından yönetilir.
- Müşteri, talep sonunda verilen kişisel takip bağlantısından onay durumunu kontrol eder. Şu anda Telefon SMS koduyla doğrulanır; işletme onayından sonra onay SMS’i gönderilir. Kurulum için [SMS belgesine](appointment-sms.md) bakın.

## Bağlantılar ve gelecek geliştirmeler

Üst menüde ayarlar ikonunun yanındaki zil, işletmenin onay bekleyen randevu taleplerini gösterir. Sayı rozeti bekleyen taleplerin toplamıdır; açılır kutuda en yeni 10 talep listelenir. Talebe tıklamak ilgili günün bekleyen randevularını açar. Panel görünürken 10 saniyede bir, sekmeye dönünce ve zile tıklayınca yenilenir. Kutuyu açmak talepleri silmez veya onaylamaz; onaylanan, reddedilen veya iptal edilen talepler listeden çıkar. Bildirim zili masaüstü bildirimi göndermez; SMS akışı ayrıca merkezi ayarlardan yönetilir.

İşletme bağlantısı `/randevu/{UUID}` biçimindedir. Rastgele kimlik veritabanında benzersizdir; işletme adı değişince bağlantı değişmez. Müşteri takip bağlantısında ayrıca tahmin edilmesi güç 64 karakterlik bir anahtar bulunur. Takip sayfası telefon, ad veya müşteri notunu göstermez.

Paylaşılacak bağlantının doğru alan adını kullanması için `.env` içindeki `APP_URL` gerçek uygulama adresi olmalıdır. Alan adı değişirse bağlantıların eski alan adından yönlendirilmesi gerekir.

İşletmeler yalnızca kendi randevularına erişebilir. Çakışan talepler işlem kilidi ve benzersiz veritabanı indeksiyle korunur. Aynı formun tekrar gönderilmesi aynı talebe döner. Geçici spam önlemi olarak gizli tuzak alan ve IP başına 10 dakikada 5 gönderim sınırı vardır; SMS kod kontrolü ve telefon/IP gönderim sınırları da uygulanır.

Randevu profilleri ve talepleri servis/fatura kayıtlarından ayrıdır. Bu aşamada randevu otomatik servis, fatura veya gelir oluşturmaz. `phone_verified_at` SMS kodunun doğrulandığı zamanı tutar. Merkezi SMS akışı eklenmiştir. İşletme/hizmet kategorileri, puanlama ve genel arama sayfası henüz uygulanmamıştır.
