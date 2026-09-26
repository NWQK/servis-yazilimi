# Fatura indirimi

Fatura oluşturma ve düzenleme ekranlarında isteğe bağlı **İndirim tutarı** alanı bulunur. Yüzde değil, para tutarı girilir. Boş bırakılırsa indirim sıfırlanır. Negatif veya ikiden fazla ondalık basamaklı tutar kabul edilmez.

İndirim mevcut vergiler dahil toplamdan düşülür; satırların vergi tutarını değiştirmez. Uygulanan indirim fatura toplamıyla sınırlıdır; toplam negatif olamaz. Ürünler sonradan azaltılırsa uygulanan indirim yeni toplamla sınırlanır. Girilen tutar düzenleme ekranında korunur, faturada gerçekten uygulanan tutar gösterilir.

Fatura görüntüsü, yazdırma çıktısı ve müşterinin QR fatura sayfasında indirim ayrı satırda görünür. Stok veya gider hareketi oluşturmaz. Ödenmiş faturanın toplamı azaltıldığında, mevcut gelir düzeltme mekanizması toplamı aşan tahsilatı eksi kayıtla düzeltir. Aynı işlemi tekrar kaydetmek tekrar düzeltme oluşturmaz. İndirim kaldırılırsa yeni tahsilat oluşturulmaz; kalan borç artar. Otomatik banka iadesi yapılmaz.

Güncellemeden sonra `php artisan migrate` ve `php artisan optimize:clear` çalıştırın. Eski faturaların indirim değeri sıfırdır; veritabanını yeniden oluşturmayın.
