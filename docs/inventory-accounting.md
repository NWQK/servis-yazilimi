# Ürün alımı, stok ve tahsilat

## Kullanım

- Yeni ürün eklenince adet × alış fiyatı kadar, ürün adı ve alış tarihiyle otomatik gider oluşturulur. Örneğin 5 adet × 200 TL = 1.000 TL.
- Mevcut ürünün stok adedi artırılırsa yalnızca artan adet, girilen alış fiyatıyla yeni alım gideri oluşturur. Aynı adedi tekrar kaydetmek veya ürün adını/fiyatını düzeltmek geçmiş alımları yeniden giderleştirmez. Stok azaltma fiziksel stok düzeltmesidir; eski alım giderini iptal etmez.
- Faturaya eklenen ürünün adedi stoktan düşer. Yetersiz stokta bütün işlem geri alınır. Sıfır stoklu kayıt silinmez; yeniden stok eklenebilir.
- Yeni fatura satırının birim fiyatı ürünün satış fiyatıdır. Mevcut satırın satış fiyatı, katalog fiyatı değişse de korunur. Miktar düzenlenince yalnızca fark kadar stok hareketi oluşur.
- Satır silinince veya farklı ürünle değiştirilince eski satırın düşülmüş stoğu geri döner. Fatura silinmesi de ürünleri stoğa iade eder.
- Ürün kaydı silinmişse fatura satırındaki ürün bilgileriyle yeniden oluşturulur. Aynı ürünün farklı satırlardan iadeleri aynı yeniden oluşturulan kayda eklenir. İade yeni alış gideri oluşturmaz.
- Yeni boş satır eklemek stok hareketi oluşturmaz; fatura kaydedildiğinde stok güncellenir. Fatura düzenlemede son ürün satırı da kaldırılabilir.

## Gelir

Gelir yalnızca tahsilattır. Ürün satışı otomatik ödeme kaydı oluşturmaz. Gelir raporunda fatura tutarı ile **Tahsil edilen gelir** ayrı gösterilir. Kâr/zarar raporu ödeme tarihine göre tahsilatları ve giderleri kullanır; başka işletmelerin kayıtlarını içermez.

Ödeme alınmış faturada ürün adedi azaltılınca veya satır kaldırılınca, yeni fatura toplamını aşan tahsilat için otomatik eksi gelir düzeltmesi oluşturulur. Düzeltme, fatura tutarındaki azalmayı aşmaz; kısmi ödeme kalan fatura tutarını aşmıyorsa korunur. Mevcut ürün satırının adedi sıfıra indirilebilir. İlk tahsilat kaydı korunur, düzeltme işlem gününe yazılır ve kâr/zarara yansır. Tekrar kaydetmek aynı iadeyi tekrar düşmez. Bu kayıt sistem içindeki hesap düzeltmesidir; bankadan para göndermez. Önceden hatalı kalan tahsilatlar geriye dönük değiştirilmez.

## Kurulum ve mevcut veriler

`php artisan migrate` ve `php artisan view:clear` çalıştırılır. Seeder gerekmez. Yeni migration ürün kimliğini, fatura satırındaki tarihsel ürün bilgilerini, stok hareketlerini ve otomatik gider bağlantısını ekler. Gider tutarı kuruşlu değerleri korur.

Eski ürünler için geriye dönük alım gideri oluşturulmaz. Daha önceki sürüm faturalarda stok düşmediği için eski satırlar silinince hayali stok iadesi yapılmaz. Eski satırın adedi artırılırsa yalnızca yeni eklenen adet stoktan düşer; sonraki iadede yalnızca gerçekten düşülmüş miktar geri gelir. Eski ve zaten silinmiş bir ürünün hiç kaydedilmemiş tarihsel bilgileri yeniden üretilemez.

Stok ve gider işlemleri transaction içinde, işletme kaydı kilitlenerek yapılır. MySQL/MariaDB üzerinde aynı ürün için eşzamanlı satış testinde stok aşımı engellenmiştir.

## Doğrulama

`php vendor/bin/phpunit -c phpunit.qr.xml` QR ve stok testlerini yalıtılmış SQLite üzerinde çalıştırır. Tam migration ve eşzamanlı stok işlemleri ayrıca yalıtılmış MySQL veritabanında doğrulanmıştır.
