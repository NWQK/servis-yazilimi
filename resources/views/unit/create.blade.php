{{Form::open(array('url'=>'unit','method'=>'post'))}}
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-12">
            {{Form::label('unit',__('Unit'),array('class'=>'form-label')) }}
            {{Form::text('unit',null,array('class'=>'form-control','placeholder'=>__('Enter unit'),'required'=>'required'))}}
        </div>
    </div>
</div>
<div class="modal-footer">
    {{Form::submit(__('Create'),array('class'=>'btn btn-secondary ml-10'))}}
</div>
{{Form::close()}}

