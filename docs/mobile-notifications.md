# Mobil randevu bildirimleri

Yalnız `mobile-api-v1` worktree'sinde geliştirilmiştir. Canlı sunucuya kurulmadı.
Yerel harness `MOBILE_PUSH_ENABLED=false`, mail fake ve dış HTTP engeli uygular.

## API ve yetki

- Tam Sanctum oturumu + MobileSession güvenlik kapıları tüm uçlarda zorunlu.
- Randevular işletme sahibine aittir; personele randevu ayrıntısı açılmaz.
- `GET /api/v1/notifications?page=1&per_page=20`: azami 50, okunmamış sayı.
- `GET /api/v1/notifications?summary=1`: yalnız sayaç.
- `POST /api/v1/notifications/{appointment}/read` JSON `{ "event": "pending" }`
  veya `cancelled`: idempotent okundu kaydı. Eski olay için 409; başka işletme 404.
- `GET /api/v1/appointments/{id}`: izinli detay. Onay/durum yazma uçları henüz yok.
- `PUT /api/v1/push-device` JSON `{ "fid": "...", "platform": "android" }`:
  FID şifrelenir; bir kurulum tek Sanctum oturumuna bağlıdır. Yeni oturumla aynı
  kurulum kaydedilirse önceki bağ kaldırılır. Token silinince FK cascade cihazı
  ve ilgili gönderim kayıtlarını siler. FID/API anahtarları loglanmamalı.
- `DELETE /api/v1/push-device`: mevcut oturumun cihaz kaydını kaldırır.

Mobil okundu bilgisi webdeki iptal okundu alanından ayrıdır. Veri kurtarma
sürümü okundu ve outbox anahtarına dahildir. Webin mevcut sorgusu ortak
AppointmentAttention servisini kullanır; expirePending davranışı korunur.

## Gönderim (varsayılan kapalı)

Migration'lar normal test/deploy prosedürüyle uygulanmalı. Yerel sentetik DB için
yalnız `php -d extension=intl tools/mobile-local.php upgrade` kullanılmalıdır;
bu harness takipli `.env` dosyasını yüklemez. `seed-notifications` aynı işlemi
yapar ve iki sentetik randevuyu tekrar oluşturmadan ekler. Veritabanını sıfırlamaz.

Randevu oluşturma ve müşteri iptali model olayları commit sonrası outbox kaydı
oluşturur. `mobile:push-work` scheduler'da dakikada bir en fazla 100 kayıt işler.
Yeni cihaza geçmiş olaylar push olarak tekrar gönderilmez; API listesi gösterir.

Gerçek gönderimi ayrıca kurarken sunucuya özgü şu ayarlar gerekir:

```
MOBILE_PUSH_ENABLED=true
FIREBASE_PROJECT_ID=sanayirandevucom
FIREBASE_CREDENTIALS_FILE=/absolute/private/server/path/firebase-service-account.json
```

Servis hesabı dosyası repo ve public dizini dışında, yalnız sunucu kullanıcısının
okuyabileceği yerde saklanmalı. Flutter'a, Git'e veya sohbete eklenmemeli.
OAuth kısa ömürlü erişim belirteci sunucuda üretilir; FCM HTTP v1 `fid` hedefini
kullanır. Gerçek Firebase servisi ve gerçek kimlikle uçtan uca doğrulama henüz yok.

Gönderici kapalı/ayrılmış hesapları, süresi dolmuş veya iptal edilmiş tokenları,
2FA/şifre/oturum değişikliklerini, abonelik ve kurtarma sürümünü kontrol eder.
Bakım/kurtarma sırasında bekler. Süresi geçmiş veya durumu değişmiş randevuyu
göndermez. Kilit ekranı mesajında müşteri adı, telefon, plaka veya işletme bilgisi
yoktur. Gönderilmiş bir OS mesajı uzaktan geri alınamaz; TTL 5 dakika ve ayrıntı
erişiminde yeniden API yetki kontrolü uygulanır.

Gönderim durumu `pending/sending/sent/failed/uncertain/cancelled` olarak tutulur.
Zaman aşımı ve sunucu hatası otomatik tekrar gönderilmez; belirsiz bir teslimin
çoğaltılmaması için `uncertain` operasyonel inceleme ister. İşçi gönderim anında
kapanırsa `sending` kayıtları da incelenmelidir. FCM exactly-once garanti etmez;
Android tag / collapse ve APNs collapse anahtarı tekrar görüntülenmesini azaltır.
Gönderici hataları kullanıcı verisi veya anahtarları loglamaz. Henüz yönetim
panelinde outbox izleme / elle tekrar gönderme ekranı yoktur.

## Cihaz testi sonra yapılacak

Kullanıcı fiziksel cihaz testini sonraya bıraktı. Android izin kabul/red, açık
uygulama, arka plan/kapalı uygulama, bildirim açma, ağ kesintisi, hesap değişimi
ve çıkış senaryoları telefonda ayrıca denenmeli. Mevcut açık uygulama callback'i
listeyi/sayacı yeniler ve uygulama içi uyarı gösterir; ön planda ayrı OS bildirimi oluşturmaz. Zorla durdurulmuş
uygulamalarda teslim OS kurallarına tabidir.

iOS adaptörü henüz etkin değil. iOS Firebase uygulaması/plist, Apple Developer
APNs anahtarı ve Xcode Push Notifications capability gerekir. Windows'ta iOS
derleme veya test yapılmadı; macOS/Xcode ve fiziksel iPhone ile doğrulanmalı.

Resmî kaynaklar:
- https://firebase.google.com/docs/cloud-messaging/send/v1-api
- https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages
- https://firebase.google.com/docs/cloud-messaging/android/get-started
