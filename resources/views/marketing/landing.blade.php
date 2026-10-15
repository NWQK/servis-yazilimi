<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sanayi Randevu — Randevudan faturaya, servisiniz tek yerde.</title>
    <meta name="description" content="Sanayi Randevu ile müşterilerinizi, araçlarınızı, randevularınızı, servis işlerinizi, faturalarınızı ve stoklarınızı tek panelden yönetin. Otomobil, motosiklet ve ağır vasıta servisleri için.">
    <meta name="theme-color" content="#e32228">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Sanayi Randevu — İşinizin ustasısınız. Takibi bize bırakın.">
    <meta property="og:description" content="Randevu, servis, fatura ve stok takibi. İşletmeniz için bir arada.">
    <meta property="og:url" content="{{ route('marketing.index') }}">
    <link rel="canonical" href="{{ route('marketing.index') }}">
    <link rel="icon" href="{{ asset('images/brand/sanayirandevu-mark.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/sanayi-landing.css') }}">
    <script defer src="{{ asset('js/sanayi-landing.js') }}"></script>
</head>
<body>
<a class="skip-link" href="#icerik">İçeriğe geç</a>
<header class="site-header">
    <div class="wrap nav-row">
        <a class="brand" href="{{ route('marketing.index') }}" aria-label="Sanayi Randevu tanıtım sayfası"><img src="{{ asset('images/brand/sanayirandevu-logo.svg') }}" width="260" height="36" alt="sanayirandevu.com"></a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-nav">Menü <span aria-hidden="true">☰</span></button>
        <nav id="main-nav" aria-label="Ana menü">
            <a href="{{ route('vehicle-history.index') }}">Araç geçmişi</a><a href="#ozellikler">Özellikler</a><a href="#nasil-calisir">Nasıl çalışır?</a><a href="#sorular">Sık sorulanlar</a>
            <a class="nav-login" href="{{ route('login') }}">İşletme girişi <span aria-hidden="true">↗</span></a>
            <a class="button button-small" href="{{ route('directory.index') }}">Servis bul <span aria-hidden="true">→</span></a>
        </nav>
    </div>
