<div class="form-group col-md-6">
    {{ Form::label('supplier_id', 'Tedarikçi (isteğe bağlı)', ['class'=>'form-label']) }}
    {{ Form::select('supplier_id', $suppliers, null, ['class'=>'form-control select2']) }}
    <small class="text-muted">Seçmeden de ürün ekleyebilirsiniz.</small>
</div>
<div class="form-group col-md-6">
    {{ Form::label('stock_alert_quantity', 'Stok uyarı miktarı (isteğe bağlı)', ['class'=>'form-label']) }}
    {{ Form::number('stock_alert_quantity', null, ['class'=>'form-control', 'min'=>0, 'max'=>100000000, 'step'=>1, 'placeholder'=>'Uyarı istemiyorsanız boş bırakın']) }}
    <small class="text-muted">Stok bu miktara veya altına indiğinde bildirim gelir. Boşsa kapalıdır; 0 girerseniz stok tükendiğinde uyarır.</small>
</div>
