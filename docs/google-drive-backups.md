# VDS ve Google Drive yedekleri

## Saklama düzeni

- Her gün 23.55, Europe/Istanbul: veritabanı + `storage/upload` + `storage/app/public`.
- Her hedefte en son 3 başarılı günlük kopya ve ayın 1'inde oluşturulan tek aylık kopya.
- Ayın 31'inde gece yedeği henüz alınmadıysa: 1'i (aylık), 28, 29, 30 (günlük). 31 gecesi günlükler 29, 30, 31 olur.
- Ayın 1'inde başarılı yeni kopya alınmadan önceki aylık kopya silinmez. Ayın 1'i kaçırılırsa geçmiş tarihli kopya üretilmez; panel eksikliği gösterir.
- AES-256 şifreli ZIP. Her dosyanın SHA-256 özetiyle arşiv okunarak kontrol edilir; Drive aktarımı ayrıca boyut + MD5 karşılaştırmasıyla doğrulanır.
- Hedefler bağımsız döner: Drive arızasında önceki Drive kopyaları kalır, VDS son üç günlük kopyayı tutar. Drive'da son üç doğrulanmış günlük kopya tutulur; başarısız günler için sahte başarılı kayıt oluşturulmaz.
- Aynı gün yeniden çalıştırmak o günün ilk başarılı kopyasını korur ve aktarımı tekrar dener. Bu bir anlık/sürekli yedekleme sistemi değildir.
- `.env`, uygulama kaynak kodu, oturumlar, günlükler ve rclone kimlik bilgileri arşive eklenmez. Uygulama kodunu Git'ten, APP_KEY ve yedek parolasını ayrı güvenli kayıttan geri getirmeniz gerekir.

## 1. VDS gereksinimleri (Ubuntu 24.04 / PHP 8.3 / MySQL 8)

```bash
sudo apt update
sudo apt install -y php8.3-zip default-mysql-client rclone p7zip-full
sudo systemctl restart php8.3-fpm
mysqldump --version
rclone version
```

PHP sürümü farklıysa ZIP paketini ve FPM servis adını ona göre değiştirin. Laravel zamanlayıcısı ve PHP-FPM aynı kullanıcıyla (`www-data` örneği) çalışmalı, `storage` yazılabilir olmalı. Veritabanı tabloları InnoDB olmalıdır; `--single-transaction` InnoDB üzerinde tutarlı veritabanı görüntüsü alır. Yedekleme sırasında şema güncellemesi/migration çalıştırmayın. Dosyalar ve veritabanı ortak bir dosya sistemi anlık görüntüsü değildir; kritik geri yüklemede bakım penceresi kullanın.

## 2. Google hesabını bir kez bağlama

Özel bir Google Drive hesabı veya yalnızca yedeklere ayrılmış hesap önerilir. Drive'a bağlanan araç **rclone**; uygulamanın Google Drive eklentisini kurmak gerekmez.

Google Cloud Console'da proje oluşturun, Google Drive API'yi etkinleştirin ve kendi OAuth istemcinizi oluşturun. rclone'ın paylaşılan istemcisi 2026'da kaldırıldığı için kendi client ID/secret bilgilerinizi kullanın. OAuth uygulaması kalıcı kullanım için uygun yayın durumunda olmalı; test modundaki kısa ömürlü yetkilendirme otomatik yedekleri durdurabilir.

```bash
sudo install -d -o www-data -g www-data -m 700 /etc/sanayirandevu
sudo -u www-data rclone config --config /etc/sanayirandevu/rclone.conf
```

