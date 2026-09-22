<div class="form-group col-12">
    <label for="qr_code_id" class="form-label">Teslim edilecek QR etiketi</label>
    <select name="qr_code_id" id="qr_code_id" class="form-control" required>
        <option value="">Etiketin üzerindeki QR numarasını seçin</option>
        @foreach ($qrCodes as $qr)
            <option value="{{ $qr->id }}" @selected(old('qr_code_id') == $qr->id)>{{ $qr->label }} — Basılmış / boşta</option>
        @endforeach
    </select>
    <small>Etiketteki numarayı eşleştirin. Kayıt tamamlandığında bu QR araca kalıcı olarak bağlanır.</small>
    @if ($qrCodes->isEmpty())
        <p class="text-danger mt-2">Önce QR etiketlerini basıp basılmış olarak işaretleyin.</p>
    @endif
    <a href="{{ route('vehicle-qr.index') }}" target="_blank" rel="noopener">QR etiketlerini yönet</a>
</div>
