@extends('layouts.sanayi-auth')
@section('tab-title', 'İki aşamalı doğrulama')
@section('content')
<div class="login-heading"><span class="login-eyebrow">HESAP GÜVENLİĞİ</span><h1>Hesabınızı doğrulayın.</h1><p>Doğrulama uygulamanızdaki altı haneli kodu girin. Telefonunuza erişemiyorsanız bir kurtarma kodu kullanabilirsiniz.</p></div>
@if(session('error'))<div class="login-message login-message-error" role="alert">{{ session('error') }}</div>@endif
<form method="POST" action="{{ route('otp.check') }}" class="login-form">@csrf
    <div class="login-field"><label for="otp-code">Doğrulama veya kurtarma kodu</label>
    <input id="otp-code" name="otp" type="text" maxlength="32" autocomplete="one-time-code" placeholder="6 haneli kod" required autofocus>
    @error('otp')<p class="login-error" role="alert">{{ $message }}</p>@enderror</div>
    <button class="login-submit" type="submit">Doğrula ve giriş yap <span aria-hidden="true">↗</span></button>
</form>
<p class="login-note">Kod kabul edilmiyorsa telefonunuzun tarih ve saat ayarını otomatik yapın ve yeni kodu bekleyin.</p>
<form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="login-submit" style="margin-top:16px;background:#252a32">Giriş ekranına dön</button></form>
@endsection