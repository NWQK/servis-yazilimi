@extends('layouts.app')
@section('page-title','Duyuru detayı')
@section('content')
<div class="card"><div class="card-body"><h4>{{ $announcement->title }}</h4><p class="text-muted">{{ dateFormat($announcement->created_at) }} · {{ timeFormat($announcement->created_at) }}</p><div style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $announcement->body }}</div><a class="btn btn-outline-secondary mt-3" href="{{ route('announcements.index') }}">Duyurulara dön</a>
@if($isAdmin && $announcement->published)<form class="mt-3" method="post" action="{{ route('announcements.withdraw',$announcement->id) }}" onsubmit="return confirm('Duyuru işletmelerin ekranından kaldırılacak. Devam edilsin mi?')">@csrf<button class="btn btn-outline-danger">Yayından kaldır</button></form>@endif
</div></div>
@if($isAdmin)<div class="card"><div class="card-body"><h5>Alıcılar ve okunma durumu</h5><div class="table-responsive"><table class="table"><thead><tr><th>İşletme</th><th>Okunma</th></tr></thead><tbody>@foreach($recipients as $r)<tr><td>{{ $r->owner?->name ?? 'Silinmiş işletme' }}</td><td>{{ $r->read_at ? dateFormat($r->read_at).' '.timeFormat($r->read_at) : 'Henüz okunmadı' }}</td></tr>@endforeach</tbody></table></div>{{ $recipients->links() }}</div></div>@endif
@endsection
