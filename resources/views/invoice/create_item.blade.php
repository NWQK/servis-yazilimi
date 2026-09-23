{{ Form::open(array('route' => array('invoice.item.store', $invoice->id),'method'=>'post')) }}
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-12">
            {{ Form::label('item', __('Item'),['class'=>'form-label']) }}
            {!! Form::select('item', $invoiceItems,null,array('class' => 'form-control select2','id'=>'item_id','required'=>'required')) !!}
        </div>
        <div class="form-group  col-md-12">
            {{ Form::label('quantity', __('Quantity'),['class'=>'form-label']) }}
            {{ Form::text('quantity',null, array('class' => 'form-control','required'=>'required')) }}
        </div>
        <div class="form-group  col-md-12">
            {{ Form::label('amount', __('Amount'),['class'=>'form-label']) }}
            {{ Form::text('amount',null, array('class' => 'form-control','readonly' => true, 'required'=>'required')) }}
        </div>
        <div class="form-group  col-md-12">
            {{ Form::label('description', __('Description'),['class'=>'form-label']) }}
            {{ Form::textarea('description', '', array('class' => 'form-control','rows'=>3)) }}
        </div>
    </div>
</div>
<div class="modal-footer">
    {{Form::submit(__('Create'),array('class'=>'btn btn-secondary ml-10'))}}
</div>
{{Form::close()}}
<script>

    $(document).on('change', '#item_id', function () {
        var invoiceItemId = $(this).val();
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
                var item = JSON.parse(data);
                $('#quantity').val(1);
                $('#amount').val(item.item.sales_price);
                $('#description').val(item.item.notes);
            },
        });
    });
</script>
