@extends('appointments.layout')
@section('title', 'Telefonunuzu doğrulayın')
@section('content')
<div class="booking-hero"><p class="booking-eyebrow">SMS DOĞRULAMASI</p><h1>Telefonunuzu doğrulayın.</h1><p>{{ $maskedPhone }} numarasına gönderilen 6 haneli kodu girin.</p></div>
<section class="booking-panel">
    @if ($errors->any())<div class="booking-alert" role="alert">{{ $errors->first() }}</div>@endif
    @if (session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if ($challenge->send_status === 'failed')<div class="booking-alert">SMS gönderilemedi. Bir dakika sonra yeniden deneyebilirsiniz.</div>
    @elseif (in_array($challenge->send_status, ['sending', 'unknown']))<p>SMS gönderimi henüz kesinleşmedi. Kod geldiyse aşağıya girin; gelmediyse bir dakika sonra yeni kod isteyin.</p>@endif
    <p>Kod 5 dakika geçerlidir. Telefon doğrulanana kadar saat ayrılmaz ve işletmeye talep iletilmez.</p>
    <form method="post" action="{{ route('booking.verify.submit', [$profile->public_id, $challenge->token]) }}">@csrf
        <label for="code">SMS onay kodu</label><input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required>
        <button class="booking-button" type="submit">Kodu doğrula ve talebi ilet</button>
    </form>
    <form method="post" action="{{ route('booking.verify.resend', [$profile->public_id, $challenge->token]) }}" style="margin-top:16px">@csrf<button class="booking-secondary" type="submit">Yeni kod gönder</button></form>
    <p class="booking-muted">Yeni kod için en az 60 saniye bekleyin. Yeni kod gönderildiğinde önceki kod geçersiz olur.</p>
    <a href="{{ route('booking.show', $profile->public_id) }}">Telefonu veya randevuyu değiştir</a>
</section>
@endsection
