@extends('appointments.layout')
@section('title', 'Randevu talebiniz')
@section('content')
@if($appointment->requested_service)<p class="booking-muted">{{ $appointment->requested_vehicle }} · {{ $appointment->requested_service }}</p>@endif
<div class="booking-hero"><p class="booking-eyebrow">RANDEVU TAKİBİ</p><h1>{{ \App\Models\Appointment::statuses()[$appointment->status] }}</h1>
<p>@switch($appointment->status)
@case('pending')Talebiniz işletmeye ulaştı. İşletmenin onayı bekleniyor.@break
@case('approved')Randevunuz işletme tarafından onaylandı.@break
@case('rejected')İşletme bu talebi kabul edemedi. Başka bir saat için talep oluşturabilirsiniz.@break
@case('cancelled')Bu randevu iptal edildi.@break
@case('expired')İşletme belirtilen süre içinde yanıt vermediği için talebiniz kapandı. Uygun bir saat için yeniden talep oluşturabilirsiniz.@break
@case('completed')Randevunuz tamamlandı. Bizi tercih ettiğiniz için teşekkür ederiz.@break
@case('no_show')İşletme bu randevuya katılım olmadığını belirtti.@break
@endswitch</p></div>
@if (session('success'))<div class="booking-alert" role="status">{{ session('success') }}</div>@endif
@if ($errors->any())<div class="booking-alert" role="alert">{{ $errors->first() }}</div>@endif
<section class="booking-panel"><h2>{{ dateFormat($appointment->starts_at) }}</h2><p class="booking-status-time">{{ timeFormat($appointment->starts_at) }}–{{ timeFormat($appointment->ends_at) }}</p><p>{{ $profile->display_name }}</p>
    @if ($appointment->status === 'pending' && $appointment->pending_expires_at)<p>Son yanıt zamanı: <strong>{{ dateFormat($appointment->pending_expires_at) }} {{ timeFormat($appointment->pending_expires_at) }}</strong>. Onay gelmezse talep otomatik kapanır.</p>@endif
    @if ($appointment->canCustomerCancel())
        <p>Bu randevuyu {{ dateFormat($appointment->customer_cancel_until) }} {{ timeFormat($appointment->customer_cancel_until) }} tarihine kadar iptal edebilirsiniz.</p>
        <form method="post" action="{{ route('booking.cancel', [$profile->public_id, $appointment->public_token]) }}">@csrf
            <label><input type="checkbox" required> Randevumu iptal etmek istiyorum.</label>
            <button class="booking-button" type="submit">Randevuyu iptal et</button>
        </form>
    @elseif (in_array($appointment->status, ['pending','approved']))<p>Çevrimiçi iptal süresi doldu. İptal için işletmeyle iletişime geçin.</p>@endif
    <hr><label for="status-link">Kişisel takip bağlantınız</label><input id="status-link" readonly value="{{ $appointment->statusUrl() }}">
    <p class="booking-muted">Bu sayfayı yer imlerine ekleyebilir veya bağlantısını saklayabilirsiniz. Randevunuzun güncel durumunu buradan takip edebilirsiniz. Bu bağlantıya sahip olanlar randevu durumunuzu görebilir ve izin verilen süre içinde iptal edebilir. Bağlantıyı başkalarıyla paylaşmayın.</p>
    <a class="booking-button" href="{{ route('booking.status', [$profile->public_id, $appointment->public_token]) }}">Durumu yenile</a> <a class="booking-secondary" href="{{ route('booking.show', $profile->public_id) }}">Randevu sayfasına dön</a>
</section>
@endsection
