# Harici işçilik

Servis oluşturma/düzenleme ve müşteri kayıt sihirbazındaki **Harici işçilik tutarı** isteğe bağlıdır. Boş veya sıfır girildiğinde ek ücret oluşmaz. Tutar en fazla iki ondalık basamakla girilir; negatif değer kabul edilmez. Girilen tutar faturaya aynen eklenir; bu alan için ayrıca vergi hesaplanmaz.

Servisin faturası oluşturulurken tutar otomatik aktarılır. Yeni fatura ekranında servis seçilince tutar gösterilir; değiştirmek için servis düzenlenir. Serviste bu tutar değiştirildiğinde bağlı faturalar güncellenir. Servis/fatura görüntüsü, yazdırılan fatura ve müşterinin QR portalı tutarı gösterir.

İşçilik eklemek ödeme ya da gider oluşturmaz. Gelir yalnızca alınan ödemelerden hesaplanır. Tutar artarsa önceki tahsilat korunur, kalan borç artar. Azalırsa fatura toplamını aşan tahsilat için mevcut muhasebe mantığıyla eksi düzeltme kaydı oluşturulur; banka üzerinden otomatik iade yapılmaz.

## Güncelleme

Mevcut veritabanını silmeden, yeni kodları aldıktan sonra:

```sh
php artisan migrate
php artisan optimize:clear
```

Migration, servis ve faturalara başlangıç değeri sıfır olan iki ondalıklı tutar sütunu ekler. Eski kayıtların tutarları değişmez. Tarayıcıyı Ctrl+F5 ile yenileyin.
