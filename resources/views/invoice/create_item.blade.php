{{ Form::open(array('route' => array('invoice.item.store', $invoice->id),'method'=>'post')) }}
<div class="modal-body">
    <div class="row location_list invoice-item-modal">
        @include('invoice.item_category', ['categoryColumn' => 'col-md-12'])
        <div class="form-group col-md-12">
            {{ Form::label('item', __('Item'),['class'=>'form-label']) }}
            {!! Form::select('item', $invoiceItems,null,array('class' => 'form-control select2 item_name_select','id'=>'modal_item_id','required'=>'required')) !!}
        </div>
        <div class="form-group  col-md-12">
            {{ Form::label('quantity', __('Quantity'),['class'=>'form-label']) }}
            {{ Form::number('quantity',null, array('class' => 'form-control quantity','min' => 1, 'step' => 1, 'required'=>'required')) }}
        </div>
        <div class="form-group  col-md-12">
            {{ Form::label('amount', __('Amount'),['class'=>'form-label']) }}
            {{ Form::text('amount',null, array('class' => 'form-control amount','readonly' => true, 'required'=>'required')) }}
        </div>
        <div class="form-group  col-md-12">
            {{ Form::label('description', __('Description'),['class'=>'form-label']) }}
            {{ Form::textarea('description', '', array('class' => 'form-control description','rows'=>3)) }}
        </div>
    </div>
</div>
<div class="modal-footer">
    {{Form::submit(__('Create'),array('class'=>'btn btn-secondary ml-10'))}}
</div>
{{Form::close()}}
<script>

    window.initItemCategories($('.invoice-item-modal'));
    $(document).off('change.invoiceItemModal', '#modal_item_id').on('change.invoiceItemModal', '#modal_item_id', function () {
        var invoiceItemId = $(this).val();
        if (!invoiceItemId) return;
        var row = $(this).closest(".location_list");
        $.ajax({
            url: "{{route('invoice.item')}}",
            type: 'post',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                'item_id': invoiceItemId
            },
            cache: false,
            success: function (data) {
                if (row.find(".item_name_select").val() !== invoiceItemId) return;
                var item = JSON.parse(data);
                row.find('.quantity').val(1);
                row.find('.amount').val(item.item.sales_price);
                row.find('.description').val(item.item.notes);
            },
        });
    });
</script>
