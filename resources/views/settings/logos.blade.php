<hr class="my-4">
<h5>İşletme logoları</h5>
<p class="text-muted">Panelde ve faturalarınızda kullanılacak görselleri buradan kaydedebilirsiniz.</p>
<form action="{{ route('setting.general') }}" method="post" enctype="multipart/form-data">
    @csrf
    <div class="row g-3">
        @foreach(['logo'=>['Panel logosu','company_logo'], 'light_logo'=>['Koyu tema logosu','light_logo'], 'favicon'=>['Sekme simgesi','company_favicon']] as $field=>$definition)
            <div class="col-12 col-md-4">
                <label for="business-{{ $field }}" class="form-label">{{ $definition[0] }}</label>
                <a href="{{ asset(Storage::url('upload/logo/'.$settings[$definition[1]])) }}" target="_blank" rel="noopener" class="ms-2">Görüntüle</a>
                <input type="file" id="business-{{ $field }}" name="{{ $field }}" accept="image/png" class="form-control">
                <small class="text-muted">PNG, en fazla 2 MB. Boş bırakırsanız mevcut görsel korunur.</small>
            </div>
        @endforeach
        <div class="col-12">
            <label for="business-invoice-logo" class="form-label">Faturada görünecek logo</label>
            <input type="file" id="business-invoice-logo" name="invoice_logo" accept="image/png,image/jpeg,image/webp" class="form-control">
            <p class="text-muted small mt-2">Önerilen boyut: <strong>600 × 240 piksel</strong>. PNG, JPG veya WebP, en fazla 2 MB ve 6000 × 6000 piksel. Logo, faturada oranı korunarak 200 × 80 piksellik alana otomatik sığdırılır; kesilmez veya esnetilmez. Şeffaf arka planlı PNG önerilir. Ayrı logo yüklemezseniz panel logosu kullanılır.</p>
            <p class="mb-2">Faturadaki görünüm:</p>
            <div id="invoice-logo-preview" class="p-3 border rounded" style="background:#fff;max-width:280px;">
                <x-invoice-logo :owner-id="parentId()" />
            </div>
        </div>
    </div>
    <div class="text-end mt-3"><button type="submit" class="btn btn-secondary">Logoları kaydet</button></div>
</form>
<script>
(() => {
    const input = document.getElementById('business-invoice-logo');
    const image = document.querySelector('#invoice-logo-preview img');
    const original = image.src;
    let temporaryUrl;
    input.addEventListener('change', () => {
        if (temporaryUrl) URL.revokeObjectURL(temporaryUrl);
        const file = input.files[0];
        temporaryUrl = file && ['image/png','image/jpeg','image/webp'].includes(file.type) && file.size <= 2 * 1024 * 1024 ? URL.createObjectURL(file) : null;
        image.src = temporaryUrl || original;
    });
})();
</script>
