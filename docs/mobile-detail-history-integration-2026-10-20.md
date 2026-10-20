# Mobil detay işlemleri ve merkezi şasi geçmişi entegrasyonu

Yeni branch (iki depoda): `integration/mobile-detail-history-update-2026-10-20`.

Laravel tabanı `d0f19d7ec40106022251583adad3e84474100f9b` (merkezi şasi sorgusu).
Birleştirilen mobil API commit'i `79b2acb50a1dcc1b9afadc542ffe1dd9d7ad71eb`;
ortak taban `aec9fb9d199fcfd0d1f439f1c4e60619d32fddb8`.
Flutter tabanı `80b606ea2e1bdba36a2b4af11053da99cb2afc24`, alınan commit
`ad40a36b79db8e800fbcc4fc736934ea5240fc61`. Kaynak worktree'ler değiştirilmedi.
Yerel mobil feature commit'leri başlangıçta origin'de yayımlanmış değildi;
entegrasyon branch'leri onları da geçmişleriyle içerir. Gerçek .env taşınmadı.

## Davranış ve çakışma çözümü

Fatura düzenleme/önizleme, ödeme ekleme, müşteri ve araç güncelleme uçları
`/api/v1/actions/...` altında, mevcut API ve mobil oturum korumalarıyla çalışır.
Finans hesapları Flutter'a taşınmadı. Web ve mobil ortak InvoiceEditing,
ManualInvoicePayment, CustomerContact ve VehicleEditing servislerini kullanır.

VehicleController çakışması ortak düzenleme servisine geçilerek çözüldü. Mobil
branch'in eski `history_public` / share çağrısı çıkarıldı. İşletme sorgu geçmişini
kapatamaz/açamaz; yalnız süper admin gerekçeli formdan yönetir. Mobil VIN değişimi
eski yayımlanmış özeti başka şasiye taşımaz, gizleme kararını kaldırmaz.

İstek UUID'si, işletme, aktör, kurtarma sürümü ve gövde özetiyle saklanır. Aynı
ödeme/düzenleme tekrarında kayıt yeniden oluşturulmaz. Farklı içerikle anahtar
kullanımı ve eski fatura/kayıt sürümü 409 döndürür. Fatura önizlemesi veritabanı
savepoint'iyle geri alınır; önizleme jetonu aktör, işletme, gövde ve veri durumuna
bağlıdır. Yeni tablo `mobile_detail_operations`, işletme geri yükleme paketine
alınmaz; kurtarma sürümü önceki işlemlerin tekrar kullanılmasını engeller.

Oturum yanıtı desteklenen detay işlemlerini yetkilere göre bildirir. Ödeme
şeması kısmi/eski ise 500 yerine 503 ve güncelleme açıklaması döner.

Tanıtım menüsü ve alt menüsüne, randevu dizini/akışının ortak başlığına
**Şasi sorgula** bağlantısı eklendi. Küçük ekranda bağlantılar alt satıra geçer.
Hedef `/arac-gecmisi`; şasi URL parametresine konmaz.

## Veritabanı / bağımlılıklar

Yeni migration: `2026_10_20_120000_create_mobile_detail_operations`.
D0 henüz VDS'e alınmadıysa merkezi geçmiş migration'ı da çalışacaktır:
`2026_10_20_000001_centralize_public_vehicle_history`.

Laravel composer.json/composer.lock, .env, vendor ve storage dosyaları iki tabana
göre değişmedi. Bu artımlı güncellemede composer install gerekmiyor. Flutter'a
url_launcher eklendi; Android sürümü 0.3.0+4. APK tek başına yeterli değildir.

## Doğrulama

Geniş Laravel regresyonu: 521 test / 6.195 doğrulama başarılı. Son API/ödeme
şeması/yetenek kontrolleri: 42 test / 496 doğrulama başarılı. Paketler örtüşür;
sayılar toplanmaz. İzole route:cache başarılı.

Ayrı MariaDB 10.4.32'de mevcut işletme korunarak yeni migration uygulandı.
Eşzamanlı aynı ödeme anahtarı 200+200 ve tek ödeme; farklı anahtarlarla aynı
revizyon 200+409 verdi. Toplam iki ödeme 50, kalan borç 50; stok değişmedi.
Gerçek üretim MySQL 8 yük testi yapılmadı.

