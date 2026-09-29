@can('manage company settings')
<div class="tab-pane {{ $activeTab === 'invoice_business' ? 'active show' : '' }}" id="invoice_business" role="tabpanel">
    <h5>İşletme bilgileri</h5>
    <p>Faturada gösterilecek işletme bilgilerini girin. Alanlar isteğe bağlıdır. Kaydedilen bilgiler yeni faturalara aktarılır; daha önce oluşturulan faturaların kayıtlı bilgileri değişmez.</p>
    <form method="post" action="{{ route('setting.invoice-business') }}">
        @csrf
        @include('invoice.billing_fields', ['business' => true, 'details' => \App\Services\InvoiceBilling::business(parentId())])
        <button type="submit" class="btn btn-primary">İşletme bilgilerini kaydet</button>
    </form>
</div>
@endcan
