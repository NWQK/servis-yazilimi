@extends('layouts.sanayi-auth')
@section('tab-title','Şifremi unuttum')
@section('content')
<div class="login-heading"><span class="login-eyebrow">HESAP GÜVENLİĞİ</span><h1>Şifrenizi<br>yenileyelim.</h1><p>Hesabınıza kayıtlı e-posta adresini girin. Şifrenizi yenilemeniz için bir bağlantı göndereceğiz.</p></div>
<form action="{{ route('password.email') }}" method="post" class="login-form">
    @csrf
    @foreach (['error','success','status'] as $notice)
        @if(session($notice))<div class="login-message {{ $notice === 'error' ? 'login-message-error' : '' }}" role="{{ $notice === 'error' ? 'alert' : 'status' }}">{{ session($notice) }}</div>@endif
    @endforeach
    <div class="login-field"><label for="email">E-posta adresi</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required placeholder="ornek@isletmeniz.com" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>@error('email')<p class="login-error" id="email-error" role="alert">{{ $message }}</p>@enderror</div>
    <button type="submit" class="login-submit">Sıfırlama bağlantısı gönder <span aria-hidden="true">↗</span></button>
    <div class="login-options"><a href="{{ route('login') }}">Giriş ekranına dön</a></div>
</form>
@endsection
