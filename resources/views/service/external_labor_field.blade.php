<div class="form-group col-md-6">
    <label for="external_labor_amount" class="form-label">Harici işçilik tutarı ({{ settings()['CURRENCY_SYMBOL'] }})</label>
    <input type="number" id="external_labor_amount" name="external_labor_amount" class="form-control"
        min="0" max="9999999999.99" step="0.01" placeholder="0,00"
        value="{{ old('external_labor_amount', $laborAmount ?? '') }}">
    <small class="text-muted">İsteğe bağlıdır. Tutarı vergi hariç girin. Seçilen vergi faturaya ayrıca eklenir. Gelir, ödeme alındığında oluşur.</small>
</div>

<div class="form-group col-md-6">
    <label for="external_labor_tax_id" class="form-label">Harici işçilik vergisi</label>
    <select id="external_labor_tax_id" name="external_labor_tax_id" class="form-control">
        <option value="">Vergi seçilmedi</option>
        @foreach (\App\Models\Tax::where('parent_id', parentId())->orderBy('title')->get() as $laborTax)
            <option value="{{ $laborTax->id }}" @selected((string) old('external_labor_tax_id', $service->external_labor_tax_id ?? '') === (string) $laborTax->id)>{{ $laborTax->title }} (%{{ $laborTax->rate }})</option>
        @endforeach
    </select>
    <small class="text-muted">İsteğe bağlıdır. Vergi seçmezseniz ek vergi hesaplanmaz.</small>
</div>
