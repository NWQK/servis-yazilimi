<!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title>@yield('title') — {{ $profile->display_name }}</title><link rel="stylesheet" href="{{ asset('css/appointments.css') }}"></head>
<body class="booking-page"><main class="booking-shell"><header class="booking-brand"><span class="booking-brand-icon" aria-hidden="true">↗</span><span>{{ $profile->display_name }}</span><span class="booking-location">İstanbul saati</span></header>
@yield('content')
<footer class="booking-footer">Randevular işletmenin onayından sonra kesinleşir.</footer></main></body></html>
