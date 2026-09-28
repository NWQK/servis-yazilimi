# Stok ve elden satış

Güncellemeden sonra `php artisan migrate` çalıştırın. Mevcut veritabanını yeniden oluşturmayın.

- Ürün fatura kaydedildiğinde veya faturaya ürün ekleme işlemi kaydedildiğinde stoktan düşer. Ödeme beklenmez; ödeme kaydetmek stoktan tekrar düşmez. Kaydedilmemiş form seçimleri stok ayırmaz.
- Fatura silme ekranı ürünlerin stoğa dönüp dönmeyeceğini sorar. Evet seçilirse faturanın takip edilen stok miktarı geri eklenir. Hayır seçilirse mevcut stok değişmez. Sunucu seçim olmadan silmeye izin vermez. Fatura ve ödeme kayıtlarının silinmesine ilişkin mevcut davranış korunmuştur; bu işlem gerçek para iadesi yapmaz.
- Ürün listesindeki Elden satış yap, adet ve kayıtlı satış fiyatı üzerinden anında tahsil edilen satış oluşturur. Ekranda toplam gösterilir. Ek vergi eklenmez. Bu işlem müşteri veya fatura oluşturmaz.
- Satış, stok hareketi ve gelir kaydı aynı transaction içindedir. İşletme kilidi stok işlemlerini sıraya koyar. Aynı form tekrar gönderilirse ikinci satış oluşmaz. Stok yetersizse ya da form açıldıktan sonra birim fiyat değiştiyse işlem reddedilir.
- Elden satış için hem ürün düzenleme hem fatura ödemesi oluşturma yetkisi gerekir. Satışlar işletmeye göre sınırlandırılır.
- Gelir raporunda elden satış geçmişi, kâr/zarar tablosu ve grafiğinde satış tutarları bulunur. Ürün silinse bile satış adı, fiyatı ve tutarı korunur. Ürün alışında oluşmuş gider tekrar oluşturulmaz.
- Randevu hizmetleri kataloğuna Kaporta ve boya ile Diğer hizmetler eklenir. Esnaflar Randevu ayarlarından bu hizmetleri verdikleri taşıt türleri için seçebilir. Otomatik olarak işletmelere atanmaz.

Bu sürüm geçmişte ertelenen tahsilat ve fatura iptali muhasebesinin genel revizyonunu içermez.
