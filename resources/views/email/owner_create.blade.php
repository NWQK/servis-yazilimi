@extends('email.layout')
@section('message')
<p>Merhaba {{ $content['message']['name'] }},</p><p>sanayirandevu.com hesabınız oluşturuldu.</p><p>Kullanıcı adınız: {{ $content['message']['email'] }}</p><p><a href="{{ $content['message']['url'] }}">Hesabınıza giriş yapın</a></p>
@endsection
