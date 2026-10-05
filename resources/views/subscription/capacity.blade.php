@extends('layouts.app')
@section('page-title', 'Ek kapasite talepleri')
@section('breadcrumb')<li class="breadcrumb-item">Ek kapasite talepleri</li>@endsection
@section('content')
<div class="card"><div class="card-header"><h5>500 araçlık ek kapasite talepleri</h5><p class="mb-0">Kapasite yalnızca ödeme kontrolü ve onay tamamlandığında artar.</p></div><div class="card-body">
@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="table-responsive"><table class="table"><thead><tr><th>İşletme</th><th>Tarih</th><th>Ek kapasite</th><th>Tutar</th><th>Durum</th>@if($admin)<th>İşlem</th>@endif</tr></thead><tbody>
@forelse ($requests as $record)
<tr><td>{{ $record->owner->name ?? 'Silinmiş işletme' }}</td><td>{{ dateFormat($record->created_at) }}</td><td>{{ $record->vehicles }} araç</td><td>{{ priceFormat($record->amount) }}</td><td>{{ ['pending'=>'Onay bekliyor','approved'=>'Onaylandı','rejected'=>'Reddedildi'][$record->status] ?? $record->status }}</td>
@if($admin)<td>@if($record->status === 'pending')
<form method="post" action="{{ route('capacity.review',$record->id) }}">@csrf
<button class="btn btn-sm btn-success" name="decision" value="approved">Ödeme alındı, onayla</button>
<button class="btn btn-sm btn-outline-danger" name="decision" value="rejected">Reddet</button>
</form>@endif</td>@endif</tr>
@empty<tr><td colspan="6">Henüz ek kapasite talebi yok.</td></tr>@endforelse
</tbody></table></div>{{ $requests->links() }}
<a href="{{ route('subscriptions.index') }}" class="btn btn-outline-secondary">Paketlere dön</a>
</div></div>
@endsection