Flutter analiz temiz; 28 birim/ekran testi başarılı, varsayılan koşuda beş opt-in
HTTP/görsel test atlandı. HTTP kontrolü ayrıca yalnız yeni sentetik SQLite ile
çalıştırılır. İlk paralel koşu tek iş parçacıklı PHP sunucusunda zaman aşımına
uğradı. Tekrar koşusunda sabit VIN ve önceki test cihazlarını varsayan fixture
sorunları düzeltildi: VIN artık benzersiz, iptal edilen oturum tam kimliğiyle seçilir.
Gerçek yerel API üzerinde dört HTTP testi de başarılı: giriş/2FA, müşteri/araç/randevu, işletme/cihaz oturumu ve fatura önizleme/düzenleme/ödeme/tekrar. Testler tek tek, her dosya öncesi yalnız izole test önbelleği temizlenerek yürütülür;
üretim rate limit'leri değiştirilmedi, önceki sentetik veritabanları silinmedi.

Gerçek e-posta/SMS/push gönderilmedi. VDS, fiziksel Android ve iOS üzerinde bu
çalışmada test/dağıtım yapılmadı. Diğer mobil servis oluşturma, PDF/yazdırma/silme
ve stok/finans yönetim formları tamamlanmış sayılmaz. Gerçek push kurulumu ayrı.

## VDS'e adım adım geçiş

Önce mevcut SHA, kaynak değişiklikleri ve migration durumunu kontrol edin.
18 Ekim yedeğini güncel veri yedeği yerine kullanmayın. Yeni SQL, storage ve .env
yedeği alınıp SHA256 kontrolü tamamlanmadan kod değiştirilmemeli.

1. `cd /var/www/sanayirandevu`, `umask 022`, `git rev-parse HEAD`, kaynak durumu.
2. Yeni branch'i fetch edin; mevcut SHA'nın hedefin atası olduğunu doğrulayın.
   `.env`, composer dosyaları, vendor ve storage için iki commit arasındaki fark
   boş olmalıdır. Başka kaynak değişikliği varsa zorla reset/clean yapmayın.
3. Bakım altında güncel .env + SQL + storage yedeği alın. `umask 077` yalnız yedek
   alt kabuğunda kullanılsın; uygulama komutlarında 022 kalsın. Çalışan özel yazıcı
   işler varsa yedek boyunca durdurun. SQL dökümünün çıkış kodu ve son satırı,
   tar içeriği ve SHA256SUMS mutlaka doğrulansın.
4. Hedef SHA'ya `git switch --detach HEDEF_SHA` ile geçin; .env korunmalıdır.
5. İzinler ve komutlar aşağıdaki sıradadır. Her hata halinde sonraki komuta
   geçmeden nedeni incelenmelidir. Bunlar canlıda otomatik çalıştırılmadı.

```bash
umask 022
chgrp -R www-data app bootstrap config database public resources routes vendor
chmod -R g+rX app bootstrap config database public resources routes vendor
chgrp www-data composer.json composer.lock
chmod 640 composer.json composer.lock
chown -R www-data:www-data storage bootstrap/cache
chmod -R u+rwX storage bootstrap/cache
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan queue:restart
systemctl reload php8.2-fpm
sudo -u www-data php artisan up
git rev-parse HEAD
php artisan route:list --path=api/v1/actions
php artisan route:list --path=admin/vehicle-history
curl -i -H 'Accept: application/json' https://sanayirandevu.com/api/v1/session
```

Oturumsuz son yanıt JSON 401 olmalı. Web girişi, işletme fatura ekranı ve şasi
sorgusu kontrol edilmeli. Sonra aynı imzalı yeni APK `adb -e install -r DOSYA`
ile yüklenir. API adresi `https://sanayirandevu.com/api/v1` olarak kalır.

## Geri dönüş

GitHub checkpoint branch'i `checkpoint/before-mobile-detail-history-2026-10-20`:
Laravel D0, Flutter 80b606e. Önceki APK ayrıca korunur.

Yeni detay API'sinden vazgeçilirse D0 web koduna dönülebilir; yeni işlem tablosu
bırakılır ve eski APK kullanılır. SQL yedeği otomatik geri yüklenmez: geçişten
sonraki kayıtlar kaybolur. Merkezi şasi migration'ının down yöntemi bilerek tablo
silmez. AEC veya daha eski koda dönmek merkezi gizleme politikasını uygulamaz;
bu nedenle merkezi geçmişi koruyan geri dönüş tabanı D0'dır. Kod geri dönüşünde
aynı izin/cache/FPM adımları tekrarlanır; bakım yalnız kontroller sonrası açılır.