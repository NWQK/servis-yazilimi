<div class="modal-body">
    <div class="product-card">
        <div class="row">
            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Title')}}</h6>
                    <p class="mb-20 text-muted">{{ $item->title}}</p>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Item Code')}}</h6>
                    <p class="mb-20 text-muted">{{$item->item_code}}</p>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Quantity')}}</h6>
                    <p class="mb-20 text-muted">{{$item->quantity}}</p>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Units')}}</h6>
                    <p class="mb-20 text-muted">{{!empty($item->unit)?$item->unit->unit:'-' }}</p>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Purchase Price')}}</h6>
                    <p class="mb-20 text-muted">{{priceFormat($item->purchase_price) }}</p>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Sales Price')}}</h6>
                    <p class="mb-20 text-muted">{{priceFormat($item->sales_price)}}</p>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Purchase Date')}}</h6>
                    <p class="mb-20 text-muted">{{dateFormat($item->purchase_date)}}</p>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Manufacturer By')}}</h6>
                    <p class="mb-20 text-muted">{{$item->manufacturer_by}}</p>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Tax')}}</h6>
                    <p class="mb-20 text-muted">

                      @foreach($item->taxes($item->taxs) as $tax)
                          {{$tax->title}} ({{$tax->rate.'%'}}) <br>
                      @endforeach
                    </p>
                </div>
            </div>

            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Warranty Information')}}</h6>
                    <p class="mb-20 text-muted">{{$item->warranty_information}}</p>
                </div>
            </div>
            <div class="col-12">
                <div class="detail-group">
                    <h6>{{__('Notes')}}</h6>
                    <p class="mb-20 text-muted">{{$item->notes}}</p>
                </div>
            </div>
        </div>
    </div>
</div>



