{{ Form::model($serviceType, ['route' => ['service-type.update', $serviceType->id], 'method' => 'PUT']) }}
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-12">
            {{ Form::label('type', __('Type'), ['class' => 'form-label']) }}
            {{ Form::text('type', null, ['class' => 'form-control', 'placeholder' => __('Enter type'), 'required' => 'required']) }}
        </div>
        <div class="form-group col-md-12">
            {{ Form::label('rate', __('Rate'), ['class' => 'form-label']) }}
            {{ Form::number('rate', null, ['class' => 'form-control', 'placeholder' => __('Enter rate'), 'step' => '0.01', 'min' => 0 ,'required' => 'required']) }}
        </div>
        <div class="form-group col-md-12">
            {{ Form::label('tax', __('Tax'), ['class' => 'form-label']) }}
            {!! Form::select('tax[]', $taxes, $tax ?? '-', [
                'class' => 'form-control select2 tax',
                'multiple' => true,
            ]) !!}
        </div>
        <div class="form-group col-md-12">
            {{ Form::label('note', __('Note'), ['class' => 'form-label']) }}
            {{ Form::text('note', null, ['class' => 'form-control', 'placeholder' => __('Enter note')]) }}
        </div>
    </div>
</div>
<div class="modal-footer">
    {{ Form::submit(__('Update'), ['class' => 'btn btn-secondary ml-10']) }}
</div>
{{ Form::close() }}
