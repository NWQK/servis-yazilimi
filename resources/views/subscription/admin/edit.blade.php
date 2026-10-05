<div class="modal-body">
    <h5>{{ $owner->name }}</h5><p class="text-muted">{{ $owner->email }} · {{ $capacity['used'] }} kayıtlı araç</p>
    @if($owner->subscription_suspended_at)
        <div class="alert alert-danger">Abonelik askıda. {{ $owner->subscription_suspension_reason }}<br><small>Paket veya tarih düzenlemek askıyı kaldırmaz. Aşağıdaki Etkinleştir düğmesini kullanın.</small></div>
    @endif
    <form method="post" action="{{ route('subscription-admin.update',$owner->id) }}">
        @csrf @method('PUT')
        <div class="row g-3">
            <div class="col-12 col-md-6"><label for="owner-subscription" class="form-label">Paket</label><select class="form-select" name="subscription" id="owner-subscription" required>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected((int)$owner->subscription === (int)$plan->id)>{{ $plan->title }}{{ $plan->vehicle_limit !== null ? ' · '.$plan->vehicle_limit.' araç' : '' }}</option>@endforeach</select></div>
            <div class="col-12 col-md-6"><label for="owner-subscription-expiry" class="form-label">Bitiş tarihi</label><input class="form-control" type="date" name="expiry_date" id="owner-subscription-expiry" value="{{ $owner->subscription_expire_date }}"></div>
            <div class="col-12"><label><input type="checkbox" name="no_expiry" value="1" class="form-check-input me-2" @checked(!$owner->subscription_expire_date)> Bitiş tarihi olmasın (süresiz)</label></div>
        </div>
        <p class="text-muted small mt-3">Mevcut araçlar silinmez. Araç sayısı yeni kapasiteyi aşarsa yeni araç eklenmesi engellenir. Bu işlem ödeme veya tahsilat kaydı oluşturmaz.</p>
        <button class="btn btn-secondary" type="submit">Paket ve süreyi kaydet</button>
    </form>
    <hr>
    <h6>Abonelik erişimi</h6><p class="text-muted small">Askıya alma panel erişimini ve yeni randevu alımını durdurur. Yeniden etkinleştirme paket veya bitiş tarihini değiştirmez. Hesap ve işletme kayıtları korunur.</p>
    <form method="post" action="{{ route('subscription-admin.status',$owner->id) }}" onsubmit="return confirm('Abonelik erişim durumu değiştirilsin mi?');">
        @csrf <input type="hidden" name="action" value="{{ $owner->subscription_suspended_at ? 'resume' : 'suspend' }}">
        @if(!$owner->subscription_suspended_at)<label for="suspension-reason" class="form-label">Askıya alma nedeni (isteğe bağlı)</label><textarea class="form-control mb-3" id="suspension-reason" name="reason" rows="2" maxlength="500"></textarea>@endif
        <input type="hidden" name="confirm" value="1">
        <button class="btn {{ $owner->subscription_suspended_at ? 'btn-success' : 'btn-outline-danger' }}" type="submit">{{ $owner->subscription_suspended_at ? 'Etkinleştir' : 'Askıya al' }}</button>
    </form>
    @if($changes->isNotEmpty())
        <hr><h6>Son abonelik işlemleri</h6>
        <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Tarih</th><th>İşlem / yönetici</th><th>Değişiklik</th></tr></thead><tbody>
        @foreach($changes as $change)
            <tr><td>{{ dateFormat($change->created_at) }} {{ $change->created_at->format('H:i') }}</td><td>{{ ['update'=>'Paket / süre düzenlendi','suspend'=>'Askıya alındı','resume'=>'Etkinleştirildi'][$change->action] ?? 'Düzenlendi' }}<br><small class="text-muted">{{ $change->actor?->name ?? 'Silinmiş yönetici' }}</small></td><td>
            @if($change->action === 'update')
                {{ $plans->firstWhere('id',$change->before['subscription'])?->title ?? 'Paket yok' }} → {{ $plans->firstWhere('id',$change->after['subscription'])?->title ?? 'Paket yok' }}<br><small>{{ $change->before['subscription_expire_date'] ? dateFormat($change->before['subscription_expire_date']) : 'Süresiz' }} → {{ $change->after['subscription_expire_date'] ? dateFormat($change->after['subscription_expire_date']) : 'Süresiz' }}</small>
            @else{{ $change->after['subscription_suspension_reason'] ?? 'Panel erişimi açıldı.' }}@endif
            </td></tr>
        @endforeach
        </tbody></table></div>
    @endif
</div>
