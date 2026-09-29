<div class="col-12"><div class="card"><div class="card-body">
    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#invoice-customer-details" aria-controls="invoice-customer-details" aria-expanded="{{ $errors->has('billing.*') || !empty($invoice->customer_details) ? 'true' : 'false' }}">Müşteri bilgileri ekle</button>
    <p id="billing-autofill-status" class="text-muted mt-2" role="status">Müşteri seçildiğinde kayıtlı iletişim ve adres bilgileri otomatik alınır.</p>
    <div id="invoice-customer-details" class="collapse {{ $errors->has('billing.*') || !empty($invoice->customer_details) ? 'show' : '' }} mt-3">
        <p class="text-muted">Kayıtlı müşteri bilgileri otomatik alınır. Eksik bilgileri isteğe bağlı olarak tamamlayabilirsiniz. Buradaki değişiklikler yalnızca bu faturaya kaydedilir.</p>
        @include('invoice.billing_fields', ['business' => false])
    </div>
</div></div></div>
@push('script-page')
<script>
$(function () {
    const selector = $('#client_id');
    let requestNumber = 0;
    const fields = @json(array_keys(\App\Services\InvoiceBilling::fields()));
    function loadCustomer() {
        const id = selector.val();
        const current = ++requestNumber;
        fields.forEach(key => $('#billing_' + key).val(''));
        if (!id) return;
        $('#billing-autofill-status').text('Müşteri bilgileri yükleniyor…');
        $.getJSON(@json(route('invoice.billing.customer', ':id')).replace(':id', id))
            .done(function (data) {
                if (current !== requestNumber) return;
                fields.forEach(key => $('#billing_' + key).val(data[key] || ''));
                $('#billing-autofill-status').text('Kayıtlı müşteri bilgileri alındı. İsterseniz ekleyebilir veya düzenleyebilirsiniz.');
            }).fail(function () {
                if (current !== requestNumber) return;
                $('#billing-autofill-status').text('Bilgiler yüklenemedi. Müşteriyi yeniden seçebilir veya bilgileri elle doldurabilirsiniz.');
            });
    }
    selector.on('change', loadCustomer);
    @if(!isset($invoice) && !old('billing'))
        if (selector.val()) loadCustomer();
    @endif
});
</script>
@endpush
