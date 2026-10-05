@extends('layouts.app')
@section('page-title', 'Paketler ve abonelik')
@section('breadcrumb')
<li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Ana sayfa</a></li>
<li class="breadcrumb-item">Paketler ve abonelik</li>
@endsection
@section('content')
@include('subscription.vehicle_packages')
@endsection