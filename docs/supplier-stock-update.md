# Tedarikçi, stok uyarısı ve satın alım geçmişi

- Ürünler ve stok menüsünde Tedarikçiler ve Satın alım geçmişi bulunur. Ürün yetkileri kullanılır; müşteri hesapları bu yeni yönetim ekranlarına erişemez.
- Firma adı zorunludur. Personel adı soyadı, telefon ve e-posta isteğe bağlıdır. Telefon +90 önekiyle, sıfırsız 10 hane olarak girilir; cep telefonlarında WhatsApp bağlantısı açılır.
- Üründe tedarikçi isteğe bağlıdır. Varsayılan seçim “Tedarikçi olmadan ekle”dir. Tedarikçi silinirse ürünler korunur ve bağlantı kaldırılır.
- Stok uyarı miktarı boşsa uyarı kapalıdır. 0 stok tükendiğinde uyarır. Stok eşik değerine veya altına indiğinde bildirim zilinde görünür; stok eşik üstüne çıktığında otomatik kalkar. Her ürün tek uyarı oluşturur.
- Ürün listesindeki artı simgesi “Stok alımı ekle” formunu açar. Miktar, alış fiyatı, tarih, isteğe bağlı tedarikçi ve not alınır. Tekrar gönderilen aynı alım isteği ikinci kez işlenmez.
- Mevcut davranış korunur: yeni ürünün başlangıç stoğu ve ürün düzenlemedeki stok artışları da alım sayılır. Alış gideri yalnızca artan miktar için bir kez kaydedilir. Tedarikçi borç/ödeme defteri oluşturulmaz.
- Fatura satışları, iadeler, elden satışlar ve stok azaltmaları satın alım kaydı oluşturmaz.
- Geçmişte ürün/firma/birim adı, miktar, birim fiyat, tutar, alım tarihi ve not saklanır. Ürün/tedarikçi değişse veya silinse bile alım anındaki bilgiler korunur. Eski alımlar geriye dönük aktarılmaz.
- Geçmiş kayıtları oluşturulma tarihinden itibaren 2 yıl saklanır. Günlük `inventory:purge-purchases` komutu eskileri siler. Bu temizlik giderleri veya stok hareketlerini silmez.
- Borç takibi Faturalar menüsündedir. Fatura etiketi yerine gerçek kalan bakiye kullanılır. Servis formu kayıtlı araç kilometresini getirir; boş alan araç kilometresini silmez.

## Sunucu kurulumu

`php artisan migrate --force` çalıştırın. Laravel zamanlayıcısı her dakika çalışmalı:

```
* * * * * cd /PROJE/YOLU && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Zamanlayıcının hali hazırda kurulu satırını tekrar eklemeyin. Yeni JavaScript ve Blade dosyaları için npm derlemesi gerekmez. `.env`, APP_KEY, yüklenmiş dosyalar ve sunucuya ait storage korunmalıdır.
