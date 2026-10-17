# Kasa, ürün satışı, tutarlılık ve mesaj kuyruğu

## Kullanım

- Faturalar → Ürün satışı: müşteri ve en az bir ürün seçilir. Araç/servis gerekmez. Vergi, indirim, müşteri/satıcı bilgileri, stok düşümü ve fatura düzenleme/silme mevcut fatura altyapısını kullanır. Fatura oluşturmak tahsilat oluşturmaz; Ödeme al ile tahsilat kaydedilir. Bu akış ayrıca elden satış geliri yazmaz. Araç bağlantısı olmayan ürün faturaları mevcut araç bağlantılı fatura e-postasına dahil değildir; işletme faturayı yazdırabilir.
- Faturada Ödeme al: Nakit, Kart veya Havale / EFT seçilir. Hızlı elden satışta da yöntem seçilir. Eski kayıtlar geriye dönük nakit varsayılmaz. Gider oluşturma/düzenlemede ödeme yöntemi seçilebilir; otomatik stok alış giderleri varsayılan olarak belirtilmemiştir.
- Finans → Gün sonu kasa: tarih, açılış nakdi ve sayılan nakit ile kapanış. Beklenen nakit = açılış + nakit tahsilat − nakit gider. Kart/havale/belirtilmemiş ayrı görünür. Kapanış gelir, gider, banka işlemi veya ödeme oluşturmaz. Otomatik fatura gelir düzeltmeleri fiziksel nakit iadesi sayılmaz; belirtilmemiş olarak görünür.
- Aynı işletme/gün için tek, değiştirilemeyen kapanış vardır. Günlük kayıtlar sonradan değişirse kapanış korunur, ekranda farkın yeniden incelenmesi gerektiği belirtilir. Form açıkken kaynaklar değişirse kapanış reddedilip yenileme istenir. Açılış önerisi önceki kapanıştan gelir; gerçek açılış nakdini personel doğrular. Kasa çekme/yatırma için ayrı bir hareket modülü bu kapsamda yoktur.
- Süper admin → Tutarlılık ve mesaj kuyruğu: işletme başına son kontrol, ilk 100 bulgu, toplam bulgu sayısı ve mesaj durumları. Sorunlar bildirim zilinde de görünür. Bir işletmenin kontrolü kimliğiyle elle başlatılabilir. Personel kasa ekranını gider görüntüleme/kaydetme izinleriyle kullanır; ek şube erişimleri aynı modül ve yazma kısıtlarına uyar.

## Tutarlılık kontrolü

Her gün 03:10 İstanbul saatinde çalışır. Stok ve stok hareketi farkı, negatif/takipsiz stok, başlangıç verisi eksikliği, fazla ödeme, eski fatura durumu, işletmesi uyuşmayan veya faturası kayıp satırlar ve 7 gün bekleyen transferler kontrol edilir. Kaynak kayıtlar otomatik düzeltilmez. Bekleyen transfer ve başlangıç verisi eksikliği inceleme uyarısıdır; mutlaka hata olduğu anlamına gelmez.

Migration, mevcut stok miktarını başlangıç bakiyesi olarak alır; eski alışları tekrar giderleştirmez ve geçmişteki stok miktarının doğruluğunu kanıtlamaz. Yeni hareketlerin başlangıç noktası işlem içinde tutulur. Başarılı kontrolde miktar ve son hareket kimliği güncellenir; uyumsuzlukta bu nokta ilerletilmez. Böylece aynı sorun sonraki kontrolde gizlenmez. Eski hareketler, kontrol noktasına dahil edildikten sonra 2 yıllık temizlikte silinebilir. Ürün silinirse kontrol noktası kaldırılır; faturadan stok iadesiyle yeniden oluşan ürün için yeni başlangıç alınır.

Kontrol sonucu işletme başına tek satırdır; stok başına bir kontrol noktası tutulur. Gün sonu günlük tek satırdır. Kapanış ve stok kontrol noktaları işletme yedek/kurtarma kapsamına dahildir; teknik kontrol sonuçları ve mesaj kuyruğu işletme kurtarmasıyla geri oynatılmaz.

## Gönderim davranışı

