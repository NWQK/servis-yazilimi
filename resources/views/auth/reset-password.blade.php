@extends('layouts.sanayi-auth')
@section('tab-title','Yeni şifre oluştur')
@section('content')
<div class="login-heading"><span class="login-eyebrow">HESAP GÜVENLİĞİ</span><h1>Yeni bir şifre,<br>güvenli bir başlangıç.</h1><p>Hesabınız için en az 8 karakterli yeni bir şifre oluşturun.</p></div>
<form action="{{ route('password.update') }}" method="post" class="login-form">
    @csrf
    <input type="hidden" name="token" value="{{ $request->route('token') }}">
    @foreach (['error','success','status'] as $notice)
        @if(session($notice))<div class="login-message {{ $notice === 'error' ? 'login-message-error' : '' }}" role="{{ $notice === 'error' ? 'alert' : 'status' }}">{{ session($notice) }}</div>@endif
    @endforeach
    <div class="login-field"><label for="email">E-posta adresi</label><input id="email" type="email" name="email" value="{{ old('email', $request->query('email')) }}" autocomplete="email" inputmode="email" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>@error('email')<p class="login-error" id="email-error" role="alert">{{ $message }}</p>@enderror</div>
    <div class="login-field"><label for="password">Yeni şifre</label><input id="password" type="password" name="password" minlength="8" autocomplete="new-password" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>@error('password')<p class="login-error" id="password-error" role="alert">{{ $message }}</p>@enderror</div>
    <div class="login-field"><label for="password_confirmation">Yeni şifre (tekrar)</label><input id="password_confirmation" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></div>
    <button type="submit" class="login-submit">Şifremi güncelle <span aria-hidden="true">↗</span></button>
    <div class="login-options"><a href="{{ route('login') }}">Giriş ekranına dön</a></div>
</form>
@endsection
