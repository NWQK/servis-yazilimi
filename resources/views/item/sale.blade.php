<form method="post" action="{{ route('item.sell', $item->id) }}">@csrf
<div class="modal-body">
    <h5>{{ $item->title }}</h5><p>Mevcut stok: <strong>{{ $item->quantity }}</strong> · Birim satış fiyatı: <strong>{{ priceFormat($item->sales_price) }}</strong></p>
    <p>Satış fiyatı × adet tutarı tahsil edilmiş gelir olarak kaydedilir. Ayrıca vergi eklenmez. Fatura veya müşteri kaydı oluşturulmaz.</p>
    <input type="hidden" name="request_key" value="{{ $requestKey }}"><input type="hidden" name="unit_price" value="{{ $item->sales_price }}">
    <label for="counter-quantity" class="form-label">Satılacak adet</label><input class="form-control" id="counter-quantity" type="number" name="quantity" min="1" max="{{ $item->quantity }}" value="1" step="1" required data-counter-price="{{ (int)round($item->sales_price * 100) }}">
    <p class="mt-3">Tahsil edilecek toplam: <output data-counter-total aria-live="polite"><strong>{{ priceFormat($item->sales_price) }}</strong></output></p>
    @if($item->quantity < 1)<p class="text-danger mt-2">Bu ürünün stoğu yok.</p>@endif
</div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button><button class="btn btn-secondary" @disabled($item->quantity < 1)>Satışı ve tahsilatı kaydet</button></div>
</form>
