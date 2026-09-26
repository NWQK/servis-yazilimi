# Merkezi randevu SMS sistemi

## Kurulum

```sh
php artisan migrate
php artisan optimize:clear
```

Süper admin hesabıyla **Randevu SMS ayarları** menüsünü açın. Proje adı başlangıçta `sanayirandevu.com` olarak gelir. Twilio Account SID, Auth Token ve uluslararası biçimde SMS gönderen Twilio numarasını girin. Alternatif olarak Messaging Service SID kullanabilirsiniz; iki gönderici alanı da doluysa Messaging Service önceliklidir.

Auth Token veritabanında Laravel `APP_KEY` ile şifrelenir, ekrana geri yazılmaz ve doğrulama hatalarında oturuma kopyalanmaz. Token alanını boş kaydetmek mevcut token'ı korur. APP_KEY'i ve veritabanını birlikte güvenli biçimde yedekleyin; anahtarı değiştirmek şifreli değerlerin okunmasını engeller. Kimlik bilgileri kaynak koda yazılmaz. İşletme sahipleri merkezi SMS ayarlarına ve mesaj kayıtlarına erişemez.

SMS alımını etkinleştirmeden yeni randevu alınmaz. Deneme hesabında Twilio'nun alıcı doğrulaması ve gönderim kısıtları geçerlidir. Canlı ortamda `APP_URL` gerçek HTTPS adresi olmalıdır. Buradaki proje adını değiştirmek alan adını veya uygulamanın diğer marka ayarlarını değiştirmez.

## Müşteri akışı

1. Türkiye cep telefonu numarası, işletme ve uygun saat seçilir. Formda `+90` sabit gösterilir. Başında 0 veya ülke kodu olmadan, 5 ile başlayan tam 10 rakam girilir (örnek: `5551234567`). Sunucu aynı kuralı doğrular ve SMS için `+90` ekler.
2. **Randevu al — SMS kodu gönder** düğmesi 6 haneli kod gönderir. Kod, işletme ve talep bilgilerine bağlıdır. Bu aşamada esnafa bildirim düşmez ve saat ayrılmaz.
3. Kod 5 dakika geçerlidir; en fazla 5 hatalı denemeye izin verilir. 60 saniye sonra yeniden kod istenebilir. Yeni kod eskisini geçersiz kılar. Bir talep için en fazla 3 SMS, telefon başına saatte 3, IP başına saatte 10 gönderim vardır. Günlük merkezi limit yeni doğrulama mesajlarını sınırlar; onay mesajları bu limiti durdurmaz ancak limite dahil sayılır. Kod yenilemeleri nedeniyle sayımlar temkinli biçimde daha yüksek kalabilir.
4. Doğru kod girildiğinde saat yeniden kontrol edilir. Uygunsa randevu talebi oluşturulur, telefon doğrulanmış olarak işaretlenir ve esnafın bildirim kutusuna düşer. Saat dolduysa yeni tarih/saat seçilir.
5. Esnaf onayladığında merkezi hesaptan işletme adı, randevu tarihi ve saati içeren onay SMS'i gönderilir. SMS özelliğinden önce oluşturulmuş, doğrulanmamış eski kayıtlara onay SMS'i gönderilmez.

Kodların yalnızca hash'i saklanır; müşteri talebinin geçici verileri şifrelenir. İstekler CSRF, HTTP istek sınırları ve veritabanındaki telefon/IP gönderim sınırlarıyla korunur. Bu sürüm Twilio **Programmable Messaging** üzerinden uygulamanın kendi tek kullanımlık kod kontrolünü kullanır; Twilio Verify Service SID gerektirmez.

## Mesaj şablonları

- Doğrulama: `{kod}`, `{isletme}`, `{marka}`. `{kod}` zorunludur.
- Randevu onayı: `{isletme}`, `{tarih}`, `{saat}`, `{marka}`. İşletme, tarih ve saat zorunludur.

Şablon değişiklikleri sonraki gönderimler içindir. Kuyruğa alınmış onay mesajının metni o anda saklanır; tekrar denemede aynı metin kullanılır. Tarihler Türkçe, saatler İstanbul saatine göre 24 saatlik biçimdedir. Uzun ve Türkçe karakter içeren metinler birden çok SMS segmenti oluşturabilir.

## Gönderim sonuçları

Onay mesajı randevuyla aynı veritabanı işleminde tek bir gönderim kaydı oluşturur. Twilio çağrısı işlem tamamlandıktan sonra yapılır. Aynı onayı tekrar kaydetmek ikinci mesaj oluşturmaz. İşlem yarıda kalırsa **Gönderim bekliyor** kayıtları süper admin panelinden gönderilebilir. Kesin başarısız gönderimler de buradan yeniden denenebilir.

Zaman aşımı, bağlantı sorunu veya belirsiz sunucu yanıtında otomatik tekrar yapılmaz: Twilio ilk isteği kabul etmiş olabilir. Böyle durumlarda panelde **Gönderim sonucu belirsiz** görünür. Süper admin önce Twilio kayıtlarını kontrol etmelidir. **Twilio kabul etti**, API'nin mesajı kabul ettiğini belirtir; telefona teslim edildiğini kanıtlamaz. Teslimat webhook takibi bu sürümde yoktur. OTP mesajları hata ayıklama amacıyla kayda veya ekrana yazılmaz.

Gönderim bu sürümde HTTP isteği sırasında yapılır; ayrı kuyruk çalışanı gerektirmez. Kabul edilmiş onay mesajları sonradan iptal edilen randevular için geri alınamaz. İptal SMS'i bu kapsamda yoktur.

## Test

`C:/xampp/php/php.exe vendor/bin/phpunit -c phpunit.qr.xml` otomatik testlerde Twilio HTTP yanıtlarını taklit eder; gerçek mesaj veya ücret oluşturmaz. Gerçek gönderim ancak merkezi kimlik bilgileri girilip SMS etkinleştirildikten sonra test edilebilir.

Twilio kaynakları: [Mesaj API'si](https://www.twilio.com/docs/messaging/api/message-resource), [Deneme hesabı](https://www.twilio.com/docs/usage/trials).
