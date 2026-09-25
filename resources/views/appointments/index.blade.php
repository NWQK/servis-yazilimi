@extends('layouts.app')
@section('page-title', 'Randevular')
@section('breadcrumb')<li class="breadcrumb-item">Randevular</li>@endsection
@section('content')
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between flex-wrap gap-2"><div><h5>Randevu talepleri</h5><p>{{ $pendingCount }} talep onay bekliyor.</p></div><a class="btn btn-outline-secondary align-self-start" href="{{ route('appointments.settings') }}">Çalışma günleri ve saatleri</a></div>
    <p class="text-muted">Bekleyen ve onaylanan talepler ilgili saati ayırır. SMS gönderilmez; müşteri kişisel takip bağlantısından durumunu görebilir.</p>
    @if (!$profile->is_active)<div class="alert alert-warning">Yeni randevu alımı kapalı. Randevu ayarlarından çalışma saatlerini seçip talep alımını açabilirsiniz.</div>@endif
    @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <form method="get" class="row align-items-end mb-3">
        <div class="col-md-4"><label for="status" class="form-label">Durum</label><select id="status" name="status" class="form-control"><option value="">Tüm durumlar</option>@foreach (\App\Models\Appointment::statuses() as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-4"><label for="date" class="form-label">Randevu tarihi</label><input id="date" class="form-control" type="date" name="date" value="{{ request('date') }}"></div>
        <div class="col-md-4 mt-2"><button class="btn btn-secondary">Filtrele</button> <a href="{{ route('appointments.index') }}" class="btn btn-outline-secondary">Temizle</a></div>
    </form>
    <div class="table-responsive"><table class="table"><thead><tr><th>Tarih / saat</th><th>Müşteri</th><th>Talep</th><th>Durum</th><th>İşlemler</th></tr></thead><tbody>
    @forelse ($appointments as $appointment)
        <tr><td>{{ dateFormat($appointment->starts_at) }}<br>{{ timeFormat($appointment->starts_at) }}–{{ timeFormat($appointment->ends_at) }}</td>
            <td>{{ $appointment->customer_name }}<br>{{ $appointment->phone }}<br><small>{{ $appointment->license_plate }}</small></td>
            <td style="white-space:pre-wrap;max-width:320px;overflow-wrap:anywhere">{{ $appointment->notes ?: '—' }}</td>
            <td>{{ \App\Models\Appointment::statuses()[$appointment->status] }}</td>
            <td><div class="d-flex flex-wrap gap-1">
            @php($actions = $appointment->status === 'pending' ? ['approved' => 'Onayla', 'rejected' => 'Reddet'] : ($appointment->status === 'approved' ? ['completed' => 'Tamamlandı', 'no_show' => 'Gelmedi', 'cancelled' => 'İptal et'] : []))
            @foreach ($actions as $status => $label)
                @if (($status !== 'approved' || $appointment->starts_at->isFuture()) && (!in_array($status, ['completed', 'no_show']) || !$appointment->starts_at->isFuture()))
                <form method="post" action="{{ route('appointments.status', $appointment->id) }}">@csrf<input type="hidden" name="status" value="{{ $status }}"><button class="btn btn-sm {{ $status === 'approved' ? 'btn-success' : 'btn-outline-secondary' }}">{{ $label }}</button></form>
                @endif
            @endforeach
            </div></td>
        </tr>
    @empty<tr><td colspan="5">Bu filtrelere uygun randevu bulunmuyor.</td></tr>@endforelse
    </tbody></table></div>{{ $appointments->links() }}
</div></div>
@endsection
