# Mobil randevu yönetimi ve araç ekleme

18 Ekim 2026. Yerel/test geliştirmesi; canlıya kurulmadı.

## Randevular

- `GET /api/v1/appointments`: `q`, `date` (Y-m-d), `status`, `page`, `per_page`
  (en fazla 50); işletme sahibine ait sayfalı liste. Önce bekleyen/onaylı işler.
- `GET /api/v1/appointments/{id}`: mevcut detay alanlarına ek olarak `version`,
  `actions`, talep edilen hizmet ve son yanıt zamanı döner. Eski mobil istemcinin
  kullandığı alanlar korunur. Eylemleri mobil hesaplamaz; Laravel döndürür.
- `POST /api/v1/appointments/{id}/status`: UUID `Idempotency-Key` başlığı,
  `status` ve `expected_version` gerekir. Onay, ret, iptal, tamamlandı ve gelmedi
  işlemleri mevcut AppointmentBooking üzerinden yürür.
- Değişmiş sürüm 409; geçersiz zaman/durum geçişi 422. Aynı anahtar ve içerik
  tekrarında yeni durum işlemi/SMS başlatılmaz, kaydın güncel hali döndürülür.
  Daha sonra iptal edilmiş randevu eski onay isteğiyle tekrar açılmaz.
- Süresi veya saati geçmiş pending kayıt GET'te expired sunulur/filtrelenir;
  GET veriyi değiştirmez. Web/scheduler mevcut expirePending işini sürdürür.
- Tam Sanctum/2FA, hesap, abonelik, bakım ve kurtarma güvenlik kapıları geçerlidir.
  Personel randevu yönetemez. Başka işletme kaydı 404 döner.
- Onayda SMS mevcut servisin kuralıyla commit sonrası değerlendirilir. Yerel
  harness dış HTTP'yi engeller; gerçek SMS/e-posta/push gönderilmez.

Flutter ana sayfada Randevular kartı ve Bildirimler ekranında tüm listeye giriş
bulunur. Durum değişikliği onay ister. Belirsiz sonuçta işlem içeriği ve anahtar
güvenli depoda korunur; kullanıcı açıkça kontrol eder, otomatik çevrimdışı yazma
kuyruğu yoktur. Okunamayan güvenli depo yeni yazmayı engeller. API yenileme ve
işlem retry durumları ayrı gösterilir.

Çalışma saati/randevu profil ayarları bu aşamada hâlâ webdedir.

## Araç ekleme

- `GET /api/v1/lookups/vehicle-customers`, `vehicle-types`,
  `vehicle-brands?type={id}`: `q`, `page`, isteğe bağlı `id`; 20 kayıtlık sayfalar.
  Yalnız yetkili kullanıcının işletmesi; marka ayrıca seçili türle sınırlandırılır.
- `POST /api/v1/vehicles`: UUID `Idempotency-Key`; müşteri, tür, marka, plaka ve
  şasi gerekir. Renk, yakıt, kilometre ve notlar isteğe bağlıdır.
- `create vehicle` yetkisi zorunlu. İşletme kimliği oturumdan alınır; istemcinin
  parent_id, status, QR veya diğer kapsam dışı alanları kayda taşınmaz.
- VehicleRegistration hem web hem API için kayıt kurallarını/oluşturmayı paylaşır.
  Webin mevcut cevap, e-posta ve yönlendirme akışı korunur. API'nin kısa formu
  webin bütün ek alanlarını veya e-posta şablonu akışını henüz sunmaz.
- ChassisNumber şasi normalizasyonu/benzersizliği; VehicleQrPool kapasite,
  işletme kilidi, QR oluşturma/atama ve mevcut araç kayıt SMS kuralını uygular.
- İdempotency kontrolü tekrar VIN benzersizlik doğrulamasından önce yapılır.
  Böylece başarılı kaydın tekrarı aynı araç kimliğini verir, ikinci QR ayırmaz.
- Araç silinince işlem kaydı web temizliğini engellemez. Saklanan araç kimliği
  tombstone olarak kalır; eski istek silinen aracı yeniden oluşturmaz, 409 verir.

Flutter ana sayfada müşteri/araç ekleme kısayolları ve Araçlar listesinde Araç ekle
düğmesi vardır. Seçiciler bütün müşterileri/katalogları indirmez. Boş katalogda
gerekli tanımların webden eklenmesi gerektiği açıklanır. Belirsiz kayıt güvenli
depoda aynı içerik/anahtarla tutulur; çıkışta ilgili yerel kayıt temizlenir.

## Yerel sentetik ortam

Takipli `.env` yüklenmeden, yalnız izole SQLite için:

```
php -d extension=intl tools/mobile-local.php upgrade
php -d extension=intl tools/mobile-local.php seed-vehicle-form
```

İkinci komut yalnız esnaf@example.test sentetik hesabına araç ekleme izni ve
örnek araç türü/marka ekler; mevcut müşteri/araç/servisleri silmez.

Gerçek cihaz testleri, iOS derlemesi, performans ölçümü ve canlı kurulum yapılmadı.
Servis oluşturma, fatura/tahsilat, stok ve rapor modülleri sonraki kapsamda.
