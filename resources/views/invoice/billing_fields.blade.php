@php
    $business = $business ?? false;
    $details = $details ?? ($invoice->customer_details ?? (isset($invoice) ? \App\Services\InvoiceBilling::customer($invoice->parent_id, $invoice->client) : []));
@endphp
<div class="row">
    @foreach(\App\Services\InvoiceBilling::fields($business) as $key => $label)
    <div class="{{ $key === 'address' ? 'col-12' : 'col-md-6' }} mb-3">
        <label for="billing_{{ $key }}" class="form-label">{{ $label }}</label>
        @if($key === 'address')
            <textarea id="billing_{{ $key }}" name="billing[{{ $key }}]" class="form-control" rows="2" maxlength="1000">{{ old('billing.'.$key, $details[$key] ?? '') }}</textarea>
        @elseif($key === 'phone')
            <div class="input-group"><span class="input-group-text">+90</span><input id="billing_phone" type="tel" name="billing[phone]" class="form-control" inputmode="numeric" pattern="[2-5][0-9]{9}" maxlength="10" placeholder="Başında sıfır olmadan 10 hane" value="{{ old('billing.phone', $details['phone'] ?? '') }}"></div>
        @else
            <input id="billing_{{ $key }}" name="billing[{{ $key }}]" type="{{ $key === 'email' ? 'email' : 'text' }}" class="form-control" maxlength="{{ ['tax_number'=>11, 'postcode'=>5, 'mersis'=>16, 'email'=>254][$key] ?? 255 }}" value="{{ old('billing.'.$key, $details[$key] ?? '') }}">
        @endif
        @error('billing.'.$key)<div class="text-danger">{{ $message }}</div>@enderror
    </div>
    @endforeach
</div>