</header>
<main id="icerik">
    <section class="hero">
        <div class="wrap">
            <p class="eyebrow"><span class="red-dot"></span> SANAYİNİN İŞİ, ARTIK DAHA DÜZENLİ.</p>
            <h1>İşinizin ustasısınız.<br><span>Takibi bize bırakın.</span></h1>
            <p class="hero-description">Randevular, servis işleri, faturalar ve stoklar.<br class="desktop-break"> İşletmenizin bütün akışı, tek bir yerde.</p>
            <div class="hero-actions"><a class="button" href="{{ route('register') }}">İşletmem için başla <span aria-hidden="true">↗</span></a><a class="button button-outline" href="{{ route('directory.index') }}">Aracım için servis bul <span aria-hidden="true">→</span></a></div>
            <p class="hero-note">Otomobil <span> / </span> Motosiklet <span> / </span> Ağır vasıta</p>
            <div class="product-scene" aria-label="Servis yönetim paneli ve mobil randevu ekranı örnekleri">
                <div class="scene-note"><span aria-hidden="true">↘</span> Günün tamamı tek bakışta.</div>
                <div class="desktop-device">
                    <div class="browser-bar"><span class="browser-dots" aria-hidden="true">● ● ●</span><span>sanayirandevu.com / işletmem</span><span aria-hidden="true">↗</span></div>
                    <div class="dashboard-demo">
                        <aside class="demo-sidebar" aria-hidden="true"><img src="{{ asset('images/brand/sanayirandevu-logo.svg') }}" width="150" height="22" alt=""><span class="demo-menu-heading">İŞLETMEM</span><span class="demo-active">▦ &nbsp; Genel bakış</span><span>▤ &nbsp; Randevular</span><span>♧ &nbsp; Müşteriler</span><span>▱ &nbsp; Araçlar</span><span>⌁ &nbsp; Servisler</span><span>▧ &nbsp; Faturalar</span><span>▦ &nbsp; Ürünler ve stok</span><span>◷ &nbsp; Gelir / Gider</span><div class="demo-shop"><b>SR</b><span>Örnek Servis<small>İşletme paneli</small></span></div></aside>
                        <div class="demo-content">
                            <div class="demo-heading"><div><span class="demo-muted">İŞLETMENİZE GENEL BAKIŞ</span><h2>Günaydın, işler yolunda.</h2></div><span class="demo-date">24 Eyl 2026</span></div>
                            <div class="demo-stats"><div><span>Bugünkü randevular</span><strong>08 <small>randevu</small></strong><em>Günün planı hazır</em></div><div><span>Devam eden servisler</span><strong>12 <small>araç</small></strong><em>Her iş kontrol altında</em></div><div><span>Tahsil edilen tutar</span><strong>₺24.850</strong><em>Ödemeler kaydedildi</em></div></div>
                            <div class="demo-table"><div class="demo-table-title"><b>Bugünün servis akışı</b><span>Tüm servisler ↗</span></div><div class="demo-table-row table-label"><span>ARAÇ / MÜŞTERİ</span><span>İŞLEM</span><span>DURUM</span></div><div class="demo-table-row"><span><b>34 SR 2026</b><small>Örnek müşteri</small></span><span>Periyodik bakım</span><span class="pill pill-red">İşlemde</span></div><div class="demo-table-row"><span><b>54 SR 054</b><small>Örnek müşteri</small></span><span>Fren bakımı</span><span class="pill">Sıraya alındı</span></div><div class="demo-table-row"><span><b>06 SR 006</b><small>Örnek müşteri</small></span><span>Yağ değişimi</span><span class="pill pill-dark">Tamamlandı</span></div></div>
                            <div class="demo-bottom"><span><i class="red-dot"></i> Yeni randevu talebi</span><b>14.00 · Genel bakım <span>→</span></b></div>
                        </div>
                    </div>
                </div>
                <div class="phone-device"><div class="phone-island" aria-hidden="true"></div><div class="phone-screen"><div class="phone-top"><img src="{{ asset('images/brand/sanayirandevu-mark.svg') }}" width="28" height="28" alt=""><b>Randevu al</b></div><span class="phone-kicker">ÖRNEK SERVİS</span><h3>Size uygun<br>zamanı seçin.</h3><div class="phone-service">Genel bakım <span>✓</span></div><p class="phone-month">Eylül 2026 <span>‹ &nbsp; ›</span></p><div class="phone-days"><span>Pzt<b>21</b></span><span>Sal<b>22</b></span><span>Çar<b>23</b></span><span class="selected">Per<b>24</b></span></div><p class="phone-label">Uygun saatler</p><div class="phone-times"><span>09.00</span><span>10.00</span><span class="selected">14.00</span><span>15.00</span></div><div class="phone-submit">Randevu talebi oluştur →</div><p class="phone-caption">Üyelik gerekmez.</p></div></div>
                <div class="scene-badge"><span class="badge-check">✓</span><span>Randevu onaylandı<small>Müşterinize SMS ile bildirildi.</small></span></div>
            </div>
            <p class="mockup-disclaimer">Ekranlar, özellikleri anlatan temsili örneklerdir.</p>
        </div>
    </section>
    <div class="benefit-strip"><div class="wrap"><span><b>01</b> Randevuyu al</span><span><b>02</b> Servisi yönet</span><span><b>03</b> Faturanı oluştur</span><span><b>04</b> İşini takip et</span></div></div>

    <section class="section wrap" id="ozellikler">
        <div class="section-heading"><div><p class="eyebrow">DAHA AZ KARIŞIKLIK. DAHA ÇOK KONTROL.</p><h2>Kapıdan giren araçtan,<br>kasaya giren tutara.</h2></div><p>Müşteri kaydından tahsilata kadar her adımı birbirine bağlayın. Günlük işlerinizi aynı panelde takip edin.</p></div>
        <div class="feature-grid">
            @foreach ([
                ['01','Müşteri & araç','Her aracın bilgisi elinizin altında.','Yalnızca müşteri kaydedin veya müşteri, araç ve servisi birlikte oluşturun. Bir müşterinin birden fazla aracını; plaka, kilometre ve notlarıyla takip edin.','Müşteri kaydı / Araç geçmişi / Notlar'],
                ['02','Servis & işçilik','Hangi iş, hangi aşamada?','Servis kaydı açın, çalışan atayın ve işin durumunu takip edin. Tarih ve saatleri gerektiğinde boş bırakın. Aracın kayıtlı kilometresini servis formunda görün, güncel değerini girerek araç kaydını yenileyin. Ek işçilik tutarını vergisiyle birlikte faturaya yansıtın.','İş takibi / Çalışan atama / Ek işçilik'],
                ['03','Fatura & tahsilat','İşlemi faturaya, ödemeyi kayda alın.','Hizmet, ürün ve işçiliği tek faturada toplayın. İndirim uygulayın; ödemeyi ve kalan bakiyeyi takip edin. Borç takibi ekranında ödemesi tamamlanmayan faturaları listeleyin, doğrudan ödeme ekleyin veya yazdırın. İşletme bilgileriniz ve faturaya özel logonuz otomatik gelsin.','Ürün + hizmet / İndirim / Ödeme takibi'],
                ['04','Ürün & stok','Rafınızdakini bilin.','Ürünleri kategorilere ayırın. Faturaya eklenen ürün stoktan düşsün, çıkarılan ürün geri gelsin. Elden satışları da aynı stoktan yönetin. İsteğe bağlı stok uyarı miktarı belirleyin; azalan ürünleri bildirim kutusundan takip edin.','Kategori / Anlık stok hareketi / Elden satış'],
                ['05','Gelir & gider','İşletmenizin hesabı görünür olsun.','Ürün alımlarının maliyetini, tahsil edilen faturaları ve elden satışları takip edin. Gelir, gider ve kâr/zarar raporlarını tarih bazında inceleyin.','Alış giderleri / Tahsilatlar / Kâr-zarar'],
                ['06','Ekip & işletme','İşletmenize göre düzenleyin.','Çalışanları ve kullanıcı yetkilerini yönetin. İşletme bilgilerinizi, sunduğunuz hizmetleri ve randevu saatlerinizi kendiniz belirleyin.','Yetkiler / İşletme ayarları / Türkçe arayüz'],
                ['07','Müşteri değerlendirmeleri','Deneyimler yıldızlarla görünsün.','Müşteriye kayıt sırasında değerlendirme bağlantısı iletin. Araç ekranından da puanlama yapılabilsin. İşletmenizin ortalaması ve değerlendirme sayısı randevu sayfalarında görünsün.','1–5 yıldız / İşletme başına tek değerlendirme'],
                ['08','Hızlı iletişim','Müşteriye bir dokunuşla ulaşın.','Müşteri ve araç listelerinden WhatsApp görüşmesini açın. Araç bağlantısını kopyalayıp paylaşın. Fatura, araç ve değerlendirme bildirimlerini merkezi SMS ve e-posta altyapısından iletin.','WhatsApp / Bağlantı kopyalama / Merkezi iletişim'],
                ['09','Hazır kayıtlar','Her şeyi sıfırdan yazmayın.','Otomobil, motosiklet ve ağır vasıta için hazır marka ve modelleri seçerek yükleyin. Hazır vergi ve ürün birimlerinden yararlanın; kendi kayıtlarınızı da ekleyin.','Marka-model aktarımı / Vergiler / Ürün birimleri'],
                ['10','Araç temizliği','Kayıtlarınız düzenli kalsın.','Uzun süredir işlem yapılmayan araçları filtreleyin, servis sayılarını inceleyin ve toplu temizleme yapın. Müşteri ve araç temizliğinde faturalarınız kayıtlı kalsın.','3 / 6 / 12 aylık filtre / Toplu işlem'],
                ['11','Destek & bildirimler','Destek için panelden ayrılmayın.','Destek bileti açın, yöneticiyle yazışın ve gelen yanıtları bildirim kutunuzdan görün. İşletmenize iletilen duyuruları aynı panelde takip edin.','Destek bileti / Yanıt bildirimi / Duyurular'],
                ['12','Kurulum & güvenlik','Nereden başlayacağınız belli olsun.','Adım adım kurulum ekranında işletme bilgilerinizi, logonuzu ve temel ayarlarınızı tamamlayın. Hazır kayıtlarla hızlı başlayın; eğitim rehberinden yardım alın ve iki aşamalı girişle hesabınızı koruyun.','Adım adım kurulum / Eğitim rehberi / İki aşamalı giriş'],
                ['13','Tedarikçi defteri','Doğru kişiye kolayca ulaşın.','Firma, yetkili personel, telefon ve e-posta bilgilerini kaydedin. Ürüne dilerseniz tedarikçi bağlayın; stok azaldığında iletişim bilgilerini veya WhatsApp görüşmesini açın.','İsteğe bağlı seçim / İletişim bilgileri / WhatsApp'],
                ['14','Satın alım geçmişi','Stoğunuzun nasıl yenilendiğini görün.','Yeni alımı miktar, birim alış fiyatı, tarih ve notuyla kaydedin. Stok ve alış gideri birlikte güncellensin. Ürün veya tedarikçi silinse de alım bilgileri iki yıllık saklama süresince korunsun.','Stok alımı / Tek gider kaydı / 2 yıllık geçmiş'],
                ['15','Borç takibi','Bekleyen tahsilatları gözden kaçırmayın.','Hiç ödenmeyen ve kısmen ödenen faturaları tek ekranda görün. Fatura tutarını, ödeneni ve kalan borcu karşılaştırın; borcu kapanan faturalar listeden otomatik çıksın.','Kalan bakiye / Hızlı ödeme / Fatura yazdırma'],
                ['16','Şasiyle servis geçmişi','Aracın geçmiş işlemlerini görün.','Şasi numarasıyla paylaşıma açılmış, tamamlanan servisleri sorgulayın. İşletme, tarih, yapılan işlem ve kilometre bilgilerini görün. Müşteri bilgileri ve faturalar bu sorguda paylaşılmaz.','Şasi sorgulama / İşlem özeti / Paylaşım tercihi'],
                ['17','Yedekleme & kurtarma','Kayıtlarınız için bir güvence daha.','Kayıtlarınız otomatik yedeklensin. Veri kaybında destek üzerinden işletmenize özel kurtarma yapılabilsin; diğer işletmelerin kayıtları etkilenmesin.','Otomatik yedek / İşletmeye özel kurtarma'],
                ['18','Esnek araç kapasitesi','İşletmeniz büyüdükçe yer açın.','Paketinizi kayıtlı araç sayınıza göre seçin. 3.000 araçtan sonra ihtiyacınız oldukça 500 araçlık ek kapasite satın alın; her ek paketle toplam kapasitenizi artırın.','14 günlük demo / Aylık paketler / 500 araçlık ek kapasite']
            ] as [$number,$tag,$title,$description,$tags])
            <article class="feature-card"><div class="feature-top"><span>{{ $tag }}</span><span class="feature-number">{{ $number }}</span></div><h3>{{ $title }}</h3><p>{{ $description }}</p><small>{{ $tags }}</small></article>
            @endforeach
        </div>
    </section>

    <section class="booking-section" id="randevu">
        <div class="wrap split-section">
            <div class="section-copy"><p class="eyebrow">YENİ MÜŞTERİLERİNİZİN YOLU SİZE ÇIKSIN.</p><h2>Doğru servis.<br>Uygun zaman.<br><span>Kolay randevu.</span></h2><p>Müşteri ilini, taşıtını ve ihtiyacı olan hizmeti seçsin. Sunduğunuz hizmetlerle eşleşen aramalarda işletmenizi bulsun ve size randevu talebi göndersin.</p><ul class="check-list"><li>Otomobil, motosiklet ve ağır vasıtaya özel hizmetler</li><li>İl seçimi; İstanbul için Avrupa ve Anadolu yakası</li><li>İşletmenize özel, paylaşılabilir randevu bağlantısı</li><li>Puana, mesafeye veya randevu sayısına göre servis arama</li><li>Google konum bağlantınızla müşteriye kolay yol tarifi</li><li>Çalışma günleri ve saatlerinde tam kontrol</li><li>Üyelik gerektirmeyen, mobil uyumlu randevu akışı</li><li>En fazla bir hafta sonrası için randevu talebi</li></ul><a class="text-link" href="{{ route('directory.index') }}">Randevu ekranını keşfet <span aria-hidden="true">↗</span></a></div>
            <div class="booking-preview"><div class="preview-label"><span class="red-dot"></span> MÜŞTERİNİZİN GÖZÜNDEN</div><div class="booking-path"><span>01 Konum</span><b>02 Taşıt</b><span>03 Hizmet</span><span>04 Servis</span></div><h3>Ne kullanıyorsunuz?</h3><p>Taşıtınıza uygun hizmetlerle başlayın.</p><div class="vehicle-previews">
                <a href="{{ route('directory.index') }}" aria-label="Otomobil için il seçerek servis aramaya başlayın"><svg viewBox="0 0 120 70" aria-hidden="true"><path d="m12 39 15-22h47l19 22 15 7v14H10V45zM27 17l-5 22h71M55 18v21"/><circle cx="29" cy="57" r="9"/><circle cx="90" cy="57" r="9"/></svg><b>Otomobil <span>↗</span></b></a>
                <a href="{{ route('directory.index') }}" aria-label="Motosiklet için il seçerek servis aramaya başlayın"><svg viewBox="0 0 120 70" aria-hidden="true"><circle cx="25" cy="49" r="16"/><circle cx="96" cy="49" r="16"/><path d="m25 49 24-25h29l18 25M52 24l11 25H25m38 0 19-38h12M44 19h18"/></svg><b>Motosiklet <span>↗</span></b></a>
                <a href="{{ route('directory.index') }}" aria-label="Ağır vasıta için il seçerek servis aramaya başlayın"><svg viewBox="0 0 120 70" aria-hidden="true"><path d="M8 12h65v43H8zM73 28h21l18 17v10H73m9-27v17h30"/><circle cx="29" cy="54" r="9"/><circle cx="94" cy="54" r="9"/></svg><b>Ağır vasıta <span>↗</span></b></a>
            </div><div class="preview-services"><span>Yağ değişimi</span><span>Motor ve mekanik</span><span>Genel bakım</span><span>Fren sistemleri</span><span>Kaporta ve boya</span><span>Arıza tespiti ve ekspertiz</span></div><div class="preview-info"><span class="badge-check">✓</span><p>Talep size gelir, son söz sizdedir.<small>Randevular işletmenin onayıyla kesinleşir.</small></p></div></div>
        </div>
    </section>

    <section class="section wrap split-section qr-section">
        <div class="qr-preview"><div class="qr-card"><img src="{{ asset('images/brand/sanayirandevu-logo.svg') }}" width="205" height="29" loading="lazy" alt="Sanayi Randevu"><div class="qr-illustration" aria-hidden="true"><svg viewBox="0 0 100 100"><path d="M4 4h28v28H4zm64 0h28v28H68zM4 68h28v28H4z" fill="none" stroke="currentColor" stroke-width="7"/><path d="M12 12h12v12H12zm64 0h12v12H76zM12 76h12v12H12zM40 4h8v16h-8zm12 16h8v20H40v-8h12zM4 40h16v8H4zm24 0h8v20H16v-8h12zM44 48h16v8H44zm24-8h12v8H68zm20 0h8v20H76v-8h12zM40 64h8v24h12v8H40zm16 0h16v8H56zm24 4h16v8H80zm-20 12h12v16H60zm20 4h8v12h-8z"/></svg></div><strong>Aracınızın faturaları,<br>bir bağlantı uzağınızda.</strong><span>Temsili QR görseli</span></div><div class="invoice-float"><span class="document-icon">▤</span><div><b>Fatura hazır</b><small>Genel bakım · Örnek araç</small></div><span class="badge-check">✓</span></div><div class="qr-caption">Kâğıt aramaya son. <span>↗</span></div></div>
        <div class="section-copy"><p class="eyebrow">MÜŞTERİNİZLE BAĞINIZ SERVİSTE BİTMESİN.</p><h2>Bir QR kod.<br>Bütün faturalar.</h2><p>Müşteriniz QR kodu okutsun veya kendisine gönderilen bağlantıyı açsın. Aracına ait faturaları telefonundan görüntülesin.</p><ul class="check-list"><li>Önceden basılabilen, 10 adetlik boş QR havuzu</li><li>Kullanıldıkça otomatik tamamlanan QR stoğu</li><li>QR seçilmeden kaydedilen araçlara kalıcı bağlantı</li><li>Bağlantıyı sonradan QR olarak yazdırma</li><li>Yazdırılmış, boş QR kodlarını tekrar PDF alma</li><li>Araç sahibine SMS ile görüntüleme bağlantısı gönderme</li><li>Araç ekranından işletmeyi yıldızlarla değerlendirme</li><li>E-posta bilgisi olan müşteriye fatura bağlantısı iletme</li></ul></div>
    </section>

    <section class="control-section"><div class="wrap split-section"><div class="section-copy"><p class="eyebrow">SATIŞINIZ, STOĞUNUZ, HESABINIZ.</p><h2>Bir işlem yapın.<br>İlgili kayıtlar<br><span>birlikte ilerlesin.</span></h2><p>Ürünü faturaya eklediğinizde stok düşer. Ürünü çıkardığınızda stoğa geri döner. Gelir ise ödeme alındığında kayda geçer.</p><p>Fatura silerken ürünlerin stoğa dönmesini siz seçersiniz. Elden satışta adedi girerek hem stok hareketini hem satış gelirini kaydedersiniz.</p><div class="control-note">Alış maliyeti giderlere, tahsilatlar gelirlere.<br>İşletmenizin durumunu raporlarda görün.</div></div><div class="invoice-preview"><div class="invoice-top"><img src="{{ asset('images/brand/sanayirandevu-mark.svg') }}" width="35" height="35" alt=""><span>ÖRNEK FATURA <b>#SR-0024</b></span><span class="pill pill-dark">Ödendi</span></div><h3>Yapılan işler, tek faturada.</h3><div class="invoice-line"><span>Periyodik bakım<small>Servis hizmeti</small></span><b>₺1.500</b></div><div class="invoice-line"><span>Motor yağı<small>2 adet × ₺750</small></span><b>₺1.500</b></div><div class="invoice-line"><span>Ek işçilik</span><b>₺500</b></div><div class="invoice-line discount"><span>İndirim</span><b>−₺250</b></div><div class="invoice-total"><span>Toplam</span><strong>₺3.250</strong></div><div class="invoice-events"><span>✓ Stoktan 2 adet düşüldü</span><span>✓ Tahsilat gelirlere işlendi</span></div><p class="example-note">Örnek tutarlar; vergi hesaplaması gösterilmemiştir.</p></div></div></section>

    <section class="section wrap" id="nasil-calisir"><div class="section-heading"><div><p class="eyebrow">BAŞLAMAK İÇİN KARMAŞIK BİR YOLA GEREK YOK.</p><h2>İşletmeniz hazır.<br>Sıra düzenini kurmakta.</h2></div><p>Randevudan servis teslimine kadar, günlük iş akışınızla birlikte çalışan bir sistem.</p></div><div class="steps-grid"><article><span>01</span><h3>İşletmenizi tanımlayın.</h3><p>Hizmetlerinizi seçin, çalışma günlerinizi ve açık saatlerinizi belirleyin. Size özel randevu bağlantınız hazır olsun.</p></article><article><span>02</span><h3>Randevularınızı yönetin.</h3><p>Talepleri bildirim kutunuzdan görün ve onaylayın. SMS etkinse müşteriniz doğrulama ve onay mesajlarını alsın.</p></article><article><span>03</span><h3>İşinizi kayda alın.</h3><p>Müşteriyi ve aracı kaydedin, servisi açın, ürün ve işçilik ekleyin. Fatura, stok ve tahsilat takibini aynı yerde sürdürün.</p></article></div></section>

    <section class="sms-band wrap"><div class="sms-symbol" aria-hidden="true">↗</div><div><p class="eyebrow">HABER VEREN BİR SİSTEM.</p><h2>Randevudan değerlendirmeye, müşterinizle bağlantıda kalın.</h2><p>Randevu doğrulaması ve onayı, araç takip bağlantıları, fatura e-postaları ve değerlendirme davetleri merkezi altyapıdan gönderilir. İşletmenin ayrıca SMTP veya SMS hesabı kurması gerekmez. Gönderimler ve mesaj şablonları süper admin tarafından yönetilir; SMS ve e-posta ayrı ayrı açılıp kapatılabilir. Gönderim için ilgili kanalın etkin ve yapılandırılmış olması gerekir.</p></div></section>

    <section class="section wrap" id="merkezi-yonetim"><div class="section-heading"><div><p class="eyebrow">YÖNETİM DE AYNI DÜZENİN PARÇASI.</p><h2>İşletmeler işine baksın.<br>Altyapı merkezden yürüsün.</h2></div><p>İşletme paneli günlük işlere odaklanırken süper admin abonelikleri, iletişimi ve desteği tek noktadan yönetir.</p></div><div class="steps-grid"><article><span>01</span><h3>Abonelik ve kullanım.</h3><p>14 gün / 50 araçlık ücretsiz demo; aylık 500 araç 800 TL, 1.000 araç 1.400 TL, 2.000 araç 2.200 TL, 3.000 araç 3.000 TL; 3.000 araçtan sonra esnaf onayıyla aylık 500 TL karşılığında 500 araçlık ek kapasite. İşletme bazında kullanım özeti, abonelik düzenleme ve askıya alma.</p></article><article><span>02</span><h3>Duyuru ve destek.</h3><p>Tüm işletmelere veya seçilen işletmelere panel duyurusu gönderin; okunma durumunu takip edin. İşletme detayından bildirim iletin ve destek biletlerini yanıtlayın.</p></article><article><span>03</span><h3>İşlem geçmişi ve erişim.</h3><p>Yönetici işlemlerinin önceki ve sonraki değerlerini görün. İşletme girişlerinin son bir haftalık IP ve cihaz bilgilerini inceleyin; iki aşamalı doğrulamada sorun yaşayan işletmelere yardımcı olun.</p></article></div></section>

    <section class="section wrap faq-section" id="sorular"><div><p class="eyebrow">AKLINIZDA KALMASIN.</p><h2>Birkaç soruya<br>net cevap.</h2></div><div class="faq-list">
        @foreach ([
            ['Hangi işletmeler kullanabilir?','Otomobil, motosiklet ve ağır vasıta servisleri kullanabilir. İşletmeler, hizmet verdikleri taşıt türlerini ve sundukları hizmetleri seçer. Müşteriler genel randevu sayfasında bu seçimlere göre işletmeleri bulur.'],
            ['Müşterinin randevu almak için üye olması gerekir mi?','Hayır. Müşteri uygun gün ve saati seçerek iletişim bilgileriyle talep oluşturabilir. SMS doğrulaması açıksa telefonuna gelen kodu girer. Talep, işletme onayladığında kesinleşir.'],
            ['Kendi randevu bağlantımı paylaşabilir miyim?','Evet. İşletmenize özel bağlantıyı sosyal medya hesaplarınıza ve işletme profilinize ekleyebilir, müşterilerinizle doğrudan paylaşabilirsiniz.'],
            ['QR kod kullanmak zorunlu mu?','Hayır. Araç kaydında QR seçmezseniz sisteme özel bir görüntüleme bağlantısı oluşturulur. Bu bağlantıyı daha sonra QR olarak yazdırabilirsiniz.'],
            ['Ürünü faturaya eklediğimde stok ne zaman düşer?','Ürün faturaya kaydedildiğinde stok düşer; ödeme beklenmez. Miktarı azaltırsanız veya ürünü kaldırırsanız ilgili adet stoğa döner. Fatura silinirken stok iadesini siz seçersiniz.'],
            ['Telefonumdan randevu alabilir miyim?','Evet. Genel servis arama ve işletmeye özel randevu ekranları mobil kullanıma uygundur. Müşterileriniz il, taşıt, hizmet ve işletme adımlarından ilerleyerek randevu talep edebilir.'],
            ['Müşteri işletmeyi nasıl değerlendirebilir?','Müşteri kaydı oluşturulduğunda etkin iletişim kanallarından özel değerlendirme bağlantısı gönderilir. Müşteri araç bağlantısındaki “İşletmeyi değerlendir” butonunu da kullanabilir. Üyelik gerekmez; aynı müşteri aynı işletmeye bir kez 1–5 yıldız verebilir.'],
            ['E-posta ve SMS ayarlarını kendim mi yapmalıyım?','İşletmeler için ayrıca SMTP veya SMS hesabı yapılandırması gerekmez. Gönderimler, şablonlar ve açma-kapatma ayarları platform yönetimi tarafından merkezi olarak yönetilir.'],
            ['Yardıma ihtiyacım olursa ne yapabilirim?','Paneldeki eğitim rehberi ve kurulum uyarıları sizi ilgili ayarlara yönlendirir. Destek bileti açarak yöneticiyle doğrudan yazışabilir, yanıtları bildirim kutunuzdan takip edebilirsiniz.'],
            ['Stok uyarısı ve tedarikçi seçimi zorunlu mu?','Hayır. Ürün eklerken tedarikçi seçmeyebilir ve stok uyarı miktarını boş bırakabilirsiniz. Eşik girerseniz stok bu miktara veya altına indiğinde bildirim oluşur; stok yenilenince uyarı kalkar.'],
            ['Şasi sorgusunda hangi bilgiler görünür?','Yalnızca paylaşıma açılan, tamamlanmış servislerin işletmesi, tarihi, işlem özeti ve kilometresi görünür. Müşteri iletişim bilgileri, faturalar ve özel notlar gösterilmez. Kayıtlar işletmelerin girişlerine dayanır; eksiksiz veya yüzde yüz doğrulanmış araç geçmişi sayılmaz.'],
            ['Sistemdeki faturalar e-Fatura yerine geçer mi?','Bu sayfada anlatılan özellik servis içi fatura ve tahsilat takibidir. GİB e-Fatura veya e-Arşiv entegrasyonu mevcut özellikler arasında bulunmaz.']
        ] as [$question,$answer])<details><summary>{{ $question }}<span aria-hidden="true">+</span></summary><p>{{ $answer }}</p></details>@endforeach
    </div></section>

    <section class="final-cta wrap"><p class="eyebrow">İŞİNİZİN YENİ DÜZENİ.</p><h2>Sanayide iş çok.<br><span>Takibi kolay olsun.</span></h2><div class="hero-actions"><a class="button button-white" href="{{ route('register') }}">İşletmem için başla <span aria-hidden="true">↗</span></a><a class="button button-ghost" href="{{ route('directory.index') }}">Servis bul, randevu al <span aria-hidden="true">→</span></a></div></section>
</main>
<footer class="site-footer wrap"><div><a class="brand" href="{{ route('marketing.index') }}"><img src="{{ asset('images/brand/sanayirandevu-logo.svg') }}" width="260" height="36" loading="lazy" alt="sanayirandevu.com"></a><p>Servisinizin işi, müşterinizin zamanı.</p></div><nav aria-label="Alt menü"><a href="{{ route('page', \App\Services\TermsAndConditions::SLUG) }}">Şartlar ve koşullar</a><a href="#ozellikler">Özellikler</a><a href="{{ route('directory.index') }}">Randevu al</a><a href="{{ route('login') }}">İşletme girişi</a></nav><div class="footer-bottom"><span>© {{ date('Y') }} Sanayi Randevu</span><span>Türkiye için. Türkçe. ₺.</span></div></footer>
</body>
</html>
