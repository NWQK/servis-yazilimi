@extends('layouts.sanayi-auth')
@section('tab-title', 'İşletme girişi')
@php($settings = settings())
@push('script-page')
    @if ($settings['google_recaptcha'] == 'on')
        {!! NoCaptcha::renderJs() !!}
    @endif
@endpush
@section('content')
<div class="login-heading">
    <span class="login-eyebrow">İŞLETME PANELİ</span>
    <h1>İşiniz kaldığı yerden<br>devam etsin.</h1>
    <p>Servisinizi yönetmek için hesabınıza giriş yapın.</p>
</div>
<form action="{{ route('login') }}" method="post" id="loginForm" class="login-form">
    @csrf
    @foreach (['error', 'success', 'status'] as $message)
        @if (session($message))<div class="login-message {{ $message === 'error' ? 'login-message-error' : '' }}" role="{{ $message === 'error' ? 'alert' : 'status' }}">{{ session($message) }}</div>@endif
    @endforeach
    <div class="login-field">
        <label for="email">E-posta adresi</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="ornek@isletmeniz.com" autocomplete="username" inputmode="email" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
        @error('email')<p class="login-error" id="email-error" role="alert">{{ $message }}</p>@enderror
    </div>
    <div class="login-field">
        <label for="password">Şifre</label>
        <div class="login-password">
            <input id="password" name="password" type="password" placeholder="Şifrenizi girin" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
            <button type="button" id="toggle-password" aria-label="Şifreyi göster" aria-pressed="false" aria-controls="password" hidden><svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></button>
        </div>
        @error('password')<p class="login-error" id="password-error" role="alert">{{ $message }}</p>@enderror
    </div>
    <div class="login-options">
        <label class="login-remember" for="remember"><input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))> Beni hatırla</label>
        @if(Route::has('password.request'))<a href="{{ route('password.request') }}">Şifremi unuttum</a>@endif
    </div>
    @if($settings['google_recaptcha'] == 'on')
        <div class="login-captcha">{!! NoCaptcha::display() !!}</div>
        @error('g-recaptcha-response')<p class="login-error" role="alert">{{ $message }}</p>@enderror
    @endif
    <button type="submit" class="login-submit">Giriş yap <span aria-hidden="true">↗</span></button>
    @if(getSettingsValByName('register_page') == 'on')
        <p class="login-register">Henüz hesabınız yok mu? <a href="{{ route('register') }}">Hesap oluşturun</a></p>
    @endif
</form>
<div class="login-customer"><span>Aracınız için servis mi arıyorsunuz?</span><a href="{{ route('directory.index') }}">Randevu alın <span aria-hidden="true">→</span></a></div>
@endsection
