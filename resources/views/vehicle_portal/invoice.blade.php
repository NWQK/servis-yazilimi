@extends('vehicle_portal.layout')
@section('title', 'Fatura ' . $settings['invoice_number_prefix'] . $invoice->invoice_id)
@section('content')
<div class="actions"><a href="{{ route('vehicle-portal.show', $code->token) }}#invoices">← Faturalara dön</a><button id="print-invoice" type="button">Yazdır / PDF kaydet</button></div>
<article class="card">
    <span class="eyebrow">FATURA</span><h1>{{ $settings['invoice_number_prefix'] . $invoice->invoice_id }}</h1>
    <p>{{ $vehicle->license_plate }} · {{ $vehicle->display_name }}</p>
    <p>{{ dateFormat($invoice->invoice_date) }} · {{ ['Ödenmedi', 'Kısmen ödendi', 'Ödendi'][$invoice->status] ?? '—' }}</p>
    <p>{{ $settings['company_address'] }} {{ $settings['company_phone'] }}</p>
    <div class="table-wrap"><table><thead><tr><th>İşlem / ürün</th><th>Miktar</th><th>Birim fiyat</th><th>Vergi</th><th>Tutar</th></tr></thead><tbody>
    @foreach ($lines as $line)<tr><td><strong>{{ $line['name'] }}</strong><p class="note">{{ $line['description'] }}</p></td><td>{{ $line['quantity'] }}</td><td>{{ number_format($line['price'], 2, ',', '.') }} {{ $settings['CURRENCY_SYMBOL'] }}</td><td>%{{ $line['tax_rate'] }}</td><td>{{ number_format($line['subtotal'] + $line['tax'], 2, ',', '.') }} {{ $settings['CURRENCY_SYMBOL'] }}</td></tr>@endforeach
    </tbody></table></div>
    <dl class="totals"><dt>Ara toplam</dt><dd>{{ number_format($lines->sum('subtotal'), 2, ',', '.') }} {{ $settings['CURRENCY_SYMBOL'] }}</dd><dt>Vergi</dt><dd>{{ number_format($lines->sum('tax'), 2, ',', '.') }} {{ $settings['CURRENCY_SYMBOL'] }}</dd><dt>Toplam</dt><dd><strong>{{ number_format($total, 2, ',', '.') }} {{ $settings['CURRENCY_SYMBOL'] }}</strong></dd><dt>Ödenen</dt><dd>{{ number_format($paid, 2, ',', '.') }} {{ $settings['CURRENCY_SYMBOL'] }}</dd><dt>Kalan</dt><dd><strong>{{ number_format(max(0, $total - $paid), 2, ',', '.') }} {{ $settings['CURRENCY_SYMBOL'] }}</strong></dd></dl>
    @if ($invoice->payments->isNotEmpty())<h2>Ödemeler</h2>@foreach ($invoice->payments as $payment)<p>{{ dateFormat($payment->payment_date) }} — {{ number_format($payment->amount, 2, ',', '.') }} {{ $settings['CURRENCY_SYMBOL'] }}</p>@endforeach @endif
</article>
@endsection
