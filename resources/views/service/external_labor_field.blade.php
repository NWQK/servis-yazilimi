<div class="form-group col-md-6">
    <label for="external_labor_amount" class="form-label">Harici işçilik tutarı ({{ settings()['CURRENCY_SYMBOL'] }})</label>
    <input type="number" id="external_labor_amount" name="external_labor_amount" class="form-control"
        min="0" max="9999999999.99" step="0.01" placeholder="0,00"
        value="{{ old('external_labor_amount', $laborAmount ?? '') }}">
    <small class="text-muted">İsteğe bağlıdır. Girilen tutar faturaya aynen eklenir; ayrıca vergi hesaplanmaz. Gelir, ödeme alındığında oluşur.</small>
</div>
