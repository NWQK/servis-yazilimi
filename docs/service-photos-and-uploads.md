# Araç kabul fotoğrafları ve yükleme güvenliği

- Servis detayındaki **Araç kabul fotoğrafları** bölümünden yükleme yapılır; zorunlu değildir.
- Servis başına en fazla 10 fotoğraf; JPG, PNG, WebP. Dosya başına 8 MB, tek istekte toplam 24 MB.
- Fotoğraflar oranı korunarak en fazla 1600 × 1600 piksele getirilir ve JPEG olarak yeniden kodlanır. GPS/EXIF metadata ve dosya sonuna eklenmiş içerik korunmaz.
- Müşteri kendi özel araç bağlantısında fotoğrafları görür. Şasi üzerinden herkese açık sorguda fotoğraflar gösterilmez.
- Yetkili personel kendi işletmesinde işlem yapabilir; employee hesapları yalnızca kendilerine atanmış servislerin fotoğraflarına erişebilir.
- Özel dosyalar `storage/app/private/service-photos/photo` dizininde tutulur. Dışarıya açık statik URL üretilmez; her görüntülemede işletme veya araç bağlantısı denetlenir.
- Fotoğraf kaydı silindiğinde dosyası da kaldırılır. Servis silme ve süresi dolmuş demo temizliği fotoğrafları temizler.
- Genel yedeklerin üçüncü yükleme kökü kabul fotoğraflarını içerir. İşletmeye özel kurtarma ve geri alma da fotoğrafları kapsar.
- Fotoğraflar varsayılan olarak servis tamamlandıktan **90 gün** sonra silinir. Devam eden servislerde süre başlamaz. Tamamlanma zamanı daha önce tutulmadığı için mevcut tamamlanmış servislerin süresi bu güncellemenin uygulanmasıyla başlar.
- Süper admin **Fotoğraf saklama ve kullanım** ekranından 1–365 günlük süre seçebilir, işletme bazında kayıtlı fotoğraf sayısını ve dosya boyutunu görebilir.
- Süresi dolan fotoğraflar hemen erişime kapanır; fiziksel temizlik her gece **03.30 Türkiye saatiyle** çalışır. Kayıtsız dosyalar 24 saatlik güvenlik payından sonra temizlenir. Kurtarma yapılan işletmeler ve aktif yedekleme/kurtarma çalışmaları sırasında temizlik ertelenir.
- Müşteri ve personel fotoğrafları süre dolmadan indirebilir. Araç, servis ve fatura kayıtları bu temizlikten etkilenmez.
- Küçük bir silinme defterinde yalnızca dosya kimliği, işletme kimliği ve silinme zamanı tutulur; fotoğraf içeriği tutulmaz. Bu defter işletme kurtarmasında geriye alınmaz; eski yedekteki süresi dolmuş fotoğraf yeniden yayına çıkmaz. Genel veritabanını elle geri yüklemek bu uygulama düzeyindeki korumanın dışındadır.
- Zamanlayıcı kurulumu mevcut olmalıdır. Gerektiğinde temizlik komutu: `php artisan photos:purge`. Eski yedek arşivlerindeki fotoğraflar arşiv saklama süresi dolana kadar bulunabilir.

## Sunucu gereksinimleri

Görsel işleme için web sunucusunun kullandığı PHP sürümünde **GD** etkin olmalıdır. JPEG yön bilgisini okuyabilmek için EXIF de önerilir.

Ubuntu'nun varsayılan PHP kurulumu için:

```bash
sudo apt install php-gd
php -m | grep -Ei 'gd|exif'
```

Ardından kullanılan PHP-FPM servisini veya Apache'yi yeniden başlatın. Birden çok PHP sürümü varsa GD paketini ve yeniden başlatılan servisi uygulamanın kullandığı sürüme göre seçin.

Önerilen PHP ayarları:

```ini
upload_max_filesize = 8M
post_max_size = 32M
max_file_uploads = 10
memory_limit = 256M
```

Nginx kullanılıyorsa ilgili site bloğunda `client_max_body_size 32m;` ayarlayın. Ardından yapılandırma doğrulaması ve yeniden yükleme yapın.

```bash
php artisan migrate --force
php artisan optimize:clear
```

`public/storage` bağlantısının hedefi `storage/app/public` olmalıdır. `storage` dizininin tamamını dışarıya açmayın. Yerel ve Drive yedekleri için mevcut zamanlayıcı/worker ayarları geçerlidir.

## Diğer yüklemeler

Oturum açmış kullanıcıların web üzerinden yüklediği dosyalar merkezi kontrolden geçer. Görseller gerçek içeriğinden doğrulanır ve yeniden kodlanır; logolar, fatura logosu ve profil görselleri kendi boyut sınırlarına sığdırılır. Çalıştırılabilir dosyalar ve SVG yüklemeleri kabul edilmez. Belge yüklemelerinde uzantı ile tespit edilen MIME türünün eşleşmesi gerekir; güvenli rastgele dosya adları kullanılır.

## Kontroller

Yeni testler; işletmeler arası erişimi, yanlış araç bağlantısını, atanmamış QR'ı, silinmiş aracı, çalışan atamasını, fotoğraf sınırını, hatalı toplu yüklemenin geri alınmasını ve dosya içeriğinin yeniden kodlanmasını kapsar. Kurtarma testleri özel fotoğrafın yedekten geri gelmesini ve işlemin geri alınmasını da doğrular.
