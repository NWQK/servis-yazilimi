<div class="twofa-panel">
    <style>
        .twofa-panel{max-width:900px;padding:8px}.twofa-panel h3{font-size:22px;margin-bottom:10px}.twofa-muted{color:#65758b;line-height:1.7}.twofa-grid{display:grid;grid-template-columns:260px 1fr;gap:28px;align-items:start;margin-top:24px}.twofa-qr{background:#fff;border:1px solid #e1e5eb;border-radius:18px;padding:10px;text-align:center}.twofa-qr img{width:240px;max-width:100%;height:auto;display:block;margin:auto}.twofa-code{font-family:monospace;letter-spacing:3px;font-size:23px;max-width:300px}.twofa-key{overflow-wrap:anywhere;user-select:all}.twofa-recovery{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;background:#f5f6f8;color:#18202b;padding:18px;border-radius:12px}.twofa-recovery code{overflow-wrap:anywhere;color:#18202b}.twofa-panel .alert{margin-top:18px}@media(max-width:640px){.twofa-grid{grid-template-columns:1fr;gap:20px}.twofa-qr{max-width:260px}.twofa-recovery{grid-template-columns:1fr}.twofa-code{width:100%;max-width:none}}
    </style>
    <h3>İki aşamalı doğrulama</h3>
    @if(Auth::user()->twofa_required)<div class="alert alert-info">İki aşamalı doğrulama süper admin tarafından zorunlu tutuluyor. Kurulum tamamlanmadan paneli kullanamazsınız.</div>@endif
    @if(!Auth::user()->twofa_secret)
        <span class="badge bg-warning text-dark">Devre dışı</span>
        <p class="twofa-muted mt-3">Hesabınızı şifrenize ek olarak telefonunuzdaki doğrulama uygulamasıyla koruyun. Google Authenticator veya Microsoft Authenticator kullanabilirsiniz.</p>
        @php($twofaQr = QrCode2FA())
        <div class="twofa-grid">
            <div class="twofa-qr"><img src="{{ $twofaQr }}" alt="Doğrulama uygulaması kurulum QR kodu" width="240" height="240"></div>
            <div>
                <h5>1. QR kodunu okutun</h5>
                <p class="twofa-muted">Doğrulama uygulamasında hesap ekleyin ve bu QR kodunu tarayın.</p>
                <details class="mb-4"><summary>QR kodunu okutamıyor musunuz?</summary><p class="twofa-muted mt-2">Uygulamada kurulum anahtarını elle girin ve zamana dayalı kod seçin. Bu anahtarı kimseyle paylaşmayın.</p><code class="twofa-key">{{ session('2fa_secret') }}</code></details>
                <h5>2. Altı haneli kodu girin</h5>
                <p class="twofa-muted">Telefonunuzdaki uygulamanın gösterdiği güncel kodu yazın.</p>
                <form method="POST" action="{{ route('setting.twofa.enable') }}">@csrf
                    <label for="twofa-setup-code" class="form-label">Doğrulama kodu</label>
                    <input id="twofa-setup-code" name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" class="form-control twofa-code" placeholder="000000" required>
                    @error('otp')<div class="text-danger mt-2">{{ $message }}</div>@enderror
                    <button class="btn btn-primary mt-3" type="submit">Doğrulamayı etkinleştir</button>
                </form>
            </div>
        </div>
    @else
        <span class="badge bg-success">Etkin</span>
        <p class="twofa-muted mt-3">Giriş yaparken doğrulama uygulamanızdaki altı haneli kod istenir.</p>
        @if(session('twofa_recovery_codes'))
            <div class="alert alert-warning">Bu kurtarma kodları yalnızca bu kez gösterilir. Telefonunuza erişemediğinizde her kodu bir kez kullanabilirsiniz. Güvenli bir yere kaydedin.</div>
            <div class="twofa-recovery">@foreach(session('twofa_recovery_codes') as $code)<code>{{ $code }}</code>@endforeach</div>
        @else
            <p class="twofa-muted">Kalan kurtarma kodu: {{ count(Auth::user()->twofa_recovery_codes ?? []) }}. Yeni kodlar oluşturmak için doğrulamayı kapatıp yeniden etkinleştirin.</p>
        @endif
        @unless(Auth::user()->twofa_required)
        <details class="mt-4"><summary>İki aşamalı doğrulamayı kapat</summary>
            <p class="twofa-muted mt-3">Kapatmak için mevcut şifrenizi ve uygulamanızdaki yeni bir kodu veya kullanılmamış kurtarma kodunu girin. Az önce kullandığınız kodun yenilenmesini bekleyin.</p>
            <form method="POST" action="{{ route('2fa.disable') }}">@csrf
                <label for="twofa-password" class="form-label">Mevcut şifreniz</label>
                <input id="twofa-password" name="password" type="password" autocomplete="current-password" class="form-control mb-3" required>
                <label for="twofa-disable-code" class="form-label">Doğrulama veya kurtarma kodu</label>
                <input id="twofa-disable-code" name="otp" type="text" maxlength="32" autocomplete="one-time-code" class="form-control" required>
                @error('otp')<div class="text-danger mt-2">{{ $message }}</div>@enderror
                @error('password')<div class="text-danger mt-2">{{ $message }}</div>@enderror
                <button class="btn btn-danger mt-3" type="submit">Doğrulamayı kapat</button>
            </form>
        </details>
        @endunless
    @endif
</div>
