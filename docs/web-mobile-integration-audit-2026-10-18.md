# Web / mobil entegrasyon kontrolü — 18 Ekim 2026

## Kaynaklar ve korunan kopyalar

- Web: `urun-servis-istatistikleri`, `585e517316047f41e3fc921418d6d80beb7d1d99`. Branch adı varsayılmadı; HEAD, diğer yerel branch'ler ve geçmiş karşılaştırıldı.
- Mobil API: `mobile-api-v1`, `fc21fb34ac607fda36bb62237008f7cc5c5e2f5d`.
- Flutter: `main`, `9fed71cf527a904316fa27c4a97c883a67791063`.
- Ana web kopyasında kaynak kod değişikliği yoktu. Başlangıçta silinmiş olan beş takipli oturum dosyası korunarak entegrasyona alınmadı. Mobil API ve Flutter kaynakları başlangıçta temizdi ve değiştirilmedi.
- Laravel entegrasyonu ayrı `servis-yazilimi-web-mobile-integration` worktree'sinde; Flutter ayrı depodaki `sanayirandevu-mobile-integration` worktree'sinde. İkisinin branch adı `integration/web-mobile-api`.
- Gerçek `.env` ve çalışma zamanı oturum/log/derlenmiş view dosyaları yeni Laravel checkout'una taşınmadı. Testlerde sıfır anahtarlı sentetik `.env.testing`, bellekte SQLite ve yeni integration worktree'sinde ayrı sentetik SQLite kullanıldı. Test ortam dosyası commit edilmedi.
- Önceki mobil SQLite dosyasının kontrol öncesi/sonrası SHA-256 değeri aynı: `41f30e722e234a3cbeb95e3639555375dc74cb2ff5c56fb7bc781b53f46a3e23`. Mevcut dosya silinmedi, yeniden seed edilmedi.

## Git birleştirmesi

Web tabanı API branch'inin doğrudan atası çıktı. Yeni branch önce web commit'inden oluşturuldu, ardından `git merge --ff-only mobile-api-v1` uygulandı. Kör dosya kopyalama veya çatışmalı eski branch'in yeni web kodunun üzerine yazılması yapılmadı.

Korunan API commitleri:

1. `24419826867e8a7457dbdf3de6173b28b655e070` — API v1, cihaz oturumu ve işletme erişimi.
2. `e583a811ee9076a842720d43e97cba127c406e9b` — oturuma bağlı bildirimler.
3. `fc21fb34ac607fda36bb62237008f7cc5c5e2f5d` — randevu işlemleri ve ortak araç kaydı.

Mekanik merge çatışması olmadı. Davranış düzeltmeleri ayrı commitlerle yapıldı:

- Laravel kodu: `841acf3ade347c5fcff80892cce7db56f71679d5`.
- Flutter kodu: `857445e158a22c089bc24f1b6f147d3881c3d1f0`.

Bu belgenin eklenmesi ayrıca bir dokümantasyon commit'idir; branch'lerin nihai HEAD değerleri teslim mesajında yer alır. Push/PR/dağıtım yapılmadı; yerel commitlerin GitHub'da bulunduğu varsayılmamalıdır.

## Bulunan ve düzeltilen davranışlar

| Senaryo | Önceki davranış | Düzeltme |
|---|---|---|
| A hesabının session isteği sürerken B hesabına geçiş | Eski yanıt yeni hesabı değiştirebilir, eski kontrol yeni kontrolü engelleyebilirdi | Doğrulama token ve işlem sıra numarasına bağlandı; eski başarı/hata yok sayılıyor |
| Hesap değişiminden sonra eski API yanıtı | Başarılı eski veri tüketilebilirdi | API istemcisi eski tokenla gelen yanıtı reddediyor; belirsiz yazma otomatik tekrarlanmıyor |
| Müşteri formu güvenli depolamaya yazarken hesap değişimi | Eski form yeni hesabın tokenıyla gönderilebilirdi | Form başlangıç hesabını/tokenını saklıyor; bekleme sonrası değişmişse POST yapılmıyor |
| Aynı saniyede randevu müşteri/not/süre düzenlemesi | Zaman damgası değişmeyince eski version geçerli kalabiliyordu | Version özeti kullanıcıya gösterilen/işleme etki eden randevu alanlarını da içeriyor; eski onay 409 |
| Araç listesinde gösterilen katalog marka/model adıyla arama | Yalnız ham vehicles.model aranıyordu | İşletme kapsamındaki katalog adı da aranıyor |
| Arşivlenmiş/silinmiş müşteri isteğinin eski anahtarla tekrarı | Var olmayan müşteri için 200 dönebiliyordu | Mevcut aktif müşteri kontrolüyle 409; yeni müşteri yaratılmıyor |
| Başarılı mobil giriş | Demo son giriş zamanı webdeki gibi güncellenmiyordu | Ortak DemoLifecycle kullanılıyor; 2FA bekleme aşaması başarılı giriş sayılmıyor |
| Boş alan hata listesi olan 422 | Flutter liste erişimi istisnası | API hata sözleşmesi korunuyor |

