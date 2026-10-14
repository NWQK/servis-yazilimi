<!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><meta name="referrer" content="no-referrer"><meta name="theme-color" content="#e32228"><title>Araç servis geçmişi · sanayirandevu.com</title><link rel="icon" href="{{ asset('images/brand/sanayirandevu-mark.svg') }}"><link rel="stylesheet" href="{{ asset('css/sanayi-customer.css') }}"><link rel="stylesheet" href="{{ asset('css/vehicle-history.css') }}"></head>
<body class="sr-customer">@include('components.public-brand',['directoryHeader'=>true])
<main class="history-shell"><header class="history-hero"><span class="history-eyebrow">ARACINIZIN SERVİS KAYITLARI</span><h1>Geçmişine bak.<br><span>Bilerek yola çık.</span></h1><p>Şasi numarasıyla sanayirandevu.com üzerinde paylaşılan tamamlanmış servis işlemlerini sorgulayın.</p><a href="{{ route('directory.index') }}">Servis bul ve randevu al →</a></header>
<section class="history-search" aria-labelledby="search-title"><h2 id="search-title">Şasi numarasıyla sorgula</h2><form method="post" action="{{ route('vehicle-history.search') }}">@csrf<label for="history-vin">Şasi numarası (VIN)</label><div class="history-input-row"><input id="history-vin" name="chassis_no" value="{{ old('chassis_no') }}" type="text" maxlength="64" required autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="Ruhsattaki şasi numarasını yazın" aria-describedby="vin-help"><button type="submit">Geçmişi sorgula <span aria-hidden="true">→</span></button></div><p id="vin-help">Numarayı ruhsattan kontrol edin. Boşluklar ve tireler otomatik temizlenir. Araç kayıtları ilgili işletmelerde kalır.</p>@error('chassis_no')<p class="history-error" role="alert">{{ $message }}</p>@enderror</form></section>
<aside class="history-notice" aria-labelledby="notice-title"><span aria-hidden="true">!</span><div><h2 id="notice-title">Kayıtların doğruluğu hakkında</h2><p>{{ \App\Services\PublicVehicleHistory::DISCLAIMER }}</p></div></aside>
@if($results)
<section class="history-results" aria-labelledby="results-title"><div class="history-result-heading"><div><span class="history-eyebrow">ŞASİ SONU · •••• {{ $search['suffix'] }}</span><h2 id="results-title">{{ number_format($results->total(),0,',','.') }} servis kaydı</h2></div><span class="history-badge">Paylaşılan tamamlanmış işlemler</span></div>
@forelse($results as $row)
<article class="history-entry"><div class="history-date">{{ $row->service_date ? dateFormat($row->service_date) : 'Tarih belirtilmedi' }}</div><div class="history-work"><h3>{{ $row->business_name }}</h3><ul>@forelse($row->operations as $operation)<li>{{ $operation }}</li>@empty<li>İşlem türü belirtilmedi</li>@endforelse</ul></div><div class="history-mileage"><span>Servis kilometresi</span><strong>{{ $row->mileage_at_service!==null ? number_format($row->mileage_at_service,0,',','.') . ' km' : 'Belirtilmedi' }}</strong></div></article>
@empty
<div class="history-empty"><h3>Paylaşılmış tamamlanmış servis kaydı bulunamadı.</h3><p>Bu sonuç aracın hiç işlem görmediği anlamına gelmez. Kayıtlar paylaşılmamış olabilir veya şasi numarası farklı girilmiş olabilir.</p></div>
@endforelse
@include('vehicle_portal.pagination',['paginator'=>$results,'anchor'=>'results-title'])
</section>
@else
<section class="history-empty"><h2>Yalnızca paylaşılan işlem özetleri.</h2><p>Müşteri adı, iletişim bilgileri, faturalar, tutarlar, borçlar ve işletmelerin özel notları bu sorguda gösterilmez.</p></section>
@endif
<footer class="history-footer">sanayirandevu.com · Bilgilendirme amaçlı servis geçmişi</footer></main></body></html>
