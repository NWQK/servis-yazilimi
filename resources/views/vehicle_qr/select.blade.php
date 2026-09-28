<div class="form-group col-12">
    <label for="qr_code_id" class="form-label">Teslim edilecek QR etiketi</label>
    <select name="qr_code_id" id="qr_code_id" class="form-control">
        <option value="">QR seçmeden kaydet — takip bağlantısı oluştur</option>
        @foreach ($qrCodes as $qr)
            <option value="{{ $qr->id }}" @selected(old('qr_code_id') == $qr->id)>{{ $qr->label }} — Basılmış / boşta</option>
        @endforeach
    </select>
    <small>QR seçerseniz etiketin bağlantısı kullanılır. Boş bırakırsanız kalıcı bir takip bağlantısı oluşturulur; araç detayından aynı bağlantıyı QR olarak yazdırabilirsiniz.</small>
    @if ($qrCodes->isEmpty())
        <p class="text-muted mt-2">Basılmış etiket yok. QR seçmeden araç kaydedebilirsiniz.</p>
    @endif
    <a href="{{ route('vehicle-qr.index') }}" target="_blank" rel="noopener">QR etiketlerini yönet</a>
</div>
