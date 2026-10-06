@extends('layouts.app')
@section('page-title','İşletme giriş geçmişi')
@section('content')
<div class="card"><div class="card-header"><h5>İşletme giriş geçmişi</h5><p class="text-muted mb-0">Son 7 gündeki başarılı işletme sahibi girişleri. Daha eski kayıtlar otomatik silinir. Cihaz bilgileri tarayıcının bildirdiği bilgilerden alınır.</p></div>
<div class="card-body">
<form method="get" class="row g-3 mb-4"><div class="col-12 col-md-8"><label for="owner-filter" class="form-label">İşletme sahibi</label><select id="owner-filter" name="owner" class="form-select"><option value="">Tüm işletmeler</option>@foreach($owners as $owner)<option value="{{ $owner->id }}" @selected(($filters['owner'] ?? '') == $owner->id)>{{ $owner->name }} — {{ $owner->email }}</option>@endforeach</select></div><div class="col-12 col-md-4 align-self-end"><button class="btn btn-secondary">Filtrele</button> <a href="{{ route('owner-logins.index') }}" class="btn btn-light">Temizle</a></div></form>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>İşletme sahibi</th><th>Tarih / saat</th><th>IP adresi</th><th>Cihaz</th><th>İşletim sistemi / tarayıcı</th></tr></thead><tbody>
@forelse($histories as $history)
@php($details=json_decode($history->details ?? '{}',true) ?: [])
<tr><td>{{ $history->user?->name ?? 'Silinmiş hesap' }}<small class="d-block text-muted">{{ $history->user?->email }}</small></td><td>{{ \Carbon\Carbon::parse($history->date)->timezone('Europe/Istanbul')->translatedFormat('d M Y H:i') }}</td><td>{{ $history->ip }}</td><td>{{ ['desktop'=>'Bilgisayar','mobile'=>'Telefon','tablet'=>'Tablet'][$details['device'] ?? ''] ?? ($details['device'] ?? 'Bilinmiyor') }}</td><td>{{ $details['os'] ?? 'Bilinmiyor' }} / {{ $details['browser'] ?? 'Bilinmiyor' }}</td></tr>
@empty<tr><td colspan="5" class="text-center text-muted py-4">Son 7 gün için giriş kaydı bulunamadı.</td></tr>@endforelse
</tbody></table></div>{{ $histories->links() }}</div></div>
@endsection
