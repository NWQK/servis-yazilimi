{{Form::open(array('url'=>'tax','method'=>'post'))}}
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-12">
            {{Form::label('title',__('Title'),array('class'=>'form-label')) }}
            {{Form::text('title',null,array('class'=>'form-control','placeholder'=>__('Enter title'),'required'=>'required'))}}
        </div>
        <div class="form-group col-md-12">
            {{Form::label('rate',__('Rate'),array('class'=>'form-label')) }}
            {{Form::number('rate',null,array('class'=>'form-control','placeholder'=>__('Enter rate'),'step'=>'any','required'=>'required'))}}
        </div>
    </div>
</div>
<div class="modal-footer">
    {{Form::submit(__('Create'),array('class'=>'btn btn-secondary ml-10'))}}
</div>
{{Form::close()}}

