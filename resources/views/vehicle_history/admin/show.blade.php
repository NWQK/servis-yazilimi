@extends('layouts.app')
@section('page-title','Araç sorgu görünürlüğü')
@section('content')
<div class="card"><div class="card-header"><h4>{{ $entry->license_plate?:'Arşiv araç' }} · {{ $entry->owner_name?:'Arşiv işletme' }}</h4><p class="mb-0">{{ $entry->chassis_no?:'Kaynak şasi numarası bulunmuyor' }}</p></div><div class="card-body">
<p>Görünürlük: <strong>{{ $entry->hidden?'Yönetici tarafından gizlendi':'Otomatik açık' }}</strong></p>
@if($entry->reason)<p>Son gerekçe: {{ $entry->reason }}</p>@endif
<form method="post" action="{{ route('vehicle-history-admin.update',$entry->vehicle_id) }}" class="border rounded p-3 mb-4">@csrf
<input type="hidden" name="hidden" value="{{ $entry->hidden?0:1 }}"><input type="hidden" name="expected_revision" value="{{ $entry->revision }}">
<label for="visibility-reason" class="form-label">{{ $entry->hidden?'Yeniden açma':'Gizleme' }} gerekçesi</label><textarea id="visibility-reason" name="reason" class="form-control mb-3" required minlength="5" maxlength="500">{{ old('reason') }}</textarea>@error('reason')<p class="text-danger">{{ $message }}</p>@enderror
<p class="text-muted small">Bu karar aracın saklanan bütün servis özetlerine uygulanır. İşletmenin kayıt silmesi, şasi değiştirmesi veya işletme yedeği yüklemesi yönetici kararını kaldırmaz. İşlem yönetici geçmişine kaydedilir.</p>
<button class="btn {{ $entry->hidden?'btn-success':'btn-danger' }}">{{ $entry->hidden?'Sorguda yeniden göster':'Sorguda gizle' }}</button></form>
<h5>Saklanan servis özetleri</h5><div class="table-responsive"><table class="table"><thead><tr><th>İşletme</th><th>Araç / maskeli plaka</th><th>Tarih</th><th>İşlemler</th><th>Kilometre</th></tr></thead><tbody>
@forelse($records as $record)<tr><td>{{ $record->business_name }}</td><td>{{ trim(($record->vehicle_brand??'').' '.($record->vehicle_model??''))?:'Marka/model belirtilmedi' }}<br>••• {{ $record->plate_suffix?:'—' }}</td><td>{{ $record->service_date?:'Belirtilmedi' }}</td><td>@foreach(json_decode($record->operations,true)?:[] as $operation)<div>{{ $operation }}</div>@endforeach</td><td>{{ $record->mileage_at_service??'Belirtilmedi' }}</td></tr>@empty<tr><td colspan="5">Geçerli şasiye bağlı tamamlanmış servis özeti yok.</td></tr>@endforelse
</tbody></table></div>{{ $records->links() }}<a href="{{ route('vehicle-history-admin.index') }}">Listeye dön</a>
</div></div>
@endsection
