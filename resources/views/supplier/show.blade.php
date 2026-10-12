<div class="modal-body">
    <h5>{{ $supplier->company_name }}</h5>
    <dl class="row mt-3">
        <dt class="col-5">Firma personeli</dt><dd class="col-7 text-break">{{ $supplier->contact_name ?: 'Belirtilmedi' }}</dd>
        <dt class="col-5">Telefon</dt><dd class="col-7">@if($supplier->phone)<a href="tel:+90{{ $supplier->phone }}">+90 {{ $supplier->phone }}</a>@else Belirtilmedi @endif</dd>
        <dt class="col-5">E-posta</dt><dd class="col-7 text-break">@if($supplier->email)<a href="mailto:{{ $supplier->email }}">{{ $supplier->email }}</a>@else Belirtilmedi @endif</dd>
    </dl>
    @if($supplier->whatsapp_url)<a class="btn btn-success" href="{{ $supplier->whatsapp_url }}" target="_blank" rel="noopener noreferrer"><i class="ti ti-brand-whatsapp"></i> WhatsApp'tan ulaş</a>@endif
    <a class="btn btn-outline-secondary" href="{{ route('item.index', ['supplier'=>$supplier->id]) }}">Ürünleri görüntüle</a>
</div>
