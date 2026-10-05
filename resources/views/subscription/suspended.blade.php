@extends('layouts.sanayi-auth')
@section('tab-title','Abonelik askıya alındı')
@section('content')
<div class="login-heading"><h1>Aboneliğiniz askıya alındı</h1><p>İşletmenizin panel erişimi geçici olarak durduruldu. Yeniden etkinleştirme için yöneticiyle iletişime geçin.</p></div>
<p>Müşteri, araç ve işlem kayıtlarınız korunmaktadır.</p>
<form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="login-submit">Çıkış yap</button></form>
@endsection
