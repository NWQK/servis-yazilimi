{{ Form::open(array('route' => array('invoice.payment', $invoice->id),'method'=>'post')) }}
<div class="modal-body">
    <div class="row">
        <div class="form-group  col-md-12">
            {{ Form::label('payment_date', __('Payment Date'),['class'=>'form-label']) }}
            {{ Form::date('payment_date', '', array('class' => 'form-control','required'=>'required')) }}
        </div>
        <div class="form-group  col-md-12">
            {{ Form::label('amount', __('Amount'),['class'=>'form-label']) }}
            {{ Form::text('amount',$invoice->getInvoiceTotalDueAmount(), array('class' => 'form-control','required'=>'required')) }}
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
