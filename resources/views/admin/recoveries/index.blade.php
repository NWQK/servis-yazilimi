@extends('layouts.app')
@section('page-title','İşletme veri kurtarma')
@section('content')
<div class="card"><div class="card-body">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3"><div><h4>İşletme veri kurtarma</h4><p class="text-muted mb-0">Diğer işletmelere dokunmadan, seçilen işletmenin ticari kayıtlarını kurtarın.</p></div><a class="btn btn-primary" href="{{ route('recoveries.create') }}">Yeni kurtarma hazırlığı</a></div>
<div class="alert alert-light border">Önce yedek incelenir ve değişiklikler ön izlenir. Uygulama için işletme adını ve yönetici şifrenizi girmeniz gerekir. İşlem öncesi şifreli kopya <strong>7 gün</strong> korunur. Hazırlık ve uygulama mevcut sunucu zamanlayıcısıyla çalışır.</div>
<div class="table-responsive"><table class="table"><thead><tr><th>İşletme</th><th>Tür</th><th>Kaynak</th><th>Durum</th><th>Tarih</th><th></th></tr></thead><tbody>
@forelse($jobs as $job)
<tr><td>{{ $job->owner->name ?? 'İşletme bulunamadı' }}</td><td>{{ ['missing'=>'Silinen kayıtları kurtar','replace'=>'Yedek tarihine döndür','rollback'=>'İşlem öncesine dön'][$job->mode] ?? $job->mode }}</td><td>{{ $job->backup?->backup_date?->format('d.m.Y') ?? 'İşlem öncesi kopya' }}</td><td>@include('admin.recoveries.status',['status'=>$job->status])</td><td>{{ $job->created_at->format('d.m.Y H:i') }}</td><td><a class="btn btn-sm btn-outline-secondary" href="{{ route('recoveries.show',$job) }}">İncele</a></td></tr>
@empty<tr><td colspan="6">Henüz işletme kurtarma işlemi yok.</td></tr>@endforelse
</tbody></table></div>{{ $jobs->links() }}</div></div>
@endsection
