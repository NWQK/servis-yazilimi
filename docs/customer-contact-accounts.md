# Müşteri iletişimi ve giriş hesabı ayrımı

- Müşterinin e-posta adresi `users.contact_email` alanında tutulur. `users.email` yalnızca giriş hesabı adresidir ve veritabanında benzersiz kalır.
- Müşteri kayıtları, mevcut araç/servis/fatura ilişkilerini bozmamak için aynı kimliklerle korunur; müşteri ID'si başka bir işletme hesabına dönüştürülmez.
- Formlar, fatura ve iletişim kodları müşterinin `email` model özelliğini okumaya devam edebilir; model bu özelliği müşteri için iletişim alanından getirir.
- Müşteri e-postası isteğe bağlıdır. Aynı adres/telefon aynı veya farklı işletmelerde birden fazla müşteri kaydında kullanılabilir. Adresin müşteri olarak kayıtlı olması, yeni işletme veya personel hesabı oluşturulmasını engellemez.
- Gerçek giriş hesapları arasında aynı e-posta tekrar kullanılamaz. Müşteri kayıtları giriş, oturum hatırlama, e-posta doğrulama ve şifre sıfırlama sorgularından dışlanır.
- Geçiş migration'ı mevcut müşteri e-postalarını iletişim alanına taşır. Araç, servis, fatura ve müşteri kimlikleri değişmez. Eski müşteri hatırlama/doğrulama bağlantıları ve şifre sıfırlama tokenları geçersizleştirilir.
- Telefon numarasının müşteri kayıtlarında benzersizlik zorunluluğu zaten yoktur. Randevu doğrulamasındaki telefon/IP bazlı SMS gönderim sınırları kötüye kullanımı önlemek için korunur. Bir kayıt paylaşılması bu gönderim sınırlarını kaldırmaz.
- Yeni özellikler müşterileri e-posta/telefon üzerinden başka işletmeye bağlamamalı; ilişkiler müşteri ID'si ve işletme ID'si ile kurulmalı.

## Yayına alma

Bu değişiklik veri migration'ı içerir. Güncel bir veritabanı yedeğinden sonra kod ve migration birlikte uygulanmalı; geçiş sırasında müşteri kaydı alınmamalı.

```bash
php artisan down
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

Migration başarısız olursa hatayı düzeltmeden `up` çalıştırmayın. Geçiş sonrasında aynı e-postayla bir işletme hesabı ve birden fazla müşteri kaydı bulunabilir; eski kod bu ayrımı desteklemez. Bu yüzden yalnızca kodu eski sürüme döndürmek güvenli bir geri alma değildir. Migration geri alınırken çakışan e-postalar varsa veri kaybını önlemek için işlem durdurulur.
