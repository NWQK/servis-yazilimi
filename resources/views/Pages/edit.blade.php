{{ Form::model($page, ['route' => ['pages.update', $page->id], 'method' => 'PUT']) }}
<div class="modal-body">
    <div class="row">
        <div class="form-group  col-md-12">
            {{ Form::label('title', __('Title'), ['class' => 'form-label']) }}
            {{ Form::text('title', null, ['class' => 'form-control', 'placeholder' => __('Enter Title')]) }}
        </div>
        <div class="form-group col-md-12">
            {{ Form::label('enabled', __('Enabled Page'), ['class' => 'form-label']) }}
            {{ Form::hidden('enabled', 0, ['class' => 'form-check-input']) }}
            <div class="form-check form-switch">
                {{ Form::checkbox('enabled', 1, $page->enabled == 1, ['class' => 'form-check-input', 'role' => 'switch', 'id' => 'flexSwitchCheckChecked']) }}
                {{ Form::label('', '', ['class' => 'form-check-label']) }}
            </div>
        </div>
        <div class="form-group  col-md-12">
            {{ Form::label('content', __('Content'), ['class' => 'form-label']) }}
            {!! Form::textarea('content', null, ['class' => 'form-control', 'id' => 'classic-editor']) !!}
        </div>
    </div>
</div>
<div class="modal-footer">
    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">{{ __('Close') }}</button>
    {{ Form::submit(__('Update'), ['class' => 'btn btn-secondary btn-rounded']) }}
</div>
{{ Form::close() }}
