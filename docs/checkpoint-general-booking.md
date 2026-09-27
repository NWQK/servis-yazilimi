# Genel randevu için checkpoint ve kabul edilen kapsam

27 Eylül 2026: Mevcut dal sms-test. Müşteri iptali ve cevapsız randevuların süre sonunda kapanması yerelde hazır; henüz commit edilmedi. Son tam test sonucu 93 test / 1490 assertion. Mevcut işletmeye özel UUID randevu bağlantıları korunacak.

Fatura/tahsilat incelemesinde bulunan sorunlar kullanıcının isteğiyle ertelendi: ödeme silmede durum hesabı, başarısız/bekleyen ödemelerin toplamlara katılması, eşzamanlı ve tekrarlanan tahsilat, işletme sınırı kontrolleri, gerçek para iadesi ile otomatik düzeltmenin ayrılması, fatura silmede ödeme geçmişinin korunması, eski faturada vergi oranının sabitlenmesi. Bu not sorunların giderildiği anlamına gelmez.

## Genel randevu için kabul edilen kullanıcı deneyimi

- Mobil kullanım öncelikli. Taşıt ve hizmet seçimleri açılır menüler yerine büyük dokunulabilir kartlarla yapılacak.
- İl seçimi; ilçe filtresi olmayacak. Yalnızca İstanbul, İstanbul Avrupa ve İstanbul Anadolu olarak ikiye ayrılacak.
- Müşterinin izniyle konumdan il önerilecek; yanında değiştirme seçeneği bulunacak. Konum alınamazsa manuel seçim yapılabilecek. İstanbul yakası güvenilir biçimde belirlenemezse kullanıcıya sorulacak.
- Adımlar ayrı sayfalar: il/bölge → taşıt (otomobil, motosiklet, ağır vasıta) → bu taşıta uygun hizmetler → eşleşen işletmeler → mevcut işletme randevu sayfası.
- Taşıt kartına dokunmak doğrudan hizmet sayfasına, hizmet kartına dokunmak doğrudan işletme listesine götürecek. Ek devam butonu gerekmeyecek.
- İşletme kartındaki Randevu al düğmesi mevcut işletmeye özel randevu bağlantısına götürecek.
- Esnaf verdiği hizmetleri taşıt türüne göre seçebilecek. Genel listede görünürlük, il/bölge ve hizmet eşleşmeleri gerekecek.
- Seçilen taşıt/hizmet, tarih seçimi ve SMS doğrulaması boyunca korunup esnafın randevu kaydına aktarılmalı.
- Geri dönüşlerde önceki seçimler korunmalı; dar ekranlarda yatay taşma olmamalı.
- Puanlama gelecekte eklenecek; şu an kapsam dışı. Sahte puan veya öneri sıralaması gösterilmeyecek.

Bu dosya plan ve checkpoint kaydıdır; genel randevu özelliğinin uygulanmış olduğunu ifade etmez.
