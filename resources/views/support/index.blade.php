@extends('layouts.app')
@section('page-title','Destek biletleri')
@section('content')
@include('support.style')
<div class="card"><div class="card-header d-flex flex-wrap gap-3 justify-content-between align-items-center"><div><h5>Destek biletleri</h5><p class="text-muted mb-0">{{ $isAdmin ? 'İşletmelerin destek taleplerini yanıtlayın ve takip edin.' : 'Destek talebi oluşturun, yanıtları ve mesaj geçmişini buradan takip edin.' }}</p></div>@unless($isAdmin)<a class="btn btn-primary" href="{{ route('support.create') }}">Yeni destek bileti</a>@endunless</div><div class="card-body">
<form method="GET" class="row g-3 mb-4">
<div class="col-12 col-md-4"><label class="form-label" for="support-search">{{ $isAdmin ? 'Konu, bilet numarası veya işletme' : 'Konu veya bilet numarası' }}</label><input class="form-control" id="support-search" type="search" name="search" maxlength="150" value="{{ $filters['search'] ?? '' }}"></div>
<div class="col-6 col-md-3"><label class="form-label" for="support-status">Durum</label><select class="form-select" id="support-status" name="status"><option value="">Tüm durumlar</option>@foreach(\App\Models\SupportTicket::STATUSES as $value=>$label)<option value="{{ $value }}" @selected(($filters['status'] ?? '')===$value)>{{ $label }}</option>@endforeach</select></div>
<div class="col-6 col-md-2"><label class="form-label" for="support-priority">Öncelik</label><select class="form-select" id="support-priority" name="priority"><option value="">Tüm öncelikler</option>@foreach(\App\Models\SupportTicket::PRIORITIES as $value=>$label)<option value="{{ $value }}" @selected(($filters['priority'] ?? '')===$value)>{{ $label }}</option>@endforeach</select></div>
<div class="col-12 col-md-3 align-self-end"><label class="d-block mb-2"><input type="checkbox" name="unread" value="1" @checked(!empty($filters['unread']))> Yalnızca okunmamış</label><button class="btn btn-primary">Filtrele</button> <a href="{{ route('support.index') }}" class="btn btn-light">Temizle</a></div>
</form>
<p class="text-muted">{{ $tickets->total() }} destek bileti</p>
@forelse($tickets as $ticket)
@php($unread=$isAdmin ? $ticket->admin_unread : $ticket->owner_unread)
<a class="support-ticket-card {{ $unread ? 'support-unread' : '' }}" href="{{ route('support.show',$ticket->id) }}"><div class="support-meta justify-content-between"><h6 class="support-ticket-title mb-0">#{{ $ticket->id }} · {{ $ticket->subject }}</h6>@if($unread)<span class="badge bg-danger">Yeni mesaj / güncelleme</span>@endif</div>
@if($isAdmin)<p class="text-muted mt-2 mb-2">{{ $ticket->owner?->name ?? 'Silinmiş işletme' }} · {{ $ticket->owner?->email }}</p>@endif
<div class="support-meta mt-3"><span class="badge {{ $ticket->status==='closed' ? 'bg-secondary' : 'bg-primary' }}">{{ \App\Models\SupportTicket::STATUSES[$ticket->status] }}</span><span class="badge {{ $ticket->priority==='high' ? 'bg-danger' : 'bg-light text-dark' }}">{{ \App\Models\SupportTicket::PRIORITIES[$ticket->priority] }} öncelik</span><span class="small text-muted">Son işlem: {{ $ticket->last_message_at->timezone('Europe/Istanbul')->translatedFormat('d M Y H:i') }}</span></div></a>
@empty<div class="text-center py-5"><h5>Henüz destek bileti yok</h5><p class="text-muted">{{ $isAdmin ? 'İşletmelerin açtığı biletler burada görünecek.' : 'Yardıma ihtiyacınız olduğunda yeni destek bileti oluşturabilirsiniz.' }}</p></div>@endforelse
{{ $tickets->links() }}
</div></div>
@endsection