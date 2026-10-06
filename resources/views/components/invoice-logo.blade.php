@props(['ownerId'])
<div class="invoice-logo-frame" style="width:200px;max-width:100%;height:80px;display:flex;align-items:center;margin-bottom:12px;overflow:hidden;">
    <img src="{{ invoiceLogoUrl((int)$ownerId) }}" alt="İşletme logosu" width="200" height="80" style="display:block;width:100%;height:80px;max-height:80px;object-fit:contain;object-position:left center;" />
</div>
