@extends('layouts.app')
@section('page-title', 'Randevu SMS ayarları')
@section('breadcrumb')<li class="breadcrumb-item">Randevu SMS ayarları</li>@endsection
@section('content')
<div class="card"><div class="card-body">
    <h5>Merkezi SMS hesabı — İleti Merkezi</h5><p>Tüm işletmelerin randevu doğrulama ve onay mesajları bu hesaptan gönderilir.</p>
    @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('appointments.sms-settings.save') }}">@csrf
        <input name="enabled" type="hidden" value="0"><label class="mb-3"><input name="enabled" type="checkbox" value="1" @checked(old('enabled', $smsSettings->enabled))> SMS ile randevu alımını etkinleştir</label>
        <p class="text-muted">SMS kapalıysa yeni randevu talebi alınmaz. Mevcut talepler ve takip bağlantıları korunur. Başlangıçta yalnızca Türkiye cep telefonu numaraları kabul edilir.</p>
        <div class="row">
        <div class="form-group col-md-6"><label for="brand">Proje adı</label><input id="brand" class="form-control" name="brand" value="{{ old('brand', $smsSettings->brand) }}" maxlength="80" required></div>
        <div class="form-group col-md-6"><label for="api_key">İleti Merkezi API Anahtarı</label><input id="api_key" class="form-control" type="password" name="api_key" autocomplete="new-password" value=""><small>{{ $smsSettings->api_key ? 'Anahtar kayıtlı. Değiştirmek istemiyorsanız boş bırakın.' : 'Henüz anahtar girilmedi.' }}</small></div>
        <div class="form-group col-md-6"><label for="api_hash">İleti Merkezi API Hash</label><input id="api_hash" class="form-control" type="password" name="api_hash" autocomplete="new-password" value=""><small>{{ $smsSettings->api_hash ? 'Hash kayıtlı. Değiştirmek istemiyorsanız boş bırakın.' : 'Henüz hash girilmedi.' }}</small></div>
        <div class="form-group col-md-6"><label for="sender">Gönderici başlığı</label><input id="sender" class="form-control" name="sender" maxlength="11" value="{{ old('sender', $smsSettings->sender) }}"><small>İleti Merkezi hesabınızda onaylı başlık. APITEST, kodu değiştirdiği için randevu doğrulamasında kullanılamaz.</small></div>
        <div class="form-group col-md-6"><label for="daily_limit">Günlük yeni doğrulama SMS sınırı</label><input id="daily_limit" class="form-control" type="number" name="daily_limit" min="1" max="10000" value="{{ old('daily_limit', $smsSettings->daily_limit) }}" required><small>Bu sınıra ulaşınca yeni kod gönderilmez. Mevcut randevuların onay mesajları gönderilmeye devam eder.</small></div>
        </div>
        <div class="form-group"><label for="verification_template">Doğrulama kodu mesajı</label><textarea id="verification_template" name="verification_template" class="form-control" rows="3" maxlength="480" required>{{ old('verification_template', $smsSettings->verification_template) }}</textarea><small>Değişkenler: {kod}, {isletme}, {marka}. {kod} zorunludur.</small></div>
        <div class="form-group"><label for="approval_template">Randevu onay mesajı</label><textarea id="approval_template" name="approval_template" class="form-control" rows="3" maxlength="480" required>{{ old('approval_template', $smsSettings->approval_template) }}</textarea><small>Değişkenler: {isletme}, {tarih}, {saat}, {marka}. İşletme, tarih ve saat zorunludur.</small></div>
        <button class="btn btn-secondary">Ayarları kaydet</button>
    </form>
</div></div>
<div class="card"><div class="card-body"><h5>Randevu onay SMS’leri</h5>
<p>“SMS sağlayıcısı kabul etti” teslim edildi anlamına gelmez. Belirsiz veya sürmekte görünen gönderimi tekrar göndermeden önce SMS sağlayıcısı kayıtlarından kontrol edin.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>İşletme</th><th>Randevu</th><th>Telefon</th><th>Gönderim durumu</th><th>SMS sağlayıcısı kaydı / hata</th><th>İşlem</th></tr></thead><tbody>
@foreach ($messages as $message)<tr><td>{{ $message->appointment?->profile?->display_name ?? '—' }}</td><td>{{ dateFormat($message->appointment?->starts_at) }} {{ $message->appointment?->starts_at ? timeFormat($message->appointment->starts_at) : '' }}</td><td>•••• {{ substr($message->recipient, -4) }}</td><td>{{ ['pending'=>'Gönderim bekliyor', 'sending'=>'Gönderim sürüyor / sonuç bekleniyor', 'accepted'=>'SMS sağlayıcısı kabul etti', 'failed'=>'Gönderilemedi', 'unknown'=>'Gönderim sonucu belirsiz', 'cancelled'=>'İptal edildi'][$message->status] ?? $message->status }}</td><td>{{ $message->provider_sid ?? $message->error_code ?? '—' }}</td><td>@if (in_array($message->status, ['failed','pending']))<form method="post" action="{{ route('appointments.sms.retry', $message->id) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Göndermeyi dene</button></form>@endif</td></tr>@endforeach
</tbody></table></div>{{ $messages->links() }}
</div></div>
<div class="card"><div class="card-body"><h5>Son doğrulama SMS denemeleri</h5>
<p>İleti Merkezi panelinde API erişimini etkinleştirin. 401: anahtar/hash veya erişim izni; 402: bakiye; 450: başlık; 451: tekrar eden istek. Bağlantı hatasında sonuç belirsizdir.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Tarih</th><th>Durum</th><th>Sipariş / hata kodu</th></tr></thead><tbody>
@foreach ($challenges as $challenge)<tr><td>{{ dateFormat($challenge->created_at) }} {{ timeFormat($challenge->created_at) }}</td><td>{{ ['sending'=>'Sonuç bekleniyor', 'accepted'=>'Sağlayıcı kabul etti', 'failed'=>'Gönderilemedi', 'unknown'=>'Sonuç belirsiz'][$challenge->send_status] ?? $challenge->send_status }}</td><td>{{ $challenge->error_code ?? $challenge->provider_sid ?? '—' }}</td></tr>@endforeach
</tbody></table></div></div></div>
@endsection
