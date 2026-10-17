# Mobil API v1

Temel sürüm: urun-servis-istatistikleri / 585e517316047f41e3fc921418d6d80beb7d1d99. Geliştirme branch'i mobile-api-v1, ayrı worktree. Canlı sunucu güncellenmedi.

## Sözleşme

`Accept: application/json`; token sonrası `Authorization: Bearer TOKEN`. Kimlik doğrulama tokenları dışında cevaplar `data`, listeler ayrıca `meta: {page, has_more}` içerir. Listelerde `q` en fazla 100 karakter, `page>=1`, `per_page` 1–50 (varsayılan 20). Hatalar Laravel JSON `message` ve doğrulamada `errors` alanlarıyla döner. Yeni alanlar eklenebilir; mevcut anlamları değiştirmek yeni API sürümü gerektirir.

| Yöntem | Yol (/api/v1 altında) | Kullanım |
|---|---|---|
| POST | auth/login | email, password, device_name; token ve requires_2fa |
| POST | auth/2fa | Geçici token + code; TOTP veya tek kullanımlık kurtarma kodu |
| POST | auth/logout | Yalnızca isteğin cihaz tokenını iptal eder |
| GET | session | Kullanıcı/işletme adı ve id, izinler, özellik sınırları |
| GET | devices | Kendi aktif mobil tokenlarının ad/id/son kullanım/sona erme bilgileri |
| DELETE | devices/{id} | Yalnızca kendi tokenını iptal eder |
| GET | dashboard | İzin verilen kaynakların işletme içi kayıt sayıları |
| GET | customers, vehicles, services | Sayfalı/aranabilir listeler |
| GET | customers/{id}, vehicles/{id}, services/{id} | Yetki ve işletme kapsamındaki detay |
| POST | customers | Müşteri-only kayıt; Idempotency-Key UUID zorunlu |

Müşteri alanları: name, phone_number (5 ile başlayan 10 hane), isteğe bağlı email, city, state, address, notes. İşletme/rol/aktiflik alanları istemciden alınmaz. Yanıt `data.id`; ilk istek 201, aynı içerik/anahtar tekrarı 200, değişmiş içerikle tekrar 409. Stok, servis veya fatura yaratmaz. Web ClientOnlyController ile ortak ClientRegistration kullanılır; web yanıtı değişmez.

## Güvenlik

- Sanctum hashlenmiş tokenları; tam token 30 gün, 2FA bekleme tokenı 5 dakika.
- Bekleme tokenı sadece 2FA ve logout için kullanılabilir. 2FA tamamlanınca yeni tam token verilir, eskisi silinir. Mevcut TwoFactorAuthentication servisi replay korumasını ve kurtarma kodu tüketimini yürütür.
- Her veri isteğinde gerçek hesap/işletme aktifliği, hesap türü, e-posta doğrulaması, hesap oturum sürümü, işletme oturum/kurtarma sürümü ve 2FA/parola durumu denetlenir. Personel ayrılışında eski token yeniden etkinleştirilse dahi geçersiz kalır.
- Rol izinleri her istekte sunucuda değerlendirilir; listede manage, detayda show, eklemede create gerekir. Personelin servis sorgusu assign ile sınırlıdır. İlişkili kişi/araç/katalog alt sorguları işletme kimliğiyle bağlanır.
- Şube merkezi girişleri ve `_branch`/X-Branch-Context ile geçiş ilk sürümde reddedilir. Yetkiyi sessizce genişletmez.
- Abonelik askısı/bitimi/demo bitimi, platform bakım ve kurtarma kapıları API'de açık uygulanır. Bakım ve abonelik askısında logout erişilebilir tutulur.
- Yazma, RecoveryWriteLock paylaşımlı dosya kilidi ve mevcut InventoryAccounting işletme satırı kilidinin altında gerçekleşir. Kilit alındıktan sonra oturum tekrar doğrulanır. İdempotency kaydı ve müşteri aynı transaction'dadır.
- Eski fatura revision/ödeme/stok kilitleri değiştirilmedi; bu API henüz finansal yazma ucu sunmaz. Sonraki aşamada InvoiceBilling/InventoryAccounting/CashRegister servisleri ve mevcut revision/işlem anahtarları kullanılmalı; Dart'a hesaplama taşınmamalı.
- Cevaplar ve API hataları private/no-store. API'de web oturum çereği tek başına yeterli değildir. Giriş ve 2FA hız sınırları vardır; gerçek dağıtımda ortak kalıcı cache kullanın.

İstatistik mobil ilk sürümde sunulmuyor. Gelecek endpoint, `UsageStatistics::report` ve `accessible` kurallarını kullanmalı; ürün birimleri birleştirilmemeli, ödemeler/alımlar/transferler kullanıma eklenmemeli; tamamlanan serviste aynı tür yalnız bir defa sayılmalı. Mevcut product-service-statistics.md korunur.

## Yerel test ve yayın sınırı

Yeni migration: `2026_10_17_100000_create_mobile_requests.php`. Canlıda çalıştırılmadı. İleride test/staging deploy'da normal Composer kurulumu, PHP 8.2+intl, migration ve cache yenilemesi gerekir. Token tablosu önceki Laravel 12 migration'ından gelir. Sanctum expired-token temizleme görevi ayrıca işletilmelidir.

`tools/mobile-local.php seed`, yalnızca yeni sentetik SQLite dosyası hazırlar. Tracked .env kullanılmaz. Mail array, dış HTTP seed sırasında engelli. `php -d extension=intl -S 127.0.0.1:8787 tools/mobile-local.php` yalnızca /api/v1 yollarını sunar. Bu yardımcı üretim sunucusu değildir.

`vendor/bin/phpunit -c phpunit.mobile.xml` yeni API ve mevcut müşteri-only testlerini çalıştırır. Windows sandbox'ında autoload önceden yükleyen yerel runner kullanıldı. Testler gerçek rol/izinlerle, SQLite in-memory üzerinde çalışır; Gate ile herkese izin verilmez. SQLite testi MySQL eşzamanlılık testi yerine geçmez. Yeni mobil idempotency için gerçek MySQL yarış testi yapılmadıysa bu açıkça raporlanmalıdır.
