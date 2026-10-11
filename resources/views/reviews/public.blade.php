<!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title>İşletmeyi değerlendir — Sanayi Randevu</title><link rel="icon" href="{{ asset('images/brand/sanayirandevu-mark.svg') }}"><link rel="stylesheet" href="{{ asset('css/sanayi-customer.css') }}"><link rel="stylesheet" href="{{ asset('css/business-reviews.css') }}"></head>
<body class="sr-customer">@include('components.public-brand', ['portalHeader'=>true])<main class="review-shell"><section class="review-card"><span class="review-label">DENEYİMİNİZ DEĞERLİ</span><h1>{{ $company }}</h1>
@if($review->rating)
<div class="review-success" role="status">Değerlendirmeniz kaydedildi. Teşekkür ederiz.</div><p class="review-score">{{ str_repeat('★', $review->rating) }}<span>{{ str_repeat('☆', 5-$review->rating) }}</span></p><p>Bu işletmeye {{ $review->rating }} / 5 puan verdiniz.</p><p class="review-muted">Her müşteri bir işletmeyi bir kez değerlendirebilir.</p>
@else
<p>Aldığınız hizmeti nasıl değerlendirirsiniz?</p><p class="review-muted">Henüz hizmet almadıysanız bu bağlantıyı hizmetinizden sonra kullanabilirsiniz.</p>
@if($errors->any())<p role="alert" class="review-error">Lütfen 1 ile 5 arasında bir puan seçin.</p>@endif
<form method="post" action="{{ route('reviews.store',$review->token) }}">@csrf<fieldset><legend>Puanınızı seçin</legend><div class="review-stars">@for($i=1;$i<=5;$i++)<label><input type="radio" name="rating" value="{{ $i }}" required @checked((int)old('rating')===$i)><span aria-hidden="true">★</span><small>{{ $i }} yıldız</small></label>@endfor</div></fieldset><button type="submit">Değerlendirmeyi gönder</button><p class="review-muted">Gönderdikten sonra puanınız değiştirilemez.</p></form>
@endif
</section><footer>sanayirandevu.com · Bu bağlantı size özeldir; paylaşmayın.</footer></main></body></html>
