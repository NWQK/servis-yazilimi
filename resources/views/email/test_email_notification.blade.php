@extends('email.layout')
@section('message')
<p>{{ $data['message'] }}</p><p>Bu mesajı aldıysanız SMTP sunucusu e-postayı gönderim için kabul etmiştir.</p>
@endsection