Merkezi e-posta ve SMS iş mesajları kalıcı şifreli kuyruğa yazılır. SMTP test butonu doğrudan gönderim yapmaya devam eder. Kaydetme işlemi SMTP/SMS cevabını beklemez. Laravel QUEUE_CONNECTION=sync kalsa bile bu ayrı gönderim kuyruğu zamanlayıcıyla çalışır; ilave Redis zorunlu değildir.

Gönderim sırasında güncel merkezi SMTP/SMS ayarları ve aç/kapat seçenekleri kontrol edilir. SMTP parolası kuyruk içeriğine konmaz. Şifre sıfırlama ve e-posta/SMS doğrulama önceliklidir. İşleyici her mesajdan sonra önceliği yeniden seçer ve yaklaşık 45 saniyelik bütçeyle çalışır. Normal koşullarda zamanlayıcı nedeniyle yaklaşık bir dakikalık gönderim gecikmesi olabilir. Yoğunluk ve sağlayıcı gecikmesi daha uzun beklemeye yol açabilir; kuyruk izlenmelidir.

Doğrulama kodları süresi geçtiğinde veya yeni kodla değiştirildiğinde gönderilmez. İşletme veri kurtarma sürümü değişirse eski bağlantı içeren bekleyen işletme mesajı iptal edilir. Kabul edilen mesaj tekrar gönderilmez. Aynı mesajı iki işleyici aynı anda aldığında yalnızca biri gönderim hakkını alır.

Gönderim öncesi yapılandırma hataları en fazla üç denemeyle, gecikmeli tekrar edilir. Ağ/SMTP sonucu belirsizse kabul edilmiş olabileceğinden otomatik tekrar yapılmaz. Yarıda kalan işleyici kayıtları da belirsiz işaretlenir. Sağlayıcının kabulü son alıcı teslim garantisi değildir. SMTP/SMS altyapısı sağlayıcı düzeyinde idempotency sunmadığından tam teslim garantisi verilemez.

Tamamlanan mesaj içeriği silinir. Teknik sonuçlar 30 gün tutulup 500 satırlık gruplarla temizlenir. Bekleyen mesajlarda alıcı, içerik ve özel bağlantılar uygulama anahtarıyla şifrelenir; APP_KEY korunmalıdır. Bu içerikler süper admin listesine veya ham hata loguna yazılmaz.

## Sunucu kurulumu

Uygulama dizininde:

```sh
php artisan migrate --force
php artisan optimize:clear
php artisan schedule:list
```

Varsayılan açıktır; `.env` içinde açıkça `COMMUNICATIONS_ASYNC=true` kullanılabilir. Değişiklik sonrası config cache yeniden hazırlanır. Var olan Laravel zamanlayıcısı her dakika çalışmalıdır; zaten varsa ikinci cron eklenmez. Gerçek proje diziniyle tek cron örneği:

```cron
* * * * * cd /gercek/proje/dizini && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Elle sınamak için:

```sh
php artisan communications:work --limit=40
php artisan platform:check-integrity --owner=ISLETME_ID
```

İşleyici yeni SMTP/SMS ayarlarını her mesajda okur. Cron çalışmazsa mesajlar kuyrukta kalır; canlıya geçişte Sistem sağlığı ekranından zamanlayıcı ve kuyruk doğrulanmalıdır.

## Doğrulama

Otomatik senaryolar ödeme yöntemlerinin ayrımı, eski kayıtlar, yabancı işletme sınırı, aynı formun tekrarı, eski kapanış formu, kapanış sonrası değişiklik, araçsız fatura düzenleme/stok iadesi, stokta manuel bozulma, yetkisiz kontrol, şifreli kuyruk, işlem geri alma, belirsiz gönderim, kod yenileme/süre sonu, sınırlı deneme ve mesaj önceliğini kapsar.

Canlıdan ayrı MariaDB denemesinde iki eşzamanlı kapanış tek kayıt/beklenen 150 TL/fark 0 verdi. Aynı mesaj için iki eşzamanlı işleyici tek sağlayıcı çağrısı ve tek deneme oluşturdu. 390 ve 1440 pikselde kasa, ürün satışı, ürün faturası düzenleme ve denetim ekranları görüntülendi; yatay sayfa taşması görülmedi.
