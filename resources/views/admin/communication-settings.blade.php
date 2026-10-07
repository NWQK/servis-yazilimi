@extends('layouts.app')
@section('page-title','E-posta ve SMS yönetimi')
@section('content')
<div class="card"><div class="card-body">
<h5>Merkezi iletişim sistemi</h5><p class="text-muted">Bu anahtarlar tüm işletmeler için geçerlidir. API bilgileri, SMTP ayarları ve şablonlar korunur. Yeniden açıldığında sonraki işlemler için gönderim devam eder; kapalı dönemde atlanan mesajlar otomatik gönderilmez.</p>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('communication-settings.save') }}">@csrf
<div class="border rounded p-3 mb-3"><input type="hidden" name="email_enabled" value="0"><label class="form-check form-switch"><input class="form-check-input" type="checkbox" name="email_enabled" value="1" @checked(old('email_enabled',$emailEnabled))><span class="form-check-label">E-posta sistemi aktif</span></label><p class="small text-muted mb-0">Kapatıldığında fatura, hesap bildirimi, test ve şifre sıfırlama dahil tüm e-postalar durur. Şifre sıfırlama bağlantısı e-postayla alınamaz.</p></div>
<div class="border rounded p-3 mb-3"><input type="hidden" name="sms_enabled" value="0"><label class="form-check form-switch"><input class="form-check-input" type="checkbox" name="sms_enabled" value="1" @checked(old('sms_enabled',$smsEnabled))><span class="form-check-label">SMS sistemi aktif</span></label><p class="small text-muted mb-0">Kapatıldığında tüm SMS gönderimleri durur. Randevular SMS doğrulaması olmadan alınabilir. Yeniden açıldığında kayıtlı doğrulama tercihi geçerli olur.</p></div>
<button class="btn btn-secondary">Durumu kaydet</button>
<a class="btn btn-outline-secondary" href="{{ route('setting.index',['tab'=>'email_SMTP_settings']) }}">SMTP ayarları</a>
<a class="btn btn-outline-secondary" href="{{ route('appointments.sms-settings') }}">SMS ayarları ve şablonlar</a>
</form></div></div>
@endsection
