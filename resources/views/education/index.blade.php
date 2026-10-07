<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#e32228">
    <title>Eğitim rehberi · sanayirandevu.com</title>
    <link rel="icon" href="{{ asset('images/brand/sanayirandevu-mark.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/sanayi-education.css') }}?v={{ filemtime(public_path('css/sanayi-education.css')) }}">
    <script defer src="{{ asset('js/sanayi-education.js') }}?v={{ filemtime(public_path('js/sanayi-education.js')) }}"></script>
</head>
<body>
<a href="#rehber" class="learn-skip">İçeriğe geç</a>
<header class="learn-header">
    <div class="learn-wrap learn-header-row">
        <a href="{{ route('education.index') }}" aria-label="Sanayi Randevu eğitim rehberi"><img src="{{ asset('images/brand/sanayirandevu-logo.svg') }}" width="260" height="36" alt="sanayirandevu.com"></a>
        <a class="learn-button learn-button-outline" href="{{ route('dashboard') }}">Panele dön <span aria-hidden="true">↗</span></a>
    </div>
</header>
<main>
    <section class="learn-hero">
        <div class="learn-wrap learn-hero-grid">
            <div>
                <p class="learn-kicker"><span aria-hidden="true">●</span> İŞLETMENİZİN KULLANIM REHBERİ</p>
                <h1>İlk kayıttan<br><em>son tahsilata.</em></h1>
                <p class="learn-intro">Hangi işlem nereden yapılır, kayıtlar birbirine nasıl bağlanır? İşletmenizi adım adım hazırlayın, günlük işlerinizi doğru sırayla takip edin.</p>
                <div class="learn-hero-actions"><a href="#rehber" class="learn-button">Rehberi keşfet <span aria-hidden="true">↓</span></a><a href="#is-akisi" class="learn-text-link">İlk kez kullanıyorum →</a></div>
                <div class="learn-meta"><span>{{ $topics->count() }} konu</span><span>{{ count($groups) }} bölüm</span><span>Adım adım anlatım</span></div>
            </div>
            <div class="learn-flow" aria-label="Örnek iş akışı">
                <div class="learn-flow-top"><span class="learn-flow-dot" aria-hidden="true"></span> BİR İŞİN YOLCULUĞU <span class="learn-flow-tag">REHBER</span></div>
                @foreach([['01','Randevu','Günü planla, talebi incele.'],['02','Müşteri ve araç','Doğru kişiyi doğru araca bağla.'],['03','Servis','İşlemleri ve işçilikleri kaydet.'],['04','Fatura ve tahsilat','İçeriği kontrol et, ödemeyi kaydet.']] as $stage)
                    <div class="learn-flow-stage"><span>{{ $stage[0] }}</span><div><strong>{{ $stage[1] }}</strong><small>{{ $stage[2] }}</small></div><b aria-hidden="true">{{ $loop->last ? '✓' : '↓' }}</b></div>
                @endforeach
                <p class="learn-flow-note">Randevu, servis ve ödeme ayrı adımlardır.</p>
            </div>
        </div>
    </section>
    <div class="learn-wrap learn-start">
        <div><p class="learn-kicker">NEREDEN BAŞLAMALI?</p><h2>Önce temelini hazırlayın.</h2></div>
        <div class="learn-start-links">
            <a href="#isletme-bilgileri"><span>01</span> İşletme bilgileri <b aria-hidden="true">↗</b></a>
            <a href="#urun-kurulumu"><span>02</span> Ürün ve stok ayarları <b aria-hidden="true">↗</b></a>
            <a href="#randevu-kurulumu"><span>03</span> Randevu saatleri <b aria-hidden="true">↗</b></a>
            <a href="#servis-olusturma"><span>04</span> İlk servis kaydı <b aria-hidden="true">↗</b></a>
        </div>
    </div>
    <section class="learn-wrap learn-library" id="rehber" aria-label="Eğitim konuları">
        <div class="learn-library-heading"><div><p class="learn-kicker">GÜNLÜK İŞLER İÇİN EL KİTABI</p><h2>İhtiyacınız olan konuyu bulun.</h2></div><p>Bir başlığa dokunun. İşlem sırasını, dikkat edilecek noktaları ve kullanacağınız ekranı birlikte görün.</p></div>
        <div class="learn-search-row">
            <label class="learn-search" for="learn-search"><span aria-hidden="true">⌕</span><span class="learn-sr">Rehberde ara</span><input id="learn-search" type="search" placeholder="Örneğin: QR, ödeme, stok, işçilik…" autocomplete="off"></label>
            <p id="learn-results" aria-live="polite">{{ $topics->count() }} konu görüntüleniyor</p>
        </div>
        <div class="learn-layout">
            <nav class="learn-sidebar" aria-label="Rehber bölümleri">
                <button type="button" class="is-active" data-group="all" aria-pressed="true">Tüm konular <span>{{ $topics->count() }}</span></button>
                @foreach($groups as $key => $group)
                    <button type="button" data-group="{{ $key }}" aria-pressed="false">{{ $group['title'] }} <span>{{ $topics->where('group', $key)->count() }}</span></button>
                @endforeach
                <div class="learn-sidebar-note"><strong>Bir konuda takıldınız mı?</strong><p>Ekranı ve yapmak istediğiniz işlemi destek biletinde anlatabilirsiniz.</p><a href="{{ route('support.index') }}">Destek biletleri →</a></div>
            </nav>
            <div class="learn-content">
                @foreach($groups as $key => $group)
                    <section class="learn-group" data-section="{{ $key }}" aria-labelledby="group-{{ $key }}">
                        <div class="learn-group-heading"><span>{{ sprintf('%02d', $loop->iteration) }}</span><div><h2 id="group-{{ $key }}">{{ $group['title'] }}</h2><p>{{ $group['description'] }}</p></div></div>
                        @foreach($topics->where('group', $key) as $topic)
                        <details class="learn-topic" id="{{ $topic['id'] }}" data-topic-group="{{ $key }}" @if($topic['id'] === 'is-akisi') open data-default-open @endif>
                            <summary><h3>{{ $topic['title'] }}</h3><span class="learn-expand" aria-hidden="true">+</span></summary>
                            <div class="learn-topic-body">
                                <p class="learn-topic-intro">{{ $topic['intro'] }}</p>
                                <h4>Nasıl yapılır?</h4>
                                <ol class="learn-steps">@foreach($topic['steps'] as $step)<li>{{ $step }}</li>@endforeach</ol>
                                <aside class="learn-note"><h4>Bilmeniz gerekenler</h4><ul>@foreach($topic['notes'] as $note)<li>{{ $note }}</li>@endforeach</ul></aside>
                                @if($topic['links'])<div class="learn-topic-links">@foreach($topic['links'] as $link)<a href="{{ $link['url'] }}">{{ $link['label'] }} <span aria-hidden="true">↗</span></a>@endforeach</div>@endif
                            </div>
                        </details>
                        @endforeach
                    </section>
                @endforeach
                <div id="learn-empty" class="learn-empty" hidden><h3>Bu aramada konu bulunamadı.</h3><p>Daha kısa bir ifade deneyin veya tüm konulara dönün.</p><button type="button" id="learn-reset" class="learn-button">Aramayı temizle</button></div>
            </div>
        </div>
    </section>
    <section class="learn-bottom"><div class="learn-wrap"><div><p class="learn-kicker">ÖĞRENDİĞİNİZİ UYGULAYIN</p><h2>İşletmenizin paneli sizi bekliyor.</h2><p>Rehbere sağ üstteki bilgi simgesinden her zaman dönebilirsiniz.</p></div><a class="learn-button" href="{{ route('dashboard') }}">Panele dön →</a></div></section>
</main>
<footer class="learn-footer learn-wrap"><img src="{{ asset('images/brand/sanayirandevu-logo.svg') }}" width="220" height="30" alt="sanayirandevu.com"><span>İşletme eğitim rehberi · Mevcut sistem özellikleri</span><a href="#">Yukarı dön ↑</a></footer>
</body>
</html>