Yeni remote: `sanayi-drive`, tür: `drive`, kendi Google client ID ve secret. Scope olarak **drive.file** (rclone'ın oluşturduğu dosyalar) seçin. Sunucuda tarayıcı yoksa otomatik tarayıcı açma sorusuna hayır deyin; rclone'ın verdiği yetkilendirme komutunu tarayıcılı bilgisayarınızda çalıştırıp sonucu sunucuya girin. Token, secret ve config dosyasını sohbete veya Git'e koymayın.

```bash
sudo chmod 600 /etc/sanayirandevu/rclone.conf
sudo -u www-data rclone lsd sanayi-drive: --config /etc/sanayirandevu/rclone.conf
```

Kaynaklar: [rclone Google Drive](https://rclone.org/drive/), [tarayıcısız sunucu bağlantısı](https://rclone.org/remote_setup/), [dosya bütünlüğü bilgileri](https://rclone.org/commands/rclone_lsjson/).

## 3. Uygulama ayarları

Proje `.env` dosyasına ekleyin; gerçek parola yerine aşağıdaki açıklama metnini bırakmayın. Parola için `openssl rand -hex 32` ile yeni bir değer üretebilirsiniz.

```dotenv
BACKUP_ARCHIVE_PASSWORD="BURAYA_EN_AZ_32_KARAKTER_RASTGELE_PAROLA"
BACKUP_MYSQLDUMP=/usr/bin/mysqldump
BACKUP_RCLONE=/usr/bin/rclone
BACKUP_RCLONE_CONFIG=/etc/sanayirandevu/rclone.conf
BACKUP_DRIVE_REMOTE="sanayi-drive:SanayiRandevuYedek"
```

**Parolayı ve mevcut APP_KEY'i sunucudan bağımsız bir parola yöneticisinde saklayın.** Parola değişince önceki arşivler eski parola ile açılır. rclone hedefini değiştirirseniz eski kopyalar kayıtlı eski hedeflerinde saklama politikasına göre temizlenir. Aynı Drive klasöründe diğer dosyalara dokunulmaz; her kurulum UUID ile ayrı alt klasör kullanır.

Proje kökünde:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Sunucu web kökü mutlaka projenin `public` dizini olmalıdır. `storage/app/private` dizinini web sunucusunda yayınlamayın. `public/storage` bağlantısı yalnızca `storage/app/public` dizinine gitmelidir. Özel yedekler `storage/app/private/backups` altında, dizin 700/dosyalar 600 izinleriyle saklanır.

## 4. Zamanlayıcı ve panel

`sudo crontab -u www-data -e` ile aşağıdaki satırı ekleyin; `/var/www/sanayirandevu` yerine gerçek proje dizininizi kullanın. Aynı Laravel zamanlayıcısı zaten varsa ikinci kez eklemeyin.

```cron
* * * * * cd /var/www/sanayirandevu && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Süper admin → **Ayarlar → Yedekler**: kurulum eksiklerini kontrol edin, otomatik yedeklemeyi açın, **Şimdi yedekle / aktarımı dene** butonuna basın. Web isteği arşiv üretmez; talep zamanlayıcının sonraki dakikasında çalışır. Ekranı yenileyerek sonucu görün. İlk yerel ve Drive kopyası doğrulanmadan kurulumu tamamlanmış saymayın.

Elle CLI testi (yedekleme panelde etkin olmalı):

```bash
sudo -u www-data php artisan platform:backup
php artisan schedule:list
```

Ekranda günlük/aylık kopyalar, boyut, yerel durum, son Drive doğrulama zamanı, bekleyen talep ve başarısız çalışma gösterilir. Esnaflar bu sayfaya erişemez. Aktifleştirme ve manuel talep yönetici işlem geçmişine yazılır. Kapatmak eski kopyaları silmez.

## 5. Geri yükleme denemesi

Geri yüklemeyi önce ayrı bir test dizininde ve boş test veritabanında deneyin. Canlı veritabanına doğrudan içe aktarmayın. Paneldeki doğrulama, arşivin okunabildiğini gösterir; sunucuda gerçek MySQL geri yükleme testi de yapılmalıdır.

Drive'daki UUID ve dosya adını kendi yedeğinizle değiştirin:

```bash
rclone copyto 'sanayi-drive:SanayiRandevuYedek/KURULUM_UUID/daily/DOSYA.zip' ./yedek.zip --config /etc/sanayirandevu/rclone.conf
7z x ./yedek.zip -o./geri-yukleme-testi
```

7z parola sorar; parolayı komut satırına eklemeyin. Arşivde `database.sql`, `manifest.json`, `uploads/0` (storage/upload) ve `uploads/1` (storage/app/public) bulunur. SQL dosyası orijinal veritabanı adını içerir (`--databases`), dolayısıyla test geri yüklemesini canlı MySQL'den **ayrı, izole bir MySQL sunucusunda** yapın. Uygulama kodu, APP_KEY, veritabanı erişimi ve yükleme dizinleri de geri kurulmalıdır.

Diskin yedek + geçici SQL dosyası için yeterli boş alanı olmalı. Drive kotasını takip edin. Başarısız çalışmada panel uyarısını inceleyin, Google bağlantısını/izinleri doğrulayın ve manuel talep verin. Sistem Drive onayını almadan eski Drive yedeğini silmez.
