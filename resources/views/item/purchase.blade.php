<form method="post" action="{{ route('item.purchase.store', $item->id) }}">@csrf
<input type="hidden" name="request_key" value="{{ $requestKey }}">
<div class="modal-body"><h5>{{ $item->title }}</h5><p class="text-muted">Mevcut stok: {{ $item->quantity }} {{ $item->unit?->unit }}. Girilen miktar mevcut stoğa eklenir.</p>
<div class="row g-3">
    <div class="col-6"><label class="form-label" for="purchase-quantity">Alınan miktar</label><input id="purchase-quantity" name="quantity" class="form-control" type="number" min="1" max="100000000" step="1" required></div>
    <div class="col-6"><label class="form-label" for="purchase-price">Birim alış fiyatı (₺)</label><input id="purchase-price" name="unit_price" class="form-control" type="number" min="0" max="9999999.99" step="0.01" value="{{ $item->purchase_price }}" required></div>
    <div class="col-12 col-md-6"><label class="form-label" for="purchase-date">Alım tarihi</label><input id="purchase-date" name="purchase_date" class="form-control" type="date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required></div>
    <div class="col-12 col-md-6"><label class="form-label" for="purchase-supplier">Tedarikçi (isteğe bağlı)</label>{{ Form::select('supplier_id', $suppliers, $item->supplier_id, ['class'=>'form-control select2', 'id'=>'purchase-supplier']) }}</div>
    <div class="col-12"><label class="form-label" for="purchase-notes">Alım notu</label><textarea id="purchase-notes" class="form-control" name="notes" maxlength="2000" rows="2"></textarea></div>
</div><p class="small text-muted mt-3 mb-0">Alım, mevcut alış giderlerine bir kez işlenir. Tedarikçi borcu veya ödeme kaydı oluşturulmaz. Alım geçmişi kayıt tarihinden itibaren 2 yıl saklanır.</p></div>
<div class="modal-footer"><button class="btn btn-secondary" type="submit">Satın alımı kaydet</button></div>
</form>
