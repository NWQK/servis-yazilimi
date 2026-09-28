<!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><meta name="theme-color" content="#e32228"><title>@yield('title') — {{ $profile->display_name }}</title><link rel="icon" type="image/svg+xml" href="{{ asset('images/brand/sanayirandevu-mark.svg') }}"><link rel="stylesheet" href="{{ asset('css/appointments.css') }}"><link rel="stylesheet" href="{{ asset('css/sanayi-customer.css') }}"></head>
<body class="booking-page sr-customer">@include('components.public-brand')<main class="booking-shell"><header class="booking-brand"><span class="booking-brand-icon" aria-hidden="true">↗</span><span>{{ $profile->display_name }}</span><span class="booking-location">İstanbul saati</span></header>
@yield('content')
<footer class="booking-footer">Randevular işletmenin onayından sonra kesinleşir.</footer></main></body></html>
