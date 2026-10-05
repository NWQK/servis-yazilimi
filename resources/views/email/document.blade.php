@extends('email.layout')
@section('message')
<p>{{ is_array($content) ? ($content['message'] ?? '') : $content }}</p>
@endsection
