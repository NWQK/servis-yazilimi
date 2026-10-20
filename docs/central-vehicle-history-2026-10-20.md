# Merkezi şasi sorgu yönetimi — 20 Ekim 2026

Taban: VDS'e alınmış `aec9fb9d199fcfd0d1f439f1c4e60619d32fddb8`.
Yerel branch: `feature/admin-vehicle-history-control-2026-10-20`.
Bu çalışma canlı sunucuda otomatik çalıştırılmadı; Flutter/APK değişikliği içermez.

## Davranış

- Geçerli şasi numaralı araçların tamamlanmış servisleri otomatik görünür.
  İşletme onayı/paylaşım anahtarı kaldırıldı. Eski formdaki `history_public`
  parametresi müşteri/araç oluşturma ve düzenlemede görünürlüğü değiştiremez.
  Eski paylaşım POST adresi 403 döndürür; servis katmanındaki eski share çağrısı
  da işletme tarafından kullanılamaz.
- Süper admin → Ayarlar → **Şasi sorgu yönetimi**
  (`/admin/vehicle-history`). Araç kaydını inceleyip gerekçeli gizleyebilir veya
  yeniden açabilir. Sahibi, personeli, müşterisi ve şube merkezi erişemez.
  Gate izinlerinin verilmesi süper admin hesap türü şartını aşmaz.
- Karar araç kaydına uygulanır; aynı şasiyi başka işletmede kaydetmiş aracın
  geçmişi kendi kaydı olarak kalır. Araç/işletme sahipliği değiştirilmez.
- Görünen alanlar: işletme, kayıtlı marka/model, plakanın **yalnız son üç
  karakteri**, servis tarihi, kayıtlı servis kilometresi ve işlem türleri.
  Örnek: `Ford Focus · Plaka: ••• 987`. Tam plaka, müşteri, e-posta, telefon,
  tutar, borç ve özel notlar sorgu yanıtına alınmaz.

## Saklanan özet ve koruma

`public_service_history` her kaynak servis için tek satır tutar. Şasi açık metin
yerine SHA-256 ile, plaka yalnız üç karakterle saklanır. Başka işletmeye ait
katalog kimliğiyle marka/model veya işlem türü okunamaz.

Özet ilk tamamlanma/yayımlanma anındaki kimliği, işletmeyi, marka/modeli,
maskeli plakayı, tarihi ve kilometreyi korur. Sonradan kaydedilen işlem satırları
eklenebilir; yayımlanmış türler işletme tarafından silinemez. En çok 100 tür,
her biri 120 karakter saklanır. Tekrarlanan senkronizasyon yeni satır üretmez.

Şasi/araç/işletme değişikliği eski özeti başka kimliğe taşımaz. Servis durumu
geriye alınsa, servis/araç/işletme kaydı silinse veya işletme yedeği geri yüklense
de yayımlanmış özet kaldırılmaz. Hatalı bir yayımlama için süper adminin inceleyip
gizlemesi gerekir. Bu özellik kaynak kayıtlarının gerçekten doğru olduğunu
kanıtlamaz; mevcut eksik/hatalı veri uyarısı korunur.

`vehicle_history_controls` yönetici görünürlüğü, gerekçesi, aktörü ve revizyonunu
tutar. İki yöneticinin eski sürümle işleminde biri 409 alır. Kararlar yönetici işlem
geçmişine yazılır. İşletme kurtarma kapsamına bu iki platform tablosu alınmaz;
tam veritabanı yedeğinin parçasıdırlar. İşletme kurtarma sırasında o işletmenin
sorgu sonuçları mevcut kurtarma kilidiyle geçici olarak saklanır.

## Migration / mevcut veri

`2026_10_20_000001_centralize_public_vehicle_history` iki tabloyu ekler ve mevcut
geçerli şasili tamamlanmış servisleri, daha önce işletme paylaşımı kapalı olsa da
yeni otomatik görünürlük kuralına göre doldurur. Çöp kutusundaki araçların mevcut
tamamlanmış servisleri de kapsanır. Daha önce tamamen silinmiş kayıtlar üretilemez.
Geçmişte kaydedilmemiş kilometre güncel araç kilometresinden uydurulmaz.
Marka/model ve işletme adı eski kayıtların geçiş anındaki mevcut bilgisidir.

Kaynak müşteri, araç, servis, fatura, stok ve ödeme kayıtları değiştirilmez.
Şartlar sayfasının yalnız ilgili sorgu bölümü ve varsayılan bilgilendirme metni
güncellenir; mevcut sayfanın yayın anahtarı korunur. Migration yarıda kalırsa
tabloları/kararları silmeden tekrar denenebilir. Yayımlanmış kayıtları yanlışlıkla
silmemek için migration down otomatik tablo silmez, kontrollü geri dönüş ister.

Dağıtım öncesi güncel veritabanı/storage/.env yedeği alınmalı; mevcut veri kurtarma
işi tamamlanmalı ve yazmalar durdurulmalıdır. Yeni kod alındıktan sonra normal
izin düzeniyle `php artisan migrate --force`, cache yenileme ve PHP-FPM reload
gerekir. Kayıt hacmine göre ilk doldurma zaman alabilir. Eski koda dönmek yeni
yönetici kontrollerini uygulamaz; yalnız kodu geri alıp siteyi açmak bu yeni
görünürlük politikasını koruyan bir geri dönüş değildir. Veritabanını otomatik
geri yüklemek de sonraki kayıtları kaybettirebilir; bakım altında değerlendirilmelidir.

## Doğrulama

- Geniş web/mobil regresyonu: **513 test / 6.088 doğrulama**, başarılı.
- Şasi, kurtarma ve mobil odaklı paket: **76 test / 750 doğrulama**, başarılı.
- Son ek rol/migration-tekrar ve şartlar paketi: **31 test / 312 doğrulama**,
  başarılı. Paketler kısmen örtüşür, sayılar toplanmamalıdır. Rotalar ayrı geçici
  dosyaya başarıyla serialize/cache edildi.
- Ayrı MariaDB **10.4.32**: eski şemadan migration, kapalı eski kaydın otomatik
  doldurulması, 220 karakter işletme adı, marka/model ve maskeli plaka doğrulandı.
  Aynı servise iki eşzamanlı capture tek özet üretti; iki eşzamanlı eski revizyon
  yönetici isteği 200 + 409 verdi. Şasi değişimi/silme gizlemeyi aşamadı; yönetici
  yeniden açınca eski şasiye bağlı özetler döndü.
- Üretim MySQL 8 yük testi ve canlı geçiş yapılmadı. Gerçek mail/SMS/push gönderilmedi.
  İlk MariaDB denemesi yerel soket erişimine takıldı; izin sonrası ayrı yeni test
  dizininde tekrarlandı, önceki test verileri sıfırlanmadı.

Son rol testinin ilk fixture'ında şube merkezi oluşturma koruması ve şube seçimi
kapısı test verisini reddetti; hesaplar yetkili yönetici bağlamında oluşturuldu.
Controller hesap türü kontrolü, şube middleware'i testte ayrıca atlanarak da
doğrulandı. Üretimdeki hesap/şube korumaları gevşetilmedi.
