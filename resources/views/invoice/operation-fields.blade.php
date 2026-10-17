<input type="hidden" name="operation_token" value="{{ \App\Services\InvoiceJournal::token() }}">
@isset($invoice)<input type="hidden" name="invoice_revision" value="{{ (int)$invoice->revision }}">@endisset
@once
<link rel="stylesheet" href="{{ asset('css/invoice-change-preview.css') }}">
<script src="{{ asset('js/invoice-change-preview.js') }}" defer></script>
@endonce