İlk dört yeni Laravel senaryosu düzeltme öncesinde başarısız oldu; düzeltmeden sonra geçti. Flutter hesap değişimi testlerinin ilk çalışması ortamın yerel soket engeline takıldı; bu ilk deneme davranış için başarısızlık kanıtı sayılmadı. Düzeltmelerden sonraki testler geçti.

Logo kurulum regresyon testi, doğrudan `.env` okumak yerine uygulamanın seçili ortam dosyasını okuyor. Aynı dosyanın değişmediği doğrulaması korunuyor; gerçek sırları test kopyasına taşımak gerekmiyor.

## Kontrol edilen sınırlar

- `/api/v1` JSON cevapları, `data`, `meta.page/has_more`, alan doğrulama hataları, 401/403/404/409/422/429 davranışları.
- İşletme kapsamı, ilişkili müşteri/araç/katalog kapsamı, personele atanmış servisler, anlık yetki iptali ve ayrılan personelin eski oturumu.
- 2FA geçici tokenının iş verisi okuyamaması; TOTP tekrar kullanım koruması, kurtarma kodu ve cihaz tokenının iptali.
- Abonelik bitimi/askısı, bakım ve işletme kurtarma engelleri. Eski recovery sürümünün oturumu geçersiz kılması.
- Ortak VehicleRegistration, ClientRegistration, AppointmentBooking ve AppointmentAttention davranışları. QR/link oluşturma ve şasi doğrulaması.
- Yazma işlemlerinin mevcut RecoveryWriteLock + InventoryAccounting işletme kilidinden geçmesi. Stok/ödeme/fatura hesapları Dart'a taşınmadı; mevcut finans kilitleri/revision kontrolleri korunuyor.
- Mobile migration sıralaması ve FK cascade ilişkileri, observer'ın commit sonrası çalışması ve `mobile:push-work` scheduler kaydı.
- Bildirim sayacı/okundu davranışı ve randevu durumunu onaylamadan yalnız bildirimi okuma. Push göndericisi testlerde sahte; gerçek gönderim kapalı.

## Test sonuçları

- Geniş Laravel web + API regresyonu: **497 test / 5.884 doğrulama, tamamı başarılı**. PHPUnit 11.5.57 / PHP 8.2.12; ayrı SQLite test veritabanı. Son çalıştırma 6 dakika 22 saniye.

- Ayrı mobil regresyon paketi: **29 test / 347 doğrulama, başarılı**. Bunlar geniş paketin içinde de yer alır; toplamla toplanmamalıdır.
- Flutter varsayılan paket: **21 başarılı, 3 isteğe bağlı atlandı**. Atlananlar 2 gerçek HTTP senaryosu ve görüntü üretme senaryosu. HTTP senaryoları aşağıda ayrıca etkinleştirilerek çalıştırıldı.
- Flutter `analyze --no-pub`: **No issues found**.
- Flutter → gerçek yerel Laravel HTTP, yeni sentetik SQLite: **2/2 başarılı**. Giriş, oturum/listeler/detay/arama, müşteri ve araç oluşturma + replay, bildirimler, randevu onay/replay/eski version, logout sonrası 401; ayrıca 2FA bekleme kısıtı ve tek kullanımlık kurtarma kodu.
- Ayrı **MariaDB 10.4.32**: mevcut sentetik web verisinin üzerine migration, müşteri/araç için ikişer eşzamanlı aynı istek ve randevu için iki eski-version isteği başarılı doğrulandı. Müşteri/araç cevapları 201+200 ve tek kayıt; randevu 200+409 ve tek işlem. Bu, MySQL 8 veya üretim yük testi değildir.
- Android debug APK derlemesi başarılı: `build/app/outputs/flutter-apk/app-debug.apk`, **164.039.347 bayt**. SHA-256: `ce395990fd0b169368ccd15f0512d4f9b56475f4a0537ea890b10fbdddb6de363`.
- `adb devices -l`: bağlı cihaz yok. **Gerçek Android cihaz ve iOS testi yapılmadı.**

İlk Laravel denemesi yeni checkout'taki derlenmiş view klasörünün yokluğu ve testlerin `.env` varsayımı nedeniyle hata verdi; test klasörleri ve sentetik test ortamı hazırlanıp test yolu düzeltildi. İlk ayrı süreç HTTP/MariaDB denemeleri zaman aşımına uğradı. Sunucuları testle aynı süreç ailesinde başlatan ve sonunda kapatan yerel harness ile HTTP ve MariaDB testleri geçti. Bu ilk ortam hataları uygulama başarısı gibi raporlanmadı.

