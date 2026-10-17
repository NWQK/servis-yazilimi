@extends('layouts.app')
@section('page-title','Sistem sağlığı')
@section('content')
<div class="card"><div class="card-body"><h5>Sistem sağlığı</h5><p class="text-muted">Son bilinen gönderim sonuçları ve çalışma zamanları. E-posta/SMS kabulü, alıcıya teslim edildiği anlamına gelmez.</p>
<div class="row g-3">@foreach(['smtp'=>'E-posta gönderimi','sms'=>'SMS gönderimi','scheduler'=>'Zamanlayıcı'] as $component=>$label)
@php($state=$health[$component]??null)
@php($stale=$component==='scheduler' && (!$state || \Carbon\Carbon::parse($state->checked_at)->lt(now()->subMinutes(5))))
<div class="col-md-4"><div class="border rounded p-3 h-100"><h6>{{ $label }}</h6><strong class="{{ !$enabled[$component] ? 'text-muted' : ($stale || $state?->status==='error' ? 'text-danger' : 'text-success') }}">{{ !$enabled[$component] ? 'Kapalı' : ($stale ? 'Gecikmiş / henüz çalışmadı' : ($state ? ($state->status==='ok' ? 'Son işlem başarılı' : 'Son işlem başarısız / belirsiz') : 'Henüz sonuç yok')) }}</strong><p class="small mt-2 mb-0">Son kontrol: {{ $state?->checked_at ?? '—' }}<br>Son başarılı: {{ $state?->last_success_at ?? '—' }}<br>Ardışık hata: {{ $state?->consecutive_failures ?? 0 }}</p></div></div>@endforeach</div>
<div class="border rounded p-3 mt-3"><h6>Yedekler</h6><p class="mb-1">{{ $backup->enabled ? 'Etkin' : 'Kapalı' }} · Son VDS kopyası: {{ $lastBackup ?? 'Yok' }} · Son Drive doğrulaması: {{ $lastDrive ?? 'Yok' }}</p>
@if($backup->enabled && (!$driveContentAt || \Carbon\Carbon::parse($driveContentAt)->lt(now()->subHours(26))))<p class="text-danger mb-1">Google Drive yedeği gecikmiş veya henüz alınmamış.</p>@endif
@if($backup->enabled && (!$lastBackup || \Carbon\Carbon::parse($lastBackup)->lt(now()->subHours(26))))<p class="text-danger mb-1">VDS yedeği gecikmiş veya henüz alınmamış.</p>@endif
@if($backup->last_error)<p class="text-danger">{{ $backup->last_error }}</p>@endif<a href="{{ route('backups.index') }}">Yedekleri görüntüle</a></div>
<p class="mt-3 mb-0"><a href="{{ route('integrity.index') }}">E-posta / SMS kuyruğunu ve stok–finans kontrollerini görüntüle</a></p>
<p class="mt-2 mb-0">Diğer başarısız kuyruk işleri: <strong>{{ $failedJobs }}</strong> · Laravel kuyruk sürücüsü: {{ config('queue.default') }}. Merkezi mesajlar ayrı kalıcı gönderim kuyruğunda işlenir.</p>
</div></div>
<div class="card"><div class="card-body"><h5>Son 30 günün hata özetleri</h5><p class="text-muted">Aynı hata günlük sayaçla birleştirilir. Mesaj içeriği, alıcı adresi ve gizli bağlantı bilgileri kaydedilmez.</p><div class="table-responsive"><table class="table"><thead><tr><th>Bileşen</th><th>Hata kodu</th><th>Gün</th><th>Tekrar</th><th>Son görülme</th></tr></thead><tbody>@forelse($errors as $error)<tr><td>{{ ['smtp'=>'E-posta','sms'=>'SMS','scheduler'=>'Zamanlayıcı'][$error->component] ?? $error->component }}</td><td>{{ \App\Services\SystemHealth::explanation($error->code) }}<small class="d-block text-muted">{{ $error->code }}</small></td><td>{{ $error->day }}</td><td>{{ $error->count }}</td><td>{{ $error->last_seen_at }}</td></tr>@empty<tr><td colspan="5">Kayıtlı hata yok.</td></tr>@endforelse</tbody></table></div>{{ $errors->links() }}</div></div>
@endsection
