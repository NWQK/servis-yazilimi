@extends('email.layout')
@section('message')
<p>Merhaba {{ $data['name'] }},</p><p>Kaydınızı tamamlamak için e-posta adresinizi doğrulayın.</p><p><a href="{{ $data['url'] }}">E-posta adresimi doğrula</a></p><p>Bu hesabı siz oluşturmadıysanız mesajı dikkate almayabilirsiniz.</p>
@endsection