## Mobilde mevcut kapsam ve kalan işler

- Mevcut: giriş/2FA, oturum, dashboard, müşteri/araç/servis listeleri ve detayları, müşteri ekleme, kısa araç ekleme, randevu liste/detay/durum işlemleri, bildirim kutusu ve Android cihaz kaydı.
- **Henüz yok:** mobil servis oluşturma, araç kabul fotoğrafı yükleme, finans/fatura/stok işlem ekranları, raporlar, randevu yapılandırması ve şube geçişi. API bunları varmış gibi açmıyor. Randevu yönetimi işletme sahibiyle sınırlı; şube merkez hesabı bu mobil sürüme giremiyor.
- Gerçek push teslimatı, Firebase üretim ayarları ve iOS/APNs ayrı doğrulanmalı. Uygulamadaki push platform desteği şu anda Android ile sınırlı.
- iOS için macOS/Xcode üzerinde build, imzalama, keychain ve fiziksel cihaz kontrolü gerekiyor. Bu inceleme iOS uygunluğuna dair çalıştırılmış test kanıtı sağlamaz.
- Üretim API adresi HTTPS olmalı; bu APK yalnız yerel debug adresine ayarlı. Release imzası ve mağaza ayarları tamamlanmış sayılmamalı.
- Firebase core/Kotlin Gradle Plugin ve Android SDK XML sürümü için derleyici uyarıları var; mevcut debug derlemesi geçti, ileride SDK/plugin yükseltmesinde ele alınmalı.
- Webdeki giriş IP/cihaz geçmişi ve giriş uyarı e-postası Mobile AuthController'a henüz bağlanmış değil. Bu kontrolde demo faaliyet zamanı eşitlendi; tüm iletişim olayları için web/mobil eşitliği iddia edilmiyor. Araç kayıt SMS'i ortak QR akışında, web kontrolöründeki ek araç e-postası ise API'de ayrıca çağrılmıyor.
- Zamanı dolmuş Sanctum tokenları, mobil outbox ve idempotency kayıtları için üretim saklama/temizlik politikası ayrıca belirlenmeli. Özellikle mobile_requests tablosunun FK'sı yok; kapanan demo kayıtlarının bu tablodaki küçük teknik kayıtları kendiliğinden silinmez. Kör süreli silme tekrar istek korumasını bozabileceğinden bu denetimde eklenmedi.
- Üretim MySQL 8 üzerinde staging migration/geri dönüş ve gerçek telefon testi tamamlanmadan canlıya hazır olduğuna dair kesin güvence verilmez. Bu denetim sınırlı senaryolarda kanıttır; tüm olası hata ve açıkların yokluğu garantisi değildir.

## Yerel Android testine başlama

Bu debug APK'nin API adresi `http://127.0.0.1:8796/api/v1`. Canlı sunucuya bağlanmaz. Yeni entegrasyon worktree'sinin sentetik verisi hazır; mevcut eski worktree verisini yeniden seed etmeyin.

Laravel integration klasöründe bir terminal açık bırakın:

```powershell
C:/xampp/php/php.exe -d extension=intl -d extension=gd -S 127.0.0.1:8796 tools/mobile-local.php
```

USB hata ayıklaması açık test telefonu bağlandıktan sonra kendi Android SDK'nızın `adb` komutuyla:

```powershell
adb devices
adb reverse tcp:8796 tcp:8796
adb install -r C:/Users/e.satarr/Documents/Codex/2026-09-20/gi/work/sanayirandevu-mobile-integration/build/app/outputs/flutter-apk/app-debug.apk
```

Sentetik hesap: `esnaf@example.test` / `Yerel-Test-2026!`. Bu yardımcı API gerçek e-posta/SMS/push göndermez. İlk kullanıcı denemesinde giriş, müşteri/araç listeleri, bir kayıt ekleme, randevu ve oturum kapatma kontrol edilebilir. 2FA sentetik kurtarma kodu testte tüketilmiştir; aynı kurtarma koduyla yeni bir başarılı test varsaymayın.

Yerel ham test çıktıları üst çalışma klasöründeki `integration-laravel-final.txt`, `integration-mobile-tests.txt`, `integration-flutter-final-tests.txt`, `integration-flutter-final-analyze.txt`, `integration-contained-http-tests.txt`, `integration-contained-mysql-setup.txt`, `integration-contained-mysql-inspect.txt` ve `integration-apk-build.txt` dosyalarındadır. Gerçek sırlar ve veritabanı dosyaları commit edilmedi.
