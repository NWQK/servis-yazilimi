{{ Form::model($expense, array('route' => array('expense.update', $expense->id), 'enctype' => "multipart/form-data", 'method' => 'PUT')) }}
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-6">
            {{Form::label('title',__('Title'),array('class'=>'form-label')) }}
            {{Form::text('title',null,array('class'=>'form-control','placeholder'=>__('Enter expense title'),'required'=>'required'))}}
        </div>
        <div class="form-group col-md-6">
            {{Form::label('date',__('Date'),array('class'=>'form-label')) }}
            {{Form::date('date',null,array('class'=>'form-control','required'=>'required'))}}
        </div>
        <div class="form-group col-md-6">
            {{Form::label('amount',__('Total Amount'),array('class'=>'form-label')) }}
            {{Form::number('amount',null,array('class'=>'form-control','placeholder'=>__('Enter expense amount'),'required'=>'required'))}}
        </div>
        <div class="form-group col-md-6">
            {{Form::label('receipt',__('Receipt'),array('class'=>'form-label')) }}
            {{Form::file('receipt',array('class'=>'form-control'))}}
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


