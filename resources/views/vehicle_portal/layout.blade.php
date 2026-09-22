<!doctype html>
<html lang="tr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><meta name="referrer" content="no-referrer"><title>@yield('title', 'Araç Servis Kaydı')</title><link rel="stylesheet" href="{{ asset('css/vehicle-portal.css') }}"><script src="{{ asset('js/vehicle-portal.js') }}" defer></script></head>
<body><main class="shell"><header class="site-header"><span class="eyebrow">ARAÇ SERVİS KAYDI</span><p>{{ $settings['company_name'] ?? 'Araç Servis Kaydı' }}</p></header>@yield('content')<footer>Bu bağlantı aracınızın servis ve fatura kayıtlarına erişim sağlar. Yalnızca güvendiğiniz kişilerle paylaşın.</footer></main></body>
</html>
