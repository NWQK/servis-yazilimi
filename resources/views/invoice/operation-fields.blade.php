<input type="hidden" name="operation_token" value="{{ \App\Services\InvoiceJournal::token() }}">
@isset($invoice)<input type="hidden" name="invoice_revision" value="{{ (int)$invoice->revision }}">@endisset
