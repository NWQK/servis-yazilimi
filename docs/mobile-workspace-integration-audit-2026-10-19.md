# Web ve mobil menü entegrasyonu — 19 Ekim 2026

## Taban ve birleştirme

- Laravel: bugün VDS'te çalışan `integration/web-mobile-api`,
  `e9c9e66a14565c39f54233178e2b7d50f7f9a076`.
- Flutter: bugün emülatörde kullanılan entegrasyon,
  `45408fef777758f978965107d5b14b3aa3694a75`.
- İki depoda ayrı yeni worktree/branch:
  `integration/mobile-workspace-update-2026-10-19`.
- Laravel ekleri: `5de56fa6` push tanılama ve Android payload;
  `4fc73b5c` kapsamlı mobil işletme menüsü API'si.
- Flutter ekleri: `68b50f5`, `f4ce404`, `600a13b`.
- Her iki özellik branch'i de mevcut entegrasyonun doğrudan devamıydı;
  fast-forward ile alındı, çakışma veya eski web kodunu geri getirme olmadı.
- Ana çalışma kopyaları, özellik worktree'leri ve önceki entegrasyon branch'leri
  değiştirilmedi. Ana web kopyasındaki silinmiş çalışma zamanı session dosyaları
  kaynak değişikliği sayılmadı ve entegrasyona taşınmadı.
- Gerçek `.env`, sırlar, loglar ve eski sentetik SQLite kopyalanmadı.
  Yeni worktree'de yeni sentetik veritabanı oluşturuldu; dış iletişim kapalı tutuldu.

## Kapsam ve kontroller

Sabit modül kayıt defteri üzerinden native menü açılır. Fatura/teklif, ürün/stok,
tedarikçi/geçmiş, tanımlar ve personel listeleri/detayları; ortak ürün/servis
istatistiği ve kasa raporu; işletme fatura bilgisi formu; kendi cihaz oturumlarını
iptal etme özellikleri entegre edildi. Bu sürüm mobil fatura/servis oluşturma,
tahsilat veya stok hareketi yazma eklemez. Hesap/şube erişimi mevcut MobileAccess
kurallarını kullanır; mobil şube geçişi hâlâ kapalıdır.

Sorgular işletme parent_id sınırında, arama OR koşulları gruplanmış, liste/detay
izinleri ayrı kontrol ediliyor. Cevaplar private/no-store. Özet ve toplamlar
Dart'ta yeniden hesaplanmıyor. İşletme profili MobileSession'ın recovery/işletme
transaction kilidinden geçiyor; expected_version eskimişse 409 veriyor ve aynı
payload tekrarının yan etkisi yok. Alan allowlist'i SMTP/anahtar kaydına izin vermiyor.
Yeni web profili snapshot'ları önceki faturalara uygulanmıyor.

`mobile:push-status` salt okunur; kimlik/cihaz sırrı yazdırmaz ve gönderim yapmaz.
Yeni kanal, ikon, titreşim izni ve Android payload değişiklikleri gerçek push'un
sunucu kurulumu yerine geçmez. Gerçek e-posta/SMS/FCM gönderilmedi.

## Çalıştırılan doğrulamalar

- PHP 8.2.12, Laravel 12.70.0, PHPUnit 11.5.57; izole SQLite.
- Geniş web + mobil paket: **504 test / 5.984 doğrulama**, başarılı, 6:48.
- Sonradan eklenen iki sınır testi ayrıca çalıştırıldı:
  **2 test / 19 doğrulama**, başarılı. Başka işletmenin ürün kodu/tedarikçi iletişim
  aramasının kapsam dışına çıkmaması; show izninin tek başına manage iznini aşamaması;
  geçersiz profil ve kurtarma kilidinde kayıt oluşmaması doğrulandı.
- Flutter varsayılan paket: **24 başarılı test**; o çalıştırmada 3 opt-in test atlandı.
  Yeni opt-in HTTP testi aşağıda ayrıca etkinleştirildi.
- Flutter analiz, son test dosyası eklendikten sonra: **No issues found**.
- Flutter → gerçek yerel Laravel HTTP: **3/3 başarılı**. Önceki giriş/2FA,
  müşteri/araç/randevu replay ve stale-version akışlarına ek olarak bütün izinli
  workspace listelerinin sözleşmesi, dolu katalog detayları, profil kaydetme/replay/409
  ve diğer cihaz iptalinden sonra 401 kontrol edildi.
- Tüm API ve web rotaları ayrı geçici dosyaya başarıyla serialize edildi.
- Bu güncellemede migration, composer.json/lock veya gerçek `.env` değişmedi.

İlk APK/analiz eşzamanlı denemesi bilgisayardaki bellek yetersizliğine takıldı;
ilk yerel HTTP hazır olma kontrolü de kaynak baskısında zaman aşımına uğradı.
Kaynak sınırları düşürülüp ağır işler sıraya alındı; HTTP testi ve analiz tekrarında
başarılı oldu. Gradle'ın 8 GB heap/4 GB metaspace isteği 2 GB/768 MB, en çok iki
işçi ve 512 MB Kotlin daemon sınırıyla değiştirildi. Yerel Kotlin/crash dosyaları
Git dışında tutuluyor. APK son sonucu, Flutter entegrasyon belgesindedir.

## Sınırlar ve dağıtım

Yeni APK fiziksel Android/iOS cihazında test edilmedi; gerçek push teslimi ve
üretim MySQL yük/eşzamanlılık testi yapılmadı. Bugün daha önce çalışan emülatör
sonucu bu yeni APK'nin cihaz testi olarak sayılmadı. Mevcut regresyonlar önceki
stok/ödeme/şube/veri kurtarma kurallarını da kapsar; tüm olası açıkların yokluğu
garanti edilmez. iOS build/imzalama/APNs ayrı çalışmadır.

GitHub checkpoint'i iki depoda `checkpoint/before-mobile-workspace-2026-10-19`;
Laravel e9c9e66a, Flutter 45408fe. Eski APK ayrı tutulur. VDS henüz otomatik
güncellenmedi. Adımlar ve şema geri almadan kod geri dönüşü:
[VDS güncelleme belgesi](mobile-workspace-vds-update-2026-10-19.md).

Ham yerel çıktılar üst çalışma klasöründeki `workspace-update-laravel-tests.txt`,
`workspace-update-boundary-tests.txt`, `workspace-update-flutter-tests.txt`,
`workspace-update-flutter-analyze-final.txt`, `workspace-update-contained-http-tests.txt`
ve `workspace-update-apk-final.txt` dosyalarındadır. Veritabanı ve ham çalışma zamanı
dosyaları Git'e eklenmez.
