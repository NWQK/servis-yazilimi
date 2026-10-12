<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="sanayirandevu.com işletme paneli, randevu, müşteri bağlantıları ve temsili belgeler için kullanım koşulları.">
    <title>Şartlar ve koşullar · sanayirandevu.com</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/brand/sanayirandevu-mark.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/sanayi-legal.css') }}">
</head>
<body>
<a class="skip-link" href="#kosullar">İçeriğe geç</a>
<header class="legal-header"><div class="legal-wrap legal-nav">
    <a href="{{ route('marketing.index') }}" aria-label="Sanayi Randevu ana sayfa"><img src="{{ asset('images/brand/sanayirandevu-logo.svg') }}" width="260" height="36" alt="sanayirandevu.com"></a>
    <a class="legal-login" href="{{ route('login') }}">İşletme girişi <span aria-hidden="true">↗</span></a>
</div></header>
<main class="legal-wrap">
    <section class="legal-hero"><p class="legal-eyebrow">AÇIK VE ANLAŞILIR.</p><h1>{{ $page->title ?? \App\Services\TermsAndConditions::TITLE }}</h1><p>Platformun nasıl çalıştığı, kullanım sınırları ve karşılıklı sorumluluklarımız.</p><div class="legal-meta"><span>Kullanım koşulları</span><span>{{ $page && !\App\Services\TermsAndConditions::isLegacy($page->content) && $page->updated_at ? $page->updated_at->locale('tr')->translatedFormat('d M Y') : \App\Services\TermsAndConditions::VERSION }}</span></div></section>
    <div class="legal-notice"><strong>Belgeler hakkında</strong><p>Platformda hazırlanan faturalar temsili belgelerdir. Resmî fatura, e-Fatura veya e-Arşiv Fatura yerine geçmez.</p></div>
    @php
        preg_match_all('/<h2\s+id="([a-z0-9-]+)"[^>]*>(.*?)<\/h2>/su', $content, $headings, PREG_SET_ORDER);
    @endphp
    <div class="legal-layout">
        <aside class="legal-sidebar"><details open><summary>Bu sayfada</summary><nav aria-label="Koşulların başlıkları">@foreach($headings as $heading)<a href="#{{ $heading[1] }}">{{ strip_tags($heading[2]) }}</a>@endforeach</nav></details><div class="legal-help"><strong>Bir sorunuz mu var?</strong><p>Platformla ilgili sorularınız için bize yazabilirsiniz.</p><a href="mailto:iletisim@sanayirandevu.com">iletisim@sanayirandevu.com</a></div></aside>
        <article id="kosullar" class="legal-content">{!! $content !!}</article>
    </div>
    <section class="legal-end"><div><h2>İşlemlerinizi güvenle takip edin.</h2><p>İşletme paneline dönün veya size uygun servisi bulun.</p></div><a class="legal-button" href="{{ route('directory.index') }}">Randevu al <span aria-hidden="true">→</span></a></section>
</main>
<footer class="legal-footer legal-wrap"><span>© {{ date('Y') }} sanayirandevu.com</span><a href="{{ route('marketing.index') }}">Ana sayfa</a><a href="mailto:iletisim@sanayirandevu.com">İletişim</a></footer>
</body>
</html>
