{{ Form::model($item, array('route' => array('item.update', $item->id), 'method' => 'PUT')) }}
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-6">
            {{Form::label('title',__('Title'),array('class'=>'form-label')) }}
            {{Form::text('title',null,array('class'=>'form-control','placeholder'=>__('Enter title'),'required'=>'required'))}}
        </div>
        <div class="form-group col-md-6">
            {{Form::label('item_code',__('Item Code'),array('class'=>'form-label')) }}
            {{Form::text('item_code',null,array('class'=>'form-control','placeholder'=>__('Enter item code'),'required'=>'required'))}}
        </div>
        <div class="form-group col-md-6">
            {{Form::label('quantity',__('Quantity'),array('class'=>'form-label')) }}
            {{Form::number('quantity',null,array('class'=>'form-control','placeholder'=>__('Enter quantity'),'required'=>'required'))}}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('units', __('Unit'),['class'=>'form-label']) }}
            {!! Form::select('units', $units,null,array('class' => 'form-control select2 ','required'=>'required')) !!}
        </div>
        <div class="form-group col-md-6">
            {{Form::label('purchase_date',__('Purchase Date'),array('class'=>'form-label')) }}
            {{Form::date('purchase_date',null,array('class'=>'form-control','required'=>'required'))}}
        </div>

        <div class="form-group col-md-6">
            {{ Form::label('taxs', __('Tax'),['class'=>'form-label']) }}
            {!! Form::select('taxs[]', $taxs,!empty($item->taxs)?explode(',',$item->taxs):null,array('class' => 'form-control select2 select2','multiple','required'=>'required')) !!}
        </div>

        <div class="form-group col-md-6">
            {{Form::label('purchase_price',__('Purchase Price'),array('class'=>'form-label')) }}
            {{Form::number('purchase_price',null,array('class'=>'form-control','placeholder'=>__('Enter purchase price'),'required'=>'required'))}}
        </div>
        <div class="form-group col-md-6">
            {{Form::label('sales_price',__('Sales Price'),array('class'=>'form-label')) }}
            {{Form::number('sales_price',null,array('class'=>'form-control','placeholder'=>__('Enter sales price'),'required'=>'required'))}}
        </div>
        <div class="form-group col-md-6">
            {{Form::label('manufacturer_by',__('Manufacturer By'),array('class'=>'form-label')) }}
            {{Form::text('manufacturer_by',null,array('class'=>'form-control','placeholder'=>__('Enter manufacturer by'),'required'=>'required'))}}
        </div>
        <div class="form-group col-md-6">
            {{Form::label('warranty_information',__('Warranty Information'),array('class'=>'form-label')) }}
            {{Form::textarea('warranty_information',null,array('class'=>'form-control','placeholder'=>__('Enter warranty information'),'rows'=>2))}}
        </div>
        <div class="form-group col-md-12">
            {{Form::label('notes',__('Notes'),array('class'=>'form-label')) }}
            {{Form::textarea('notes',null,array('class'=>'form-control','placeholder'=>__('Enter notes'),'rows'=>2))}}
        </div>
    </div>
</div>
<div class="modal-footer">
    {{Form::submit(__('Update'),array('class'=>'btn btn-secondary ml-10'))}}
</div>
{{Form::close()}}


