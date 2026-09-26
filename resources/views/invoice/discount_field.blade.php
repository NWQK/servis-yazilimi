<div class="form-group col-md-6">
    <label for="discount_amount" class="form-label">İndirim tutarı ({{ settings()['CURRENCY_SYMBOL'] }})</label>
    <input id="discount_amount" name="discount_amount" class="form-control" type="number" min="0" max="9999999999.99" step="0.01" value="{{ old('discount_amount', $invoice->discount_amount ?? 0) }}">
    <small class="text-muted">İsteğe bağlıdır. Vergiler dahil toplamdan düşülür. İndirim, fatura toplamını aşamaz; fazla tutar girilirse toplam kadar uygulanır.</small>
</div>
