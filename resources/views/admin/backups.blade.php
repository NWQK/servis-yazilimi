@extends('layouts.app')
@section('page-title','Yedekler')
@section('content')
<div class="card"><div class="card-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <div><h5 class="mb-1">Platform yedekleri</h5><p class="text-muted mb-0">Tüm işletmelerin veritabanı, logoları ve yüklenen dosyaları.</p></div>
        <span class="badge {{ $settings->enabled ? 'bg-success' : 'bg-secondary' }}">{{ $settings->enabled ? 'Etkin' : 'Kapalı' }}</span>
    </div>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    @if($settings->last_error)<div class="alert alert-danger">{{ $settings->last_error }}</div>@endif
    @if($problems)<div class="alert alert-warning"><strong>Kurulum tamamlanmalı</strong><ul class="mb-0">@foreach($problems as $problem)<li>{{ $problem }}</li>@endforeach</ul></div>@endif
    <div class="alert alert-light border">Her gece <strong>23.55 (Türkiye saati)</strong> yedek alınır. VDS ve Google Drive’da <strong>son 3 günlük + ayın 1’indeki 1 aylık</strong> yedek korunur. Örneğin ayın 31’inde gece yedeği alınmadan önce: 1, 28, 29 ve 30. Yeni kopya doğrulanmadan ilgili hedefteki eski yedek silinmez.</div>
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted">Son çalışma başlangıcı</small><div>{{ $settings->last_started_at?->format('d.m.Y H:i:s') ?? 'Henüz çalışmadı' }}</div></div></div>
        <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted">Son çalışma bitişi</small><div>{{ $settings->last_finished_at?->format('d.m.Y H:i:s') ?? '—' }}</div></div></div>
        <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted">Son doğrulanmış Drive aktarımı</small><div>{{ $copies->max('drive_verified_at')?->format('d.m.Y H:i:s') ?? 'Henüz aktarılmadı' }}</div></div></div>
    </div>
    @if($settings->requested_at)<div class="alert alert-info">Talep bekliyor: {{ $settings->requested_at->format('d.m.Y H:i') }}. Uzun süre beklerse sunucunun Laravel zamanlayıcısını kontrol edin.</div>@endif
    @if($monthlyMissing)<div class="alert alert-warning">Bu ayın 1’ine ait yedek bulunmuyor. Sistem geçmiş tarihli bir yedek üretmez; varsa önceki aylık kopyayı korur. Yeni aylık yedek sonraki ayın 1’inde alınır.</div>@endif
    <div class="d-flex flex-wrap align-items-center gap-3">
        <form method="post" action="{{ route('backups.save') }}" class="d-flex flex-wrap align-items-center gap-3">@csrf
            <input type="hidden" name="enabled" value="0">
            <label class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="enabled" value="1" @checked($settings->enabled)><span class="form-check-label">Otomatik yedekleme</span></label>
            <button class="btn btn-secondary">Kaydet</button>
        </form>
        <form method="post" action="{{ route('backups.request') }}">@csrf<button class="btn btn-outline-secondary" @disabled(!$settings->enabled || count($problems))><i class="ti ti-cloud-upload me-1"></i>Şimdi yedekle / aktarımı dene</button></form>
    </div>
    <p class="small text-muted mt-3 mb-0">Aynı gün tekrar çalıştırmak günlük kopyayı değiştirmez. Kapatmak mevcut yedekleri silmez. Google hesabının bağlantısı sunucuda rclone ile bir kez kurulur; gizli anahtarlar bu ekranda gösterilmez.</p>
</div></div>
<div class="card"><div class="card-body"><h5 class="mb-3">Saklanan kopyalar</h5>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Tür</th><th>Yedek tarihi</th><th>Boyut</th><th>VDS</th><th>Google Drive</th><th>Açıklama</th></tr></thead><tbody>
@forelse($copies as $backup)<tr>
    <td><span class="badge {{ $backup->kind === 'monthly' ? 'bg-dark' : 'bg-secondary' }}">{{ $backup->kind === 'monthly' ? 'Aylık' : 'Günlük' }}</span></td>
    <td class="text-nowrap">{{ $backup->backup_date->format('d.m.Y') }}</td><td class="text-nowrap">{{ number_format($backup->bytes / 1048576, 2, ',', '.') }} MB</td>
    <td>{{ $backup->local_available ? 'Doğrulandı' : 'Saklama süresi doldu' }}</td>
    <td>{{ $backup->drive_verified_at ? 'Doğrulandı · '.$backup->drive_verified_at->format('d.m H:i') : ($backup->drive_error ? 'Aktarım başarısız' : 'Bekliyor') }}</td>
    <td class="small">{{ $backup->drive_error ?? 'AES-256 şifreli ZIP' }}</td>
</tr>@empty<tr><td colspan="6" class="text-muted">Henüz yedek yok. Sunucu kurulumunu tamamlayıp yedeklemeyi etkinleştirin.</td></tr>@endforelse
</tbody></table></div></div></div>
@endsection
