<div class="form-group {{ $categoryColumn ?? 'col-md-2 col-lg-2' }}">
    <label class="form-label d-block">Ürün Kategorisi
        <select class="form-control item-category-select" aria-label="Ürün Kategorisi"
            data-default="{{ ($productSale??false)?'all':'' }}"
            data-products="{{ json_encode($itemCatalog, JSON_UNESCAPED_UNICODE) }}">
            @if($productSale??false)<option value="all">Tüm ürünler</option>@endif
            <option value="">Kategori seçin</option>
            <option value="uncategorized">Kategorisiz</option>
            @foreach($itemCategories as $categoryId => $categoryName)
                <option value="{{ $categoryId }}">{{ $categoryName }}</option>
            @endforeach
        </select>
    </label>
</div>
