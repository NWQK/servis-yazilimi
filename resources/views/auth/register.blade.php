@extends('layouts.sanayi-auth')
@section('tab-title','Hesap oluştur')
@section('panel-label','İşletme hesabı oluşturma')
@php($settings = settings())
@push('script-page')
    @if($settings['google_recaptcha'] == 'on')
        {!! NoCaptcha::renderJs() !!}
    @endif
@endpush
@section('content')
<div class="login-heading">
    <span class="login-eyebrow">İŞLETMENİZ İÇİN YENİ BİR BAŞLANGIÇ</span>
    <h1>Servisinizin takibi<br>tek yerde olsun.</h1>
    <p>İşletme hesabınızı oluşturun; müşterilerinizi, araçlarınızı ve servis işlemlerinizi kolayca yönetin.</p>
</div>
<form action="{{ route('register') }}" method="post" id="register-Form" class="login-form">
    @csrf
    @foreach(['error','success','status'] as $notice)
        @if(session($notice))<div class="login-message {{ $notice === 'error' ? 'login-message-error' : '' }}" role="{{ $notice === 'error' ? 'alert' : 'status' }}">{{ session($notice) }}</div>@endif
    @endforeach
    <div class="login-field">
        <label for="name">Adınız ve soyadınız</label>
        <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" maxlength="255" placeholder="Adınız ve soyadınız" required @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
        @error('name')<p class="login-error" id="name-error" role="alert">{{ $message }}</p>@enderror
    </div>
    <div class="login-field">
        <label for="email">E-posta adresi</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" maxlength="255" placeholder="ornek@isletmeniz.com" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
        @error('email')<p class="login-error" id="email-error" role="alert">{{ $message }}</p>@enderror
    </div>
    <div class="login-field">
        <label for="password">Şifre</label>
        <input id="password" name="password" type="password" autocomplete="new-password" placeholder="Şifrenizi oluşturun" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
        @error('password')<p class="login-error" id="password-error" role="alert">{{ $message }}</p>@enderror
    </div>
    <div class="login-field">
        <label for="password_confirmation">Şifre tekrar</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Şifrenizi tekrar girin" required @error('password_confirmation') aria-invalid="true" aria-describedby="password-confirmation-error" @enderror>
        @error('password_confirmation')<p class="login-error" id="password-confirmation-error" role="alert">{{ $message }}</p>@enderror
    </div>
    <div class="login-options">
        <label class="login-remember" for="agree"><input id="agree" name="agree" type="checkbox" value="1" @checked(old('agree')) required><span><a href="{{ route('page', $menu->slug ?? \App\Services\TermsAndConditions::SLUG) }}" target="_blank" rel="noopener">Şartlar ve koşulları</a> okudum ve kabul ediyorum.</span></label>
    </div>
    @if($settings['google_recaptcha'] == 'on')
        <div class="login-captcha">{!! NoCaptcha::display() !!}</div>
        @error('g-recaptcha-response')<p class="login-error" role="alert">{{ $message }}</p>@enderror
    @endif
    <p class="login-register">Demo 14 gün ve 50 araç ile sınırlıdır. Son girişten itibaren 30 gün giriş yapılmayan ücretsiz demo hesapları ve işletme kayıtları kalıcı olarak silinir.</p>
    <button type="submit" class="login-submit">Hesap oluştur <span aria-hidden="true">↗</span></button>
    <p class="login-register">Zaten hesabınız var mı? <a href="{{ route('login') }}">Giriş yapın</a></p>
</form>
<div class="login-customer"><span>Aracınız için servis mi arıyorsunuz?</span><a href="{{ route('directory.index') }}">Randevu alın <span aria-hidden="true">→</span></a></div>
@endsection
