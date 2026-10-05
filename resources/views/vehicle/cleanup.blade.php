@extends('layouts.app')
@section('page-title','Araç temizleme')
@section('breadcrumb')
<li class="breadcrumb-item"><a href="{{ route('vehicle.index') }}">Araçlar</a></li><li class="breadcrumb-item">Temizleme işlemi</li>
@endsection
@section('content')
@php($trash=($filters['view'] ?? '')==='trash')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="card">
    <div class="card-header"><div class="d-flex flex-wrap justify-content-between gap-3"><div><h5>Araç temizleme</h5><p class="text-muted mb-0">Uzun süredir işlem yapılmayan araçları inceleyip seçerek silebilirsiniz.</p></div><a href="{{ route('vehicle.index') }}" class="btn btn-light">Araç listesine dön</a></div></div>
    <div class="card-body">
        <div class="alert alert-light">Son işlem tarihi; araç kaydı/düzenlemesi, servis, fatura, ödeme ve teklif hareketlerinden en güncel olanıdır. <strong>Açık servisi bulunan araçlar silinemez.</strong> Araç silinince müşterinin başka aktif aracı yoksa müşteri de silinenlere taşınır. Faturalar, servis geçmişi, ödemeler ve stok hareketleri korunur.</div>
        <div class="d-flex gap-2 mb-3 flex-wrap"><a class="btn {{ !$trash ? 'btn-secondary' : 'btn-outline-secondary' }}" href="{{ route('vehicle-cleanup.index') }}">Temizlenecek araçlar</a><a class="btn {{ $trash ? 'btn-secondary' : 'btn-outline-secondary' }}" href="{{ route('vehicle-cleanup.index',['view'=>'trash','months'=>'all']) }}">Silinen kayıtlar / geri getir</a></div>
        <form method="get" action="{{ route('vehicle-cleanup.index') }}" class="row g-3 align-items-end mb-4">
            <input type="hidden" name="view" value="{{ $trash ? 'trash' : 'active' }}">
            <div class="col-12 col-md-3"><label class="form-label" for="cleanup-months">İşlem yapılmayan süre</label><select class="form-select" id="cleanup-months" name="months">@foreach(['3'=>'En az 3 ay','6'=>'En az 6 ay','12'=>'En az 1 yıl','all'=>'Tüm araçlar'] as $value=>$label)<option value="{{ $value }}" @selected((string)$filters['months'] === (string)$value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-12 col-md-4"><label class="form-label" for="cleanup-search">Plaka veya müşteri adı</label><input type="search" class="form-control" id="cleanup-search" name="search" maxlength="150" value="{{ $filters['search'] ?? '' }}"></div>
            <div class="col-12 col-md-3"><label><input type="checkbox" name="never_serviced" value="1" class="form-check-input me-2" @checked(!empty($filters['never_serviced']))> Servis kaydı olmayanlar</label></div>
            <div class="col-12 col-md-2"><button type="submit" class="btn btn-secondary">Listele</button></div>
        </form>
        <p class="text-muted">{{ $vehicles->total() }} araç bulundu. Toplu seçim bu sayfadaki en fazla 50 aracı kapsar.</p>
        <form method="post" id="vehicle-cleanup-form" action="{{ route($trash ? 'vehicle-cleanup.restore' : 'vehicle-cleanup.destroy') }}">
            @csrf @if(!$trash) @method('DELETE') @endif
            <input type="hidden" name="confirm" value="1">
            @foreach($filters as $name=>$value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3"><label><input type="checkbox" class="form-check-input me-2" id="cleanup-select-all"> Bu sayfadaki seçilebilir araçları seç</label><span id="cleanup-selected-count" aria-live="polite">0 araç seçildi</span></div>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Seç</th><th>Araç / plaka</th><th>Müşteri</th><th>Kayıt tarihi</th><th>Son işlem</th><th>Son servis tarihi</th><th>Toplam servis</th><th>Fatura</th><th>Durum</th></tr></thead><tbody>
            @forelse($vehicles as $vehicle)
                <tr><td><input type="checkbox" class="form-check-input cleanup-choice" name="ids[]" value="{{ $vehicle->id }}" aria-label="{{ $vehicle->license_plate ?: 'Plakasız araç' }} aracını seç" @disabled(!$trash && $vehicle->open_service_count > 0)></td>
                    <td><strong>{{ $vehicle->license_plate ?: 'Plaka yok' }}</strong><br><small class="text-muted">{{ $vehicle->brand_name }} {{ $vehicle->model }}</small></td>
                    <td>{{ $vehicle->clients?->name ?? 'Müşteri bulunamadı' }}</td>
                    <td>{{ $vehicle->created_at ? dateFormat($vehicle->created_at) : 'Tarih yok' }}</td>
                    <td>{{ $vehicle->last_activity_at ? dateFormat($vehicle->last_activity_at) : 'Tarih yok' }}</td>
                    <td>{{ $vehicle->services_max_service_date ? dateFormat($vehicle->services_max_service_date) : 'Tarih girilmemiş' }}</td>
                    <td>{{ $vehicle->services_count }}</td><td>{{ $vehicle->invoice_count }}</td>
                    <td>@if($trash)<span class="badge bg-light-secondary">Silindi</span><br><small>{{ dateFormat($vehicle->deleted_at) }}</small>@elseif($vehicle->open_service_count > 0)<span class="badge bg-light-warning">{{ $vehicle->open_service_count }} açık servis</span>@else<span class="badge bg-light-success">Seçilebilir</span>@endif</td>
                </tr>
            @empty<tr><td colspan="9" class="text-center py-4">Bu filtrelere uygun araç bulunamadı.</td></tr>@endforelse
            </tbody></table></div>
            @if(!$trash || (Gate::check('create vehicle') && Gate::check('create client')))
            <button class="btn {{ $trash ? 'btn-secondary' : 'btn-danger' }}" type="submit" id="cleanup-submit" disabled>{{ $trash ? 'Seçilenleri geri getir' : 'Seçilen araç ve müşteri kayıtlarını sil' }}</button>
            @endif
            <p class="text-muted small mt-2">{{ $trash ? 'Geri getirme mevcut araç kapasitenize göre yapılır. Müşteri kaydı da geri açılır.' : 'Silme işlemi geri alınabilir. Araçların QR/müşteri bağlantıları kapanır; QR kodları başka araçlara verilmez. Stok ve kâr/zarar tutarları değişmez.' }}</p>
        </form>
        {{ $vehicles->links() }}
    </div>
</div>
<script>
(() => {
    const form=document.getElementById('vehicle-cleanup-form'), all=document.getElementById('cleanup-select-all');
    const choices=[...form.querySelectorAll('.cleanup-choice:not(:disabled)')], button=document.getElementById('cleanup-submit');
    const update=()=>{const count=choices.filter(input=>input.checked).length; document.getElementById('cleanup-selected-count').textContent=count+' araç seçildi'; if(button) button.disabled=count===0; all.checked=choices.length>0 && count===choices.length; all.indeterminate=count>0 && count<choices.length;};
    all.addEventListener('change',()=>{choices.forEach(input=>input.checked=all.checked); update();});
    choices.forEach(input=>input.addEventListener('change',update));
    form.addEventListener('submit',event=>{const count=choices.filter(input=>input.checked).length; const message=@json($trash ? ' seçilen araç ve ilgili müşteri kayıtları geri getirilsin mi?' : ' seçilen araç silinsin mi? Başka aktif aracı olmayan müşteriler de silinenlere taşınacak. Faturalar ve ödemeler korunacak.'); if(!count || !confirm(count+message)) event.preventDefault();});
    update();
})();
</script>
@endsection
