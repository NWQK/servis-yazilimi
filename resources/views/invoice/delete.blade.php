<form method="post" action="{{ route('invoice.destroy', $invoice->id) }}">@csrf @method('DELETE')
<div class="modal-body"><h5>{{ invoicePrefix().$invoice->invoice_id }} numaralı fatura silinsin mi?</h5><p>Fatura ve bağlı ödeme kayıtları silinecek. Bu işlem para iadesi yapmaz.</p>
<fieldset><legend class="h6">Fatura içerisindeki ürünler stoklara tekrar eklensin mi?</legend>
<label class="d-block py-2"><input type="radio" name="return_stock" value="1" required> Evet, ürünleri stoğa geri ekle.</label>
<label class="d-block py-2"><input type="radio" name="return_stock" value="0" required> Hayır, stoklar olduğu gibi kalsın.</label></fieldset>
@foreach($invoice->items as $line)<p class="mb-1">{{ $line->item_title }} · {{ $line->stock_quantity }} adet stok iadesi</p>@endforeach
</div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button><button class="btn btn-danger">Faturayı sil</button></div></form>
