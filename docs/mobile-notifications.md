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
- `GET /api/v1/appointments/{id}`: izinli detay. 18 Ekim güncellemesiyle
  liste/filtre/durum yazma uçları eklendi; `mobile-appointments-vehicles.md` belgesine bakın.
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

## Arka planda bildirim gelmiyorsa — 19 Ekim 2026

Uygulamaya girince listenin güncellenmesi yalnızca API sorgusunun çalıştığını
kanıtlar. Push kaydı, outbox üretimi, sunucu gönderimi ve Android gösterimi ayrı
adımlardır. `google-services.json` mobil istemci ayarıdır; sunucu servis hesabının
ve zamanlayıcının yerine geçmez.

Yeni `php artisan mobile:push-status` komutu salt okunurdur: gönderimi açmaz,
mesaj göndermez, token/anahtar/müşteri bilgisi yazdırmaz. Yapılandırmayı, yerel
servis hesabı geçerliliğini, cihaz sayısını, outbox durum sayılarını ve son scheduler/
push-worker çalışma kaydını raporlar. Gerçek Firebase yetkisini veya teslimi
sınamaz. Kodun sunucuya güvenli dağıtımından sonra çalıştırılmalıdır.

- Gönderim kapalıysa, yetkili yönetici mevcut sunucu .env dosyasında mobile_push
  ayarlarını yapılandırmalı. Kimlik dosyası repo/public dışında tutulmalı.
- Ayar değişiminde mevcut dağıtım prosedüründeki config cache yenilenmeli.
- Yerel kimlik geçersizse dosyanın okunabilirliğini, özel anahtarı ve aynı Firebase
  proje kimliğini sunucuda kontrol edin; dosyayı sohbete/Git'e koymayın.
- Cihaz sayısı 0 ise doğru API ortamındaki uygulamada giriş yapıp cihaz bildirim
  kaydını yenileyin. Cihaz kaydı yapılmadan oluşan eski randevu push'u tekrar üretilmez.
- pending artıyor, worker güncel değilse mevcut Laravel scheduler cron'unu inceleyin.
  Standart schedule:run dakikada bir çalışmalı; ikinci kopya cron eklemeyin.
- Worker dakikada bir çalıştığı için bildirimde yaklaşık bir dakikalık kuyruk gecikmesi
  olabilir; kesin anlık teslim garantisi yoktur.
- failed/uncertain için son FCM denemesindeki http_401/http_403/http_400 veya
  send_exception kodlarını inceleyin. Yanıt gövdeleri/kimlikler loglanmaz. http_200
  yalnızca FCM kabulüdür; telefon gösterimi kanıtı değildir.
- sending/uncertain kayıtlarını otomatik sıfırlamayın: tekrar gönderim çift uyarı
  oluşturabilir. Yeni bir sentetik randevuyla kontrollü uçtan uca test yapın.

Bu sürüm FCM Android priority HIGH ile, appointments_v1 kanalı ve
ic_stat_sanayirandevu küçük ikonu üzerinden sesli bildirim ister. İstemci bu kanalı
ilk açılışta oluşturur; eski uygulamalar manifest/fallback kanalı kullanabilir.
Kullanıcının kanal sesi, bildirim izni ve Rahatsız Etmeyin seçimleri geçerlidir.
Android'in zorla durdurma ve pil yönetimi kısıtları aşılamaz.

Kontrollü test: doğru sunucudaki yeni APK'yı aç → giriş ve bildirim izni → ana ekrana
dön (zorla durdurma değil) → yeni sentetik randevu oluştur → scheduler turunu bekle →
bildirim ve dokunma akışını doğrula → ardından normal uygulama kapatma senaryosunu
ayrı dene. Gerçek müşteriye SMS/e-posta gitmeyecek test ortamını kullanın.
