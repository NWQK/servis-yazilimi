# Araç yönlendirme, personel ayrılışı ve fatura işlem özeti

## Araç yönlendirme

Şube yönetimi → Araç yönlendirmeleri ekranından aynı merkezdeki başka bir şubeye talep gönderilir. Şube sahipleri yalnızca kendi şubelerinden, merkez yöneticisi kendi merkezindeki şubelerden işlem yapar. Süper admin grup ekranından erişebilir. Personel bu akışa erişmez.

Plaka ve şasi araç eşleştirmesi için paylaşılır. Müşteri adı, telefon, e-posta, kilometre ve marka/model açıklaması ayrı ayrı seçilir; varsayılan olarak ek bilgiler seçili değildir. Gönderenin ayrıca yazdığı açıklama paylaşılır. Eski servis notları, faturalar, tahsilatlar, belgeler ve fotoğraflar aktarılmaz. Paylaşılan anlık kayıt şifrelenir, listeler özel/no-store döner.

Alıcı mevcut müşteriyi ve aynı şasili aracı açıkça seçer veya yeni yerel müşteri/araç oluşturur. Mevcut kayıtların kişisel bilgileri, plakası ve kilometresi değiştirilmez. Yeni kayıt kapasite sınırına uyar; aynı şasi mevcutsa yeni araç oluşturma reddedilir ve işlemde açılmış yeni müşteri de geri alınır. Gönderen işletmenin marka/model kimlikleri alıcıya kopyalanmaz; paylaşılmış marka/model yalnızca açıklama olarak kullanılır. Alıcı kendi personelini ve isteğe bağlı kendi servis türünü seçer.

Kabul, alıcı şubede yeni servis ve fatura oluşturur. Kaynak kayıtlar olduğu gibi kalır; şubeler arasında ödeme, gider veya stok hareketi oluşturulmaz. Yeni müşteri ve araç için mevcut merkezi bildirim kuralları geçerlidir; iletişim bilgisi paylaşılmadıysa bu kanallardan mesaj gönderilmez.

Aynı kabul isteği ikinci servis oluşturmaz. Kabul/reddetme/iptal yarışında tek sonuç uygulanır. Bekleyen yönlendirme varken ilgili şube merkezden ayrılamaz. İşlem sonuçlandıktan sonra üyelik değişse bile geçmişteki işletme adları anlık kayıttan gösterilir. Hatalı şasi olasılığı nedeniyle alıcıdan ruhsatla doğrulama istenir. Yönlendirme kayıtları işletmelerin geçmiş verilerini tek bir ortak müşteri/araç havuzuna dönüştürmez.

## Personel ayrılışı

Personel listesindeki ayrılış simgesinden açılır. İşletme sahibi kendi personelini, merkez yöneticisi kendi merkezindeki personeli, süper admin gerektiğinde bütün işletmeleri yönetebilir. Sıradan personele bu işlem verilmez.

Tamamlanmamış/iptal edilmemiş servisler, ilgili işletmeden seçilen aktif personel veya işletme sahibine devredilir. Birden fazla şubede açık iş varsa her şube için ayrı seçim gerekir. İşletme sahibi başka şubenin işlerini veya personel listesini görmez; merkez yöneticisi devreder. Yanlış/eksik eşleştirmede bütün işlem geri alınır.

Kullanıcı ve personel kayıtları silinmez. Hesap pasifleştirilir, oturum sürümü artırılır, hatırla belirteci değiştirilir, ek şube erişimleri silinir. Giriş sırasında ve her istekte etkinlik/oturum sürümü kontrol edildiğinden dosya, veritabanı veya başka oturum sürücüsünden bağımsız olarak eski oturumlar kapatılır. Hesap sonradan etkinleştirilse bile eski oturum yeniden kullanılamaz. Ayrılış geçmişi gerçek aktör ve seçilen devirlerle tutulur.

