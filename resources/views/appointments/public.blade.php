@extends('appointments.layout')
@section('title', 'Randevu al')
@section('content')
<div class="booking-hero"><p class="booking-eyebrow">SİZE UYGUN BİR ZAMAN</p><h1>Servis randevunuzu planlayın.</h1><p>Tarih ve saat seçin, talebinizi işletmeye iletin. Üyelik gerekmez.</p></div>
@if ($errors->any())<div class="booking-alert" role="alert">{{ $errors->first() }}</div>@endif
@if (!$profile->is_active)
    <section class="booking-panel"><h2>Şu anda yeni randevu alınmıyor.</h2><p>Lütfen daha sonra tekrar kontrol edin.</p></section>
@else
<div class="booking-columns"><section class="booking-panel"><h2><span class="booking-step">1</span> Tarih seçin</h2>
    <form method="get" action="{{ route('booking.show', $profile->public_id) }}"><label for="booking-date">Randevu günü</label><input id="booking-date" name="date" type="date" required value="{{ $date }}" min="{{ today()->toDateString() }}" max="{{ today()->addDays(90)->toDateString() }}"><button class="booking-button" type="submit">Uygun saatleri göster</button></form>
    <div class="booking-help"><strong>Nasıl çalışır?</strong><p>Her randevu aralığı 1 saattir. Talep gönderince saat sizin için ayrılır. İşletme onayladığında randevunuz kesinleşir.</p><p>SMS bildirimi henüz kullanılmıyor. Talep sonunda verilen bağlantıyı saklayarak durumunuzu takip edin.</p></div>
</section><section class="booking-panel"><h2><span class="booking-step">2</span> {{ dateFormat($date) }}</h2>
@if (count($hours))
    <form method="post" action="{{ route('booking.store', $profile->public_id) }}">@csrf
        <input type="hidden" name="date" value="{{ $date }}"><input type="hidden" name="request_key" value="{{ old('request_key', $requestKey) }}">
        <fieldset class="booking-slots"><legend>Uygun saatler</legend>@foreach ($hours as $hour)<label class="booking-slot"><input type="radio" name="hour" value="{{ $hour }}" required @checked((string) old('hour', '') === (string) $hour)><span>{{ sprintf('%02d:00', $hour) }}</span></label>@endforeach</fieldset>
        <label for="customer_name">Ad soyad</label><input id="customer_name" name="customer_name" autocomplete="name" required maxlength="150" value="{{ old('customer_name') }}">
        <label for="phone">Telefon numarası</label><input id="phone" type="tel" name="phone" autocomplete="tel" required maxlength="25" placeholder="0555 123 45 67" value="{{ old('phone') }}">
        <label for="license_plate">Plaka <span class="booking-muted">(isteğe bağlı)</span></label><input id="license_plate" name="license_plate" maxlength="20" value="{{ old('license_plate') }}">
        <label for="notes">İhtiyacınızı kısaca yazın <span class="booking-muted">(isteğe bağlı)</span></label><textarea id="notes" name="notes" maxlength="1000" rows="3" placeholder="Örneğin yağ bakımı veya fren kontrolü">{{ old('notes') }}</textarea>
        <div class="booking-trap" aria-hidden="true"><label>Web sitesi<input name="website" tabindex="-1" autocomplete="off"></label></div>
        <button class="booking-button" type="submit">Randevu talebi gönder</button><p class="booking-muted">Bu işlem onay talebi oluşturur; kesinleşmiş randevu değildir.</p>
    </form>
@else
    <div class="booking-empty"><h3>Bu gün için uygun saat yok.</h3><p>İşletme kapalı olabilir veya saatler dolmuş olabilir. Başka bir tarih seçebilirsiniz.</p></div>
@endif
</section></div>
@endif
@endsection
