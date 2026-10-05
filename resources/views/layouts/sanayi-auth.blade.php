<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e32228">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sanayi Randevu — @yield('tab-title')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/brand/sanayirandevu-mark.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/sanayi-auth.css') }}">
    <script src="{{ asset('js/sanayi-auth.js') }}" defer></script>
</head>
<body>
<a class="login-skip" href="#login-content">Giriş formuna geç</a>
<main class="login-shell">
    <aside class="login-story" aria-label="Sanayi Randevu servis yönetimi">
        <a class="login-brand" href="{{ route('marketing.index') }}"><img src="{{ asset('images/brand/sanayirandevu-logo-light.svg') }}" width="290" height="41" alt="sanayirandevu.com"></a>
        <div class="login-story-content">
            <span class="login-story-tag"><span></span> RANDEVUDAN FATURAYA</span>
            <h2>İşinizin ustasısınız.<br><em>Takibi bize bırakın.</em></h2>
            <p>Müşteriler, araçlar, servis işleri ve stoklar.<br>İşletmenizin günlük akışı tek panelde.</p>
            <div class="login-preview" aria-hidden="true">
                <div class="login-preview-header"><span class="login-preview-dots">● ● ●</span><span>Servisiniz tek yerde</span><span>↗</span></div>
                <div class="login-preview-body">
                    <div class="login-preview-title"><div><small>GÜNLÜK AKIŞ</small><strong>Her adım kontrolünüzde.</strong></div><span class="login-preview-check">✓</span></div>
                    <div class="login-preview-row"><span class="login-step">01</span><div><strong>Randevu talebi</strong><small>Müşteriniz saatini seçer.</small></div><span class="login-preview-badge">Randevu</span></div>
                    <div class="login-preview-row"><span class="login-step">02</span><div><strong>Servis kaydı</strong><small>İşlemler ve ürünler kaydedilir.</small></div><span class="login-preview-badge">Servis</span></div>
                    <div class="login-preview-row"><span class="login-step">03</span><div><strong>Fatura ve takip</strong><small>Müşteriniz bağlantıdan görüntüler.</small></div><span class="login-preview-badge">Fatura</span></div>
                </div>
            </div>
        </div>
        <div class="login-story-footer"><span>Otomobil · Motosiklet · Ağır vasıta</span><span>sanayirandevu.com</span></div>
    </aside>
    <section class="login-panel" id="login-content" aria-label="İşletme girişi">
        <header class="login-panel-header"><a class="login-brand" href="{{ route('marketing.index') }}"><img src="{{ asset('images/brand/sanayirandevu-logo.svg') }}" width="254" height="36" alt="sanayirandevu.com"></a><a class="login-back" href="{{ route('marketing.index') }}">Siteye dön <span aria-hidden="true">↗</span></a></header>
        <div class="login-form-wrap">@yield('content')</div>
        <footer class="login-panel-footer">© {{ date('Y') }} Sanayi Randevu</footer>
    </section>
</main>
@stack('script-page')
</body>
</html>
