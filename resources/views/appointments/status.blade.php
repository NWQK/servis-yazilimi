@extends('appointments.layout')
@section('title', 'Randevu talebiniz')
@section('content')
<div class="booking-hero"><p class="booking-eyebrow">RANDEVU TAKİBİ</p><h1>{{ \App\Models\Appointment::statuses()[$appointment->status] }}</h1>
<p>@switch($appointment->status)
@case('pending')Talebiniz işletmeye ulaştı. İşletmenin onayı bekleniyor.@break
@case('approved')Randevunuz işletme tarafından onaylandı.@break
@case('rejected')İşletme bu talebi kabul edemedi. Başka bir saat için talep oluşturabilirsiniz.@break
@case('cancelled')Bu randevu iptal edildi.@break
@case('completed')Randevunuz tamamlandı. Bizi tercih ettiğiniz için teşekkür ederiz.@break
@case('no_show')İşletme bu randevuya katılım olmadığını belirtti.@break
@endswitch</p></div>
<section class="booking-panel"><h2>{{ dateFormat($appointment->starts_at) }}</h2><p class="booking-status-time">{{ timeFormat($appointment->starts_at) }}–{{ timeFormat($appointment->ends_at) }}</p><p>{{ $profile->display_name }}</p>
    <hr><label for="status-link">Kişisel takip bağlantınız</label><input id="status-link" readonly value="{{ $appointment->statusUrl() }}">
    <p class="booking-muted">Bu sayfayı yer imlerine ekleyebilir veya bağlantısını saklayabilirsiniz. SMS gönderilmiyor. Bu bağlantıya sahip olanlar randevu durumunuzu görebilir.</p>
    <a class="booking-button" href="{{ route('booking.status', [$profile->public_id, $appointment->public_token]) }}">Durumu yenile</a> <a class="booking-secondary" href="{{ route('booking.show', $profile->public_id) }}">Randevu sayfasına dön</a>
</section>
@endsection
