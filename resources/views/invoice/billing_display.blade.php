@php
    $businessDetails = $invoice->business_details ?? \App\Services\InvoiceBilling::business($invoice->parent_id);
    $customerDetails = $invoice->customer_details;
    if ($customerDetails === null) {
        $customerDetails = ['name' => $invoice->clients->name ?? '', 'phone' => $invoice->clients->phone_number ?? '',
            'address' => $invoice->clients->clients->address ?? '', 'city' => $invoice->clients->clients->city ?? ''];
    }
    $customerDetails['name'] = $customerDetails['name'] ?? ($invoice->clients->name ?? '');
@endphp
<div class="invoice-billing-parties">
    @foreach(['Satıcı bilgileri' => $businessDetails, 'Müşteri bilgileri' => $customerDetails] as $heading => $party)
    <section class="invoice-billing-party">
        <h5>{{ $heading }}</h5>
        @if(!empty($party['name']))<p><strong>{{ $party['name'] }}</strong></p>@endif
        @foreach(\App\Services\InvoiceBilling::fields(true) as $key => $label)
            @if($key !== 'name' && !empty($party[$key]))
                <p>{{ $label }}: {{ $key === 'phone' && preg_match('/^[2-5][0-9]{9}$/', $party[$key]) ? '+90 '.$party[$key] : $party[$key] }}</p>
            @endif
        @endforeach
    </section>
    @endforeach
</div>