Tamamlanan ve iptal edilen servisler eski personele bağlı kalır. Kapalı servis aynı kapalı durumda düzenlenirken eski sorumlu korunabilir; ayrılan personele yeni/açık iş atanamaz. Hesap silme yerine ayrılış işlemi zorunludur. Kurtarma işlemleri personelin oturum sürümünü ve ayrılış alanlarını geçmişe döndürmez.

İşlem kilit sırası veri kurtarma dosyaları → grup → işletmeler → personel/servislerdir. Personelin devam eden yetkili yazma işlemi tamamlanınca ayrılış güncel açık işleri yeniden okur; bu sırada oluşan yeni iş de devredilir. Şube yapısı değişmişse eski ayrılış isteği reddedilir.

## Fatura işlem özeti

Fatura düzenleme ve tam fatura silme formlarında kaydetmeden önce özet açılır: eski/yeni tutar, kayıtlı ödeme toplamı, stoktan düşecek/stoğa dönecek ürünler ve varsa otomatik gelir düzeltmesi. Silmede seçilen stok iade politikası kullanılır. Gelir düzeltmesi veya fatura silme, gerçek banka/nakit iadesi yapmaz; bu açıklama özette görünür.

Özet ayrı bir mali hesap formülü kullanmaz. Mevcut güncelleme/silme işlemi iç içe bir veritabanı işleminde denenip geri alınır. Mali kayıtlar, stok hareketleri, ödeme düzeltmeleri ve işlem geçmişi önizleme nedeniyle kalıcı değişmez. MySQL otomatik kimliklerinde geri alınan işlemlerden dolayı boşluk olabilir; bu yeni kalıcı kayıt anlamına gelmez.

Onay belirteci veritabanına yazılmaz; aktör, işletme, fatura, sürüm, işlem türü, form ve ilgili stok/vergi/işçilik durumuna bağlanır, 10 dakika geçerlidir. Son onayda kilit altında yeniden kontrol edilir. Form içeriği veya ilgili kayıt değişmişse yeniden özet istenir. İki farklı cihaz aynı sürümü onayladığında yalnızca ilk değişiklik uygulanır. Aynı işlem anahtarıyla ağ tekrarında değişiklik ikinci kez uygulanmaz. Vazgeçmek formu göndermez. Tarayıcı geri dönüşünde önceki onay temizlenir.

Bu özet düzenleme ve tam silme akışını kapsar. Ödeme ekleme ve tek ürün ekleme/silme kısayolları mevcut ayrı işlem/sürüm kontrollerini kullanır.

## Kurulum ve doğrulama — 16 Ekim 2026

```sh
php artisan migrate --force
php artisan optimize:clear
```

Özet varsayılan olarak açıktır; örnek ortam dosyasında `INVOICE_CHANGE_PREVIEW=true` bulunur. Eski testlerin işlem sonuçlarını bağımsız kontrol edebilmesi için genel test ortamında kapalıdır; yeni özet testleri açık ayarla sunucu zorunluluğunu, kötüye kullanımları ve gerçek sonuçları doğrular.

- Genel test paketi: 417 test, 5.252 doğrulama.
- Yeni 16 senaryo: alan bazında paylaşım, işletme sınırı, aynı istek, kabul/ret, kapasite, şasi eşleştirmesi, başarısız kabulde geri alma, ayrılış/devir, geçmişin korunması, eski oturum, eski/yanlış onay ve iki silme politikası.
- Ayrı gerçek MariaDB veritabanı: iki kabul tek servis/araç; yazan personel ile ayrılış yarışında yeni işin devri; iki farklı fatura onayından biri 302, diğeri eski sürüm nedeniyle 422. Son stok 9, ödeme toplamı 50 TL, tek düzeltme.
- 390 ve 1440 pikselde yönlendirme listesi, kabul, ayrılış ve fatura özeti kontrol edildi. Tarayıcıda Vazgeç ve Onayla akışları çalıştırıldı; sayfa veya özet kutusunda yatay taşma görülmedi.

Bu kontroller kapsanan senaryolarda doğrulama sağlar; bütün olası açıklara karşı garanti değildir.
