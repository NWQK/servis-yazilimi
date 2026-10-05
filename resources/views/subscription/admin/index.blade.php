@extends('layouts.app')
@section('page-title','Abonelik yönetimi')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Ana sayfa</a></li>
    <li class="breadcrumb-item">Abonelik yönetimi</li>
@endsection
@section('content')
<div class="card">
    <div class="card-header"><h5>İşletme abonelikleri</h5><p class="text-muted mb-0">Paketleri, kullanım miktarını ve bitiş tarihlerini takip edin. Askıya alınan işletmenin ve personelinin panel erişimi ve yeni randevu alımı durdurulur; kayıtları korunur.</p></div>
    <div class="card-body">
        <form method="get" action="{{ route('subscription-admin.index') }}" class="row g-3 align-items-end mb-4">
            <div class="col-12 col-md-4"><label for="subscription-search" class="form-label">İşletme sahibi veya e-posta</label><input type="search" class="form-control" id="subscription-search" name="search" maxlength="150" value="{{ $filters['search'] ?? '' }}"></div>
            <div class="col-6 col-md-3"><label for="subscription-plan" class="form-label">Paket</label><select id="subscription-plan" name="plan" class="form-select"><option value="">Tüm paketler</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected(($filters['plan'] ?? '') == $plan->id)>{{ $plan->title }}</option>@endforeach</select></div>
            <div class="col-6 col-md-3"><label for="subscription-status" class="form-label">Abonelik durumu</label><select id="subscription-status" name="status" class="form-select"><option value="">Tüm durumlar</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option><option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>Askıda</option><option value="expired" @selected(($filters['status'] ?? '') === 'expired')>Süresi dolmuş</option></select></div>
            <div class="col-12 col-md-2"><button class="btn btn-secondary" type="submit">Filtrele</button> <a href="{{ route('subscription-admin.index') }}" class="btn btn-light" aria-label="Filtreleri temizle">Temizle</a></div>
        </form>
        <p class="text-muted">{{ $owners->total() }} işletme</p>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>İşletme sahibi</th><th>Paket / araç kapasitesi</th><th>Bitiş tarihi</th><th>Durum</th><th>İşlemler</th></tr></thead><tbody>
        @forelse($owners as $owner)
            @php
                $plan=$owner->subscriptions;
                $extra=$plan && (int)$plan->vehicle_limit === 3000 ? (int)$owner->extra_vehicles : 0;
                $limit=$plan && $plan->vehicle_limit !== null ? (int)$plan->vehicle_limit+$extra : null;
                $suspended=$owner->subscription_suspended_at !== null;
                $expired=$owner->subscription_expire_date && $owner->subscription_expire_date < $today;
            @endphp
            <tr>
                <td><strong>{{ $owner->name }}</strong><br><span class="text-muted small">{{ $owner->email }}</span></td>
                <td>{{ $plan?->title ?? 'Paket atanmamış' }}<br><small class="text-muted">{{ $owner->vehicle_count }} / {{ $limit ?? 'Sınırsız' }} araç @if($extra) · +{{ $extra }} ek kapasite @endif</small>@if($limit !== null && $owner->vehicle_count > $limit)<br><span class="text-danger small">Kapasite aşılmış; yeni araç eklenemez.</span>@endif</td>
                <td>{{ $owner->subscription_expire_date ? dateFormat($owner->subscription_expire_date) : 'Süresiz' }}</td>
                <td><span class="badge {{ $suspended ? 'bg-light-danger' : ($expired ? 'bg-light-warning' : 'bg-light-success') }}">{{ $suspended ? 'Askıda' : ($expired ? 'Süresi dolmuş' : 'Aktif') }}</span>@if((int)$owner->is_active === 0)<br><small class="text-muted">Kullanıcı hesabı pasif</small>@endif</td>
                <td><div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-sm btn-outline-secondary customModal" href="#" data-url="{{ route('subscription-admin.edit',$owner->id) }}" data-size="lg" data-title="Aboneliği düzenle">Düzenle</a>
                    <form method="post" action="{{ route('subscription-admin.status',$owner->id) }}" onsubmit="return confirm('{{ $suspended ? 'Abonelik yeniden etkinleştirilsin mi? Bitiş tarihi uzatılmayacak.' : 'Abonelik askıya alınsın mı? İşletme ve personelinin panel erişimi durdurulacak.' }}');">
                        @csrf <input type="hidden" name="confirm" value="1"><input type="hidden" name="action" value="{{ $suspended ? 'resume' : 'suspend' }}">
                        <button type="submit" class="btn btn-sm {{ $suspended ? 'btn-outline-success' : 'btn-outline-danger' }}">{{ $suspended ? 'Etkinleştir' : 'Askıya al' }}</button>
                    </form>
                </div></td>
            </tr>
        @empty<tr><td colspan="5" class="text-center text-muted py-4">Bu filtrelere uygun işletme bulunamadı.</td></tr>@endforelse
        </tbody></table></div>
        {{ $owners->links() }}
    </div>
</div>
@endsection
