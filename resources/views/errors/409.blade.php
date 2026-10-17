<!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sayfayı yeniden açın · sanayirandevu.com</title><link rel="stylesheet" href="{{ asset('css/sanayi-customer.css') }}"><style>main{max-width:620px;margin:10vh auto;padding:24px}h1{font-size:32px}p{line-height:1.7;color:#67686f}a{display:inline-block;color:#c90018;margin-top:12px}</style></head>
<body class="sr-customer">@include('components.public-brand')
<main><h1>Bu sayfanın bilgileri değişti.</h1><p>{{ $exception->getMessage() ?: 'Eski form üzerinden işlem yapılmadı. Güncel bilgileri görmek için sayfayı yeniden açın.' }}</p>
@if(auth()->user()?->type === 'branch_manager')<a href="{{ route('branches.portal') }}">Şubelerim ekranına dön</a>@else<a href="{{ route('dashboard') }}">Ana sayfaya dön</a>@endif
</main></body></html>
