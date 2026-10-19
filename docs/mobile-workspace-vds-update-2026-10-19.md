# Mobil işletme menüsü: VDS güncellemesi ve geri dönüş

Bu belge, bugün çalıştığı doğrulanan `e9c9e66a14565c39f54233178e2b7d50f7f9a076`
sürümünden geçiş içindir. Eski Laravel 9 dağıtım belgesini bu güncelleme için
kullanmayın. Komutlar sunucuda otomatik çalıştırılmadı.

Her iki depodaki yeni branch: `integration/mobile-workspace-update-2026-10-19`.
GitHub geri dönüş branch'i: `checkpoint/before-mobile-workspace-2026-10-19`.
Laravel checkpoint'i e9c9e66a, Flutter checkpoint'i 45408fe'dir.
Yeni sürümde menü, okuma ekranları, işletme fatura profili, cihaz oturumları ve
push tanılama bulunur. Finansal işlem oluşturma veya gerçek push kurulumu eklenmez.

## 1. SSH ön kontrolü

```bash
cd /var/www/sanayirandevu
umask 022
git rev-parse HEAD
git status --short --untracked-files=no -- . ':!vendor' ':!storage' ':!bootstrap/cache'
```

Beklenen HEAD e9c9e66a'dır. `.env` değişikliği korunur. Başka kaynak değişikliği
varsa zorla sıfırlamadan farkı inceleyin. Sonraki bloklar aynı Bash oturumundadır.

```bash
set -euo pipefail
current=$(git rev-parse HEAD)
test "$current" = e9c9e66a14565c39f54233178e2b7d50f7f9a076
git diff --quiet HEAD -- . ':(exclude).env' ':(exclude)vendor/**' ':(exclude)storage/**' ':(exclude)bootstrap/cache/**'
git fetch origin integration/mobile-workspace-update-2026-10-19
target=$(git rev-parse origin/integration/mobile-workspace-update-2026-10-19)
git merge-base --is-ancestor "$current" "$target"
git diff --quiet "$current" "$target" -- .env composer.json composer.lock database/migrations storage
```

Son kontrol bu dağıtımda ortam, bağımlılık, migration ve depolama değişikliği
olmadığını şart koşar. Başarısızsa sonraki adımlara geçmeyin.

## 2. Güncel yedek

Eski 18 Ekim yedeği yerine bugünkü veriyi de koruyun. `Enter password:` sorusunda
`.env` içindeki MySQL `sanayi` kullanıcısının parolası kullanılır; uygulama yönetici
parolası değildir. Parolayı komut satırına yazmayın.

```bash
stamp=$(date +%Y%m%d-%H%M%S)
backup_dir="/root/.sanayirandevu-backups/workspace-$stamp"
(
  umask 077
  mkdir -p "$backup_dir"
  cp -p .env "$backup_dir/.env"
  printf '%s\n' "$current" > "$backup_dir/previous-commit"
  printf '%s\n' "$target" > "$backup_dir/target-commit"
  printf '%s\n' "$PWD" > "$backup_dir/project-directory"
)
git branch "rollback/workspace-$stamp" "$current"
php artisan down --retry=60
php artisan queue:restart
(
  umask 077
  mysqldump --host=127.0.0.1 --user=sanayi --password --single-transaction --quick --no-tablespaces sanayirandevu > "$backup_dir/database.sql"
  test -s "$backup_dir/database.sql"
  tar --exclude=storage/framework --exclude=storage/logs --exclude=storage/app/private/backups --exclude=storage/app/mobile-local -czf "$backup_dir/storage.tar.gz" storage
  tar -tzf "$backup_dir/storage.tar.gz" > /dev/null
  sha256sum "$backup_dir/database.sql" "$backup_dir/storage.tar.gz" > "$backup_dir/SHA256SUMS"
)
printf 'YEDEK: %s\n' "$backup_dir"
```

Kısıtlı umask yalnız yedek alt kabuğunda kullanılır; uygulama dosyaları root 0600
oluşmaz. Yedek hatasında durun. Bu kısa bakım penceresi tanıtım sayfasını da etkiler.
Bakımı yok sayan özel işçiler varsa yedek sırasında ayrıca durdurulmalıdır.

## 3. Kodu etkinleştirme

```bash
umask 022
git switch --detach "$target"
chgrp -R www-data app bootstrap config database public resources routes vendor
chmod -R g+rX app bootstrap config database public resources routes vendor
chown -R www-data:www-data storage bootstrap/cache
chmod -R u+rwX storage bootstrap/cache
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan queue:restart
systemctl reload php8.2-fpm
sudo -u www-data php artisan up
git rev-parse HEAD
curl -i -H 'Accept: application/json' https://sanayirandevu.com/api/v1/session
```

Oturumsuz yanıt JSON **401** olmalı. Yeni migration ve Composer değişikliği
olmadığından bu geçişte migrate/composer install gerekmez. `.env`, APP_KEY,
logolar ve müşteri verisi yerinde kalır; eski artisan/storage kopyası taşınmaz.
Mobil menü rotalarını `php artisan route:list --path=api/v1/workspace` ile kontrol edin.
`php artisan mobile:push-status` salt okunur tanılamadır; gönderim kapalı/kimlik
eksik ise hata çıkış kodu verebilir, bu durum API güncellemesinin başarısız olduğu
anlamına gelmez. Bildirim göndermez veya gönderimi açmaz.

## 4. Android emülatörü

Önce backend güncellenmeli. Yeni 0.2.0+3 debug APK, aynı paket adı ve debug imzasıyla
`adb -e install -r YENI_APK_YOLU` kullanılarak mevcut uygulamanın üzerine yüklenir.
Uygulamayı kaldırmayın veya emülatörü sıfırlamayın; oturum ve uygulama verisi korunur.
API adresi `https://sanayirandevu.com/api/v1`'dir. Yeni APK henüz kullanıcı cihazında
test edilmiş sayılmaz. iOS derlemesi ve gerçek Firebase teslim testi ayrı işlerdir.

## 5. Kod geri dönüşü

Bu sürüm şema değiştirmediğinden **veritabanını geri yüklemeden** eski koda dönmek
mümkündür; güncelleme sonrası yeni kayıtlar da korunur. Önceki APK'yi de saklayın.
Laravel projesinde:

```bash
umask 022
sudo -u www-data php artisan down --retry=60
git switch --detach e9c9e66a14565c39f54233178e2b7d50f7f9a076
chgrp -R www-data app bootstrap config database public resources routes vendor
chmod -R g+rX app bootstrap config database public resources routes vendor
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan queue:restart
systemctl reload php8.2-fpm
sudo -u www-data php artisan up
```

Eski kod yeni menü uçlarını içermediği için eski APK ile kullanın. APK sürüm düşürme
emülatörde ayrıca gerekebilir (`adb install -r -d ESKI_APK`); uygulamayı silmek ilk
adım değildir. SQL yedeğini otomatik geri yüklemeyin: yedekten sonra oluşturulmuş
canlı kayıtları kaybettirir. Tam veri kurtarma ayrı, kontrollü bir işlemdir.
