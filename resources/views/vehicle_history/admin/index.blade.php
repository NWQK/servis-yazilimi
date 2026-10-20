@extends('layouts.app')
@section('page-title','Şasi sorgu yönetimi')
@section('content')
<div class="card"><div class="card-header"><h4>Şasi sorgu yönetimi</h4><p class="text-muted mb-0">Tamamlanmış servis özetleri otomatik görünür. İşletmeler kapatamaz; yalnız süper admin gerekçeli olarak gizleyebilir veya yeniden açabilir.</p></div>
<div class="card-body"><form class="row g-3 mb-4" method="get">
<div class="col-md-7"><label class="form-label" for="history-filter">Şasi, plaka veya işletme sahibi</label><input id="history-filter" name="q" maxlength="150" value="{{ $filters['q']??'' }}" class="form-control"></div>
<div class="col-md-3"><label class="form-label" for="history-status">Görünürlük</label><select id="history-status" name="status" class="form-select"><option value="">Tümü</option><option value="public" @selected(($filters['status']??'')==='public')>Açık</option><option value="hidden" @selected(($filters['status']??'')==='hidden')>Yönetici gizledi</option></select></div>
<div class="col-md-2 align-self-end"><button class="btn btn-secondary">Filtrele</button></div></form>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Araç / şasi</th><th>İşletme sahibi</th><th>Özet sayısı</th><th>Görünürlük</th><th>İşlem</th></tr></thead><tbody>
@forelse($vehicles as $entry)<tr><td>{{ $entry->license_plate?:'Kaynak araç silinmiş' }}<br><small>{{ $entry->chassis_no?:'Şasi kaynak kayıtta bulunmuyor' }}</small></td><td>{{ $entry->owner_name?:'İşletme kaydı silinmiş' }}</td><td>{{ $entry->record_count }}</td><td><span class="badge {{ $entry->hidden?'bg-light-danger':'bg-light-success' }}">{{ $entry->hidden?'Yönetici gizledi':'Otomatik açık' }}</span>@if(!$entry->record_count)<br><small>Henüz yayımlanabilir özet yok</small>@endif</td><td><a class="btn btn-sm btn-outline-secondary" href="{{ route('vehicle-history-admin.show',$entry->vehicle_id) }}">İncele / yönet</a></td></tr>
@empty<tr><td colspan="5">Kayıt bulunamadı.</td></tr>@endforelse
</tbody></table></div>{{ $vehicles->links() }}</div></div>
@endsection
