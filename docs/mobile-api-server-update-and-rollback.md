# Mobil API denemesi: VDS güncelleme ve geri dönüş

Bu işlem sunucuda otomatik çalıştırılmadı. Aşağıdaki komutları SSH terminalinde, Laravel projesinin `artisan` dosyasının bulunduğu klasörde uygulayın. Örnek bir proje yolu varsayılmıyor.

## GitHub'da korunan sürümler

| Depo | Branch | İşaretlediği sürüm |
|---|---|---|
| NWQK/servis-yazilimi | checkpoint/web-before-mobile-api-2026-10-18 | 585e517316047f41e3fc921418d6d80beb7d1d99 |
| NWQK/servis-yazilimi | integration/web-mobile-api | Test edilmiş web + API entegrasyonu ve bu belge |
| NWQK/sanayirandevu-mobile | checkpoint/flutter-before-integration-2026-10-18 | 9fed71cf527a904316fa27c4a97c883a67791063 |
| NWQK/sanayirandevu-mobile | integration/web-mobile-api | Entegrasyon düzeltmeleri olan Flutter |

Webin mevcut `urun-servis-istatistikleri` branch'i ve Flutter'ın uzak `main` branch'i değiştirilmez. Flutter'ı VDS'ye kurmak gerekmez; VDS yalnız Laravel'i çalıştırır. Uygulamanın gerçek sunucu testi için HTTPS API adresiyle ayrıca APK hazırlanmalıdır. Önceki yerel debug APK `127.0.0.1:8796` kullanır ve kendi başına VDS'ye bağlanmaz.

## 1. Ön kontrol ve güncelleme hedefini kaydetme

Önce gerçek proje klasörüne `cd` ile girin. Aşağıdaki blok, sunucunun denetlenen son web sürümünde olmasını şart koşar. Farklı sürüm çıkarsa durur; daha eski veya farklı bir sürüme bu yükseltme/geri dönüş güvencesi otomatik uygulanamaz. Dosyaları zorla sıfırlamayın.

```bash
set -euo pipefail
test -f artisan && test -f .env
php -v
git status --short
current=$(git rev-parse HEAD)
expected=585e517316047f41e3fc921418d6d80beb7d1d99
if [ "$current" != "$expected" ]; then
  printf 'DURDU: Sunucudaki commit %s; beklenen %s. Önce bu fark incelenmeli.\n' "$current" "$expected"
  exit 1
fi
git diff --quiet HEAD -- . ':(exclude).env' ':(exclude)storage/**' ':(exclude)bootstrap/cache/**' ':(exclude)vendor/**'
git fetch origin
target=$(git rev-parse origin/integration/web-mobile-api)
git merge-base --is-ancestor "$current" "$target"
git diff --quiet "$current" "$target" -- .env storage
```

Kaynak kodda yerel değişiklik varsa `git diff --quiet` aşaması durur. `.env`/storage/vendor çalışma zamanı farkları otomatik silinmez. Untracked dosya çakışmasında Git de checkout'u durdurur. `set -e` nedeniyle terminal kapanırsa yeni bir SSH terminali açın; hata çözülmeden sonraki bloğa geçmeyin.

## 2. Kod, ayar, veritabanı ve dosya yedeği

Aynı SSH terminalinde devam edin. Yedek proje/public dışında, yalnız oturum sahibinin erişebileceği klasördedir. `mysqldump` satırındaki `sanayi` ve `sanayirandevu`, önceki sunucu kurulumundaki kullanıcı/veritabanı adlarıdır; sizde farklıysa bu iki değeri değiştirin. `Enter password:` sorulduğunda MySQL kullanıcısının şifresini yazın; yazılan karakterler görünmez.

```bash
umask 077
stamp=$(date +%Y%m%d-%H%M%S)
backup_dir="$HOME/.sanayirandevu-backups/mobile-api-$stamp"
mkdir -p "$backup_dir"
chmod 700 "$backup_dir"
cp -p .env "$backup_dir/.env"
printf '%s\n' "$current" > "$backup_dir/previous-commit"
printf '%s\n' "$target" > "$backup_dir/target-commit"
printf '%s\n' "$PWD" > "$backup_dir/project-directory"
git status --short > "$backup_dir/git-status.txt"
git branch "rollback/mobile-api-$stamp" "$current"
php artisan down --retry=60
php artisan queue:restart
mysqldump --host=127.0.0.1 --user=sanayi --password --single-transaction --quick --no-tablespaces sanayirandevu > "$backup_dir/database.sql"
test -s "$backup_dir/database.sql"
tar --exclude=storage/framework --exclude=storage/logs --exclude=storage/app/private/backups --exclude=storage/app/mobile-local -czf "$backup_dir/storage.tar.gz" storage
tar -tzf "$backup_dir/storage.tar.gz" > /dev/null
sha256sum "$backup_dir/database.sql" "$backup_dir/storage.tar.gz" > "$backup_dir/SHA256SUMS"
printf 'YEDEK KLASÖRÜ: %s\n' "$backup_dir"
```

