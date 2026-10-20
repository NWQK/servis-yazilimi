# Mobil detay işlemleri — 20 Ekim 2026

Branch: `feature/mobile-detail-actions`, başlangıç `aec9fb9d`. Ana Laravel kopyası ve başka sohbetin çalışma dosyaları değiştirilmedi.

## Kapsam ve sözleşme

Tüm uçlar `/api/v1` altında tam MobileSession denetimine tabidir. Mobil sahibi/personeli kendi işletmesi içinde çalışır. Ayrı şubeye geçiş eklenmedi.

- `GET actions/{customers|vehicles|invoices}/{id}/form`: izinli alanlar, seçim etiketleri ve güncel sürüm.
- `PUT actions/{customers|vehicles|invoices}/{id}`: müşteri iletişim/adres/not, araç alanları veya fatura satırlarını kaydeder.
- `GET actions/lookups/{kind}`: izinli sabit seçim listeleri; sunucuda arama ve 20 kayıtlık sayfalama.
- `POST actions/invoices/{id}/preview`: mevcut InvoiceChangePreview ile gerçek işlemi savepoint içinde simüle eder ve geri alır.
- `GET actions/invoices/{id}/payment-form`, `POST actions/invoices/{id}/payments`: kalan borç ve manuel nakit/kart/havale tahsilatı.

Yazmalarda UUID Idempotency-Key zorunludur. Aynı anahtar ve gövde aynı sonucu döndürür; farklı içerik 409. Müşteri/araç expected_version alanı değiştirilebilir kayıtların SHA256 özetidir. Fatura invoice_revision ve zorunlu, oyuncu/işletme/fatura/gövde/güncel stok durumuna bağlı önizleme tokenı kullanır. Tekrar kontrolü eski sürüm kontrolünden önce, ortak işletme işlem kilidi içinde yapılır. Finans/stock kodu Dart'a taşınmadı.

Web controller'larının mevcut işlem gövdeleri InvoiceEditing, ManualInvoicePayment, VehicleEditing ve CustomerContact ortak servislerine çıkarıldı. Web doğrulama/redirect davranışı korunur; müşteri API'si webdeki müşteri+araç+servis birleşik formunu çağırmaz. Araç API'si status/history_public/QR yazma yetkisi vermez. Şasi değişimi ortak onay ve benzersizlik kurallarını kullanır. Fatura değişimleri ortak InvoiceJournal'a kaydedilir.

Detay DTO'sundaki `actions` eklenebilir alanı eski mobil okuyucuları bozmaz. Düzenleme/ödeme ayrı izinlidir. WhatsApp URL'si yalnızca işletmeye ait müşteri telefonundan üretilir; uygulama görüşmeyi dış uygulamada açar, mesajı otomatik göndermez.

Yeni migration `2026_10_20_120000_create_mobile_detail_operations.php` sadece tekrar koruma tablosunu ekler. Üretime otomatik dağıtım yapılmadı. Eski payment_status/transaction_id/payment_type/receipt alanları eksikse mobil ödeme 503 ile kapanır; gerçek ortamın mevcut yükseltme şeması denetlenmelidir. `tools/mobile-local.php seed-detail-actions` yalnızca kendi sentetik SQLite verisini/uyumluluk alanlarını hazırlar; depo .env dosyasını yüklemez.

## Doğrulama

197 Laravel testi / 2.605 doğrulama geçti: MobileApi, ClientOnly, InventoryAccounting, CashIntegrityAndOutbox, UsageStatistics, ReferralsDeparturesPreview, VehicleCatalog/Qr/Cleanup/Capacity, PublicVehicleHistory. Yerel sentetik Laravel HTTP sunucusuna Flutter istemcisiyle fatura önizleme, düzenleme, aynı anahtarla tekrar, ödeme, müşteri güncelleme ve çıkış testi geçti. Gerçek SMS/e-posta/push gönderilmedi.

## Birleştirme notu

Başka sohbetin `feature/admin-vehicle-history-control-2026-10-20` çalışma kopyasında bu çalışma sırasında commit edilmemiş ClientController/VehicleController ve araç geçmişi değişiklikleri vardı. Buraya taşınmadı. O çalışma birleştirilirken ortak servislere çıkarılan ilgili yöntemler elle gözden geçirilmeli; yöneticiye alınan geçmiş paylaşımı tekrar işletme API'sine açılmamalı.

## Kalan kapsam

Tüm web eşdeğerliği tamamlanmadı. QR/etiket/PDF/yazdırma, silme/arşiv, ödeme silme/banka onayı, servis/teklif/fatura oluşturma, diğer katalog yönetim formları bu değişikliğin kapsamında değildir. Canlı VDS ve gerçek Android/WhatsApp testi yapılmadı; iOS askıda.
