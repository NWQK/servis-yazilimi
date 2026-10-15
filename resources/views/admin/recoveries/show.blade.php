@extends('layouts.app')
@section('page-title','İşletme kurtarma ön izlemesi')
@section('content')
<div class="card"><div class="card-body">
<div class="d-flex flex-wrap justify-content-between gap-3"><div><h4>{{ $recovery->owner->name ?? 'İşletme bulunamadı' }}</h4><p class="text-muted">{{ ['missing'=>'Silinen kayıtları kurtar','replace'=>'İşletmeyi yedek tarihine döndür','rollback'=>'Kurtarma işleminden hemen önceki duruma dön'][$recovery->mode] }}</p></div><div>@include('admin.recoveries.status',['status'=>$recovery->status])</div></div>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if($recovery->error)<div class="alert alert-danger">{{ $recovery->error }}</div>@endif
<p>Kaynak: <strong>{{ $recovery->backup?->created_at?->format('d.m.Y H:i') ?? 'İşlem öncesi şifreli kurtarma kopyası' }}</strong></p>
@if(in_array($recovery->status,['queued_prepare','preparing','queued_apply','applying']))<div class="alert alert-info">Sunucudaki zamanlayıcı bu işlemi çalıştırır. Bu sayfa beklerken 20 saniyede bir yenilenir. Uygulama sırasında yalnızca hedef işletme geçici olarak durdurulur.</div>@endif
@if($recovery->summary)
<div class="row g-3 my-2">@foreach(['insert'=>'Eklenecek','update'=>'Güncellenecek','delete'=>'Silinecek','revive'=>'Silinenlerden dönecek','files'=>'Dosya işlemi'] as $key=>$label)<div class="col-6 col-lg"><div class="border rounded p-3"><span class="text-muted small">{{ $label }}</span><div class="fs-3 fw-bold">{{ number_format($recovery->summary[$key]??0,0,',','.') }}</div></div></div>@endforeach</div>
@if(!empty($recovery->summary['conflicts']))<div class="alert alert-danger"><strong>Bu ön izleme uygulanamaz</strong><ul class="mb-0">@foreach($recovery->summary['conflicts'] as $conflict)<li>{{ $conflict }}</li>@endforeach</ul><p class="mt-2 mb-0">Kayıtlar değiştirilmedi. Çakışmaları inceleyin veya uygun başka bir yedekten yeni hazırlık oluşturun.</p></div>@endif
<details class="my-3"><summary>Tablo bazında işlem özeti</summary><div class="table-responsive"><table class="table"><thead><tr><th>Tablo</th><th>Yedek</th><th>Güncel</th><th>Ekle</th><th>Güncelle</th><th>Sil</th><th>Geri getir</th></tr></thead><tbody>@foreach($recovery->summary['tables']??[] as $table=>$counts)<tr><td>{{ $table }}</td>@foreach(['backup','current','insert','update','delete','revive'] as $key)<td>{{ $counts[$key] }}</td>@endforeach</tr>@endforeach</tbody></table></div></details>
@endif
@if($recovery->status==='preview')
<div class="alert {{ $recovery->mode==='missing'?'alert-light border':'alert-danger' }}"><strong>{{ $recovery->mode==='missing'?'Mevcut kayıtlar ezilmez.':'Bu işlem yedekten sonraki ticari değişiklikleri kaldırabilir.' }}</strong> Ön izleme sonrasında işletme verileri değişirse işlem otomatik durur ve yeniden hazırlık gerekir. İşlem başlamadan şifreli kurtarma kopyası alınır.</div>
<form method="post" action="{{ route('recoveries.apply',$recovery) }}">@csrf
<div class="row g-3"><div class="col-12 col-md-6"><label for="confirm-owner" class="form-label">Onay için hesap adını yazın: <strong>{{ $recovery->owner->name }}</strong></label><input class="form-control" name="owner_name" id="confirm-owner" required autocomplete="off"></div><div class="col-12 col-md-6"><label for="confirm-password" class="form-label">Süper admin şifreniz</label><input class="form-control" type="password" name="current_password" id="confirm-password" autocomplete="current-password" required></div></div>
<label class="form-check my-3"><input class="form-check-input" type="checkbox" name="confirm" value="1" required><span class="form-check-label">Bu işletme ve işlem özetini kontrol ettim; belirtilen değişiklikleri onaylıyorum.</span></label><button class="btn btn-danger">Onayla ve kurtarmayı sıraya al</button>
</form>
@endif
@if($recovery->checkpoint_hash&&in_array($recovery->status,['completed','failed','needs_attention'])&&($recovery->status==='needs_attention'||$recovery->checkpoint_expires_at?->isFuture()))
<div class="border rounded p-3 mt-4"><h5>İşlem öncesi kurtarma kopyası</h5><p class="text-muted">Geçerlilik: {{ $recovery->checkpoint_expires_at?->format('d.m.Y H:i') }}. Bu düğme önce ayrı ön izleme hazırlar. İşlem sonrasında eklenen ticari kayıtlar da etkilenebilir.</p><form method="post" action="{{ route('recoveries.undo',$recovery) }}">@csrf<button class="btn btn-outline-danger">İşlem öncesine dönüşü ön izle</button></form></div>
@endif
<div class="d-flex flex-wrap gap-2 mt-4"><a class="btn btn-outline-secondary" href="{{ route('recoveries.show',$recovery) }}">Durumu yenile</a><a class="btn btn-link" href="{{ route('recoveries.index') }}">Kurtarma işlemleri</a>@if(in_array($recovery->status,['queued_prepare','preview','blocked','queued_apply']))<form method="post" action="{{ route('recoveries.cancel',$recovery) }}">@csrf<button class="btn btn-outline-secondary">Bekleyen işlemi iptal et</button></form>@endif</div>
</div></div>
@endsection
@if(in_array($recovery->status,['queued_prepare','preparing','queued_apply','applying']))@push('script-page')<script>setTimeout(function(){window.location.reload()},20000)</script>@endpush @endif