Yazdırılan yedek klasörünün yolunu saklayın. SQL dump'ın boş olmaması tek başına geri yükleme testi değildir; önemli canlı geçişlerde yedeğin ayrı veritabanına geri yüklenmesi ve VDS sağlayıcısının snapshot'ı ek güvence sağlar. Bu işlem Laravel'in kısa süreli bakım modunu açar; güncelleme sırasında tanıtım sayfası da geçici olarak etkilenebilir. Mevcut uygulama içi bakım ayarı ayrı kalır. Planlı işler/workerlar için bakım modunu yok sayan özel `--force` çalıştırmaları varsa yedek sırasında durdurulmalıdır.

## 3. Yeni kodu alma ve migration

Yukarıdaki yedek komutları başarılı olduysa aynı terminalde:

```bash
git switch --detach "$target"
cp -p "$backup_dir/.env" .env
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
printf 'ÖNCEKİ: %s\nYENİ: %s\nYEDEK: %s\n' "$current" "$target" "$backup_dir"
```

Detached HEAD burada bilinçlidir: sunucu, test edilen commit'e sabitlenir; mevcut branch'lerin geçmişi değiştirilmez. Sonraki güncellemede `git pull` yerine hedef commit/branch ayrıca seçilmelidir. Bu kontrol edilen taban ile yeni sürüm arasındaki migration'lar beş yeni mobil tabloyu oluşturur; mevcut müşteri/araç/fatura tablolarını silmez. `migrate:fresh`, `db:wipe`, yeniden seed, `key:generate`, `composer update`, `git reset --hard` ve `git clean` çalıştırmayın. `artisan` ve storage dosyalarını eski kopyayla değiştirmeyin.

Composer/migration/cache adımı hata verirse blok `up` çalıştırmadan durur. Hata sonrası körlemesine siteyi açmayın; aşağıdaki kod geri dönüşü uygulanabilir. Production `.env` içindeki APP_KEY, DB ve SMTP bilgileri korunur. Gerçek push'u bu testte açmayın; `MOBILE_PUSH_ENABLED` varsayılanı false'tur. Daha önce özel olarak açtıysanız test boyunca false yapıp config cache'i yeniden oluşturun. Canlı kayıt işlemleri mevcut merkezi SMS/e-posta ayarlarına göre ileti gönderebilir; bu nedenle sentetik test hesaplarında gerçek alıcı bilgisi kullanmayın veya yönetici panelinden ilgili kanalları geçici kapatın.

## 4. VDS kontrolü

```bash
git rev-parse HEAD
php artisan migrate:status
php artisan route:list --path=api/v1
```

Webde giriş, müşteri/araç listeleri ve randevu ekranını kontrol edin. Oturumsuz `https://sanayirandevu.com/api/v1/session` isteğinin JSON 401 döndürmesi beklenir; 200 iş verisi dönmemelidir. Flutter VDS test APK'sında API adresi `https://sanayirandevu.com/api/v1` olmalıdır. Android/iOS mağaza yayını veya gerçek push testi bu adımların parçası değildir.

## 5. Kodu önceki sürüme döndürme

Gerçek yedek klasörü yolunu aşağıdaki ilk satıra yazın. Laravel proje klasöründe çalıştırın. Başka proje klasöründe çalıştırmayı önlemek için kaydedilmiş yol karşılaştırılır.

```bash
set -euo pipefail
backup_dir='/GERCEK/YEDEK/KLASORU'
test "$(pwd -P)" = "$(cd "$(cat "$backup_dir/project-directory")" && pwd -P)"
previous=$(cat "$backup_dir/previous-commit")
php artisan down --retry=60
php artisan queue:restart
git switch --detach "$previous"
cp -p "$backup_dir/.env" .env
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
git rev-parse HEAD
```

Bu **kod geri dönüşüdür**. Mobil tablolar veritabanında kalır, eski web onları kullanmaz. Deneme sırasında eklenen müşteri/araç/randevular ortak tablolarda korunur. `migrate:rollback` çalıştırmayın; migration batch'i başka güncellemeleri de içerebilir ve mobil teknik kayıtları siler. Mobil istemci eski web sürümünde API uçlarına ulaşamaz; bu, mobil denemenin durdurulmasının beklenen sonucudur.

Tam veritabanı yedeğini geri yüklemek farklı bir işlemdir: yedekten sonra oluşan gerçek kayıtları geri alır. Bu yüzden bu rehber veritabanını otomatik geri yüklemez. Veri hasarı olursa önce mevcut durumun ikinci yedeği alınmalı, sonra hangi verinin kaybedileceği açıkça değerlendirilmelidir.
