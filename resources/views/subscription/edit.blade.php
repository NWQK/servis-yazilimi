{{ Form::model($subscription, ['route' => ['subscriptions.update', $subscription->id], 'method' => 'PUT']) }}
<div class="modal-body">
    <div class="row">
        <div class="form-group">
            {{ Form::label('title', __('Title'), ['class' => 'form-label']) }}
            {{ Form::text('title', null, ['class' => 'form-control', 'placeholder' => __('Enter subscription title'), 'required' => 'required']) }}
        </div>
        <div class="form-group">
            {{ Form::label('interval', __('Interval'), ['class' => 'form-label']) }}
            {!! Form::select('interval', $intervals, null, [
                'class' => 'form-control select2',
                'required' => 'required',
                $subscription->id == 1 ? 'disabled' : '' => $subscription->id == 1 ? 'disabled' : '',
            ]) !!}
            @if ($subscription->id == 1)
                <input type="hidden" name="interval" value="{{ $subscription->interval }}">
            @endif
        </div>
        <div class="form-group">
            {{ Form::label('package_amount', __('Package Amount'), ['class' => 'form-label']) }}
            {{ Form::number('package_amount', null, ['class' => 'form-control', 'placeholder' => __('Enter package amount'), 'step' => '0.01']) }}
        </div>
        <div class="form-group">
            {{ Form::label('user_limit', __('User Limit'), ['class' => 'form-label']) }}
            {{ Form::number('user_limit', null, ['class' => 'form-control', 'placeholder' => __('Enter user limit'), 'required' => 'required']) }}
        </div>
        <div class="form-group">
            {{ Form::label('client_limit', __('Client Limit'), ['class' => 'form-label']) }}
            {{ Form::number('client_limit', null, ['class' => 'form-control', 'placeholder' => __('Enter client limit'), 'required' => 'required']) }}
        </div>
        <div class="form-group">
            {{ Form::label('employee_limit', __('Employee Limit'), ['class' => 'form-label']) }}
            {{ Form::number('employee_limit', null, ['class' => 'form-control', 'placeholder' => __('Enter employee limit'), 'required' => 'required']) }}
        </div>

        <div class="form-group col-md-6">
            <div class="form-check form-switch custom-switch-v1 mb-2">
                <input type="checkbox" class="form-check-input input-secondary" name="enabled_logged_history"
                    id="enabled_logged_history" {{ $subscription->enabled_logged_history == 1 ? 'checked' : '' }}>
                {{ Form::label('enabled_logged_history', __('Show User Logged History'), ['class' => 'form-label']) }}
            </div>
        </div>
        <div class="form-group col-md-6">
            <div class="form-check form-switch custom-switch-v1 mb-2">
                <input type="checkbox" class="form-check-input input-secondary" name="enabled_openai"
                    id="enabled_openai" {{ $subscription->enabled_openai == 1 ? 'checked' : '' }}>
                {{ Form::label('enabled_openai', __('Enabled Open Ai Support'), ['class' => 'form-label']) }}
            </div>
        </div>
        <div class="form-group col-md-6">
            <div class="form-check form-switch custom-switch-v1 mb-2">
                <input type="checkbox" class="form-check-input input-secondary" name="enabled_n8n" id="enabled_n8n"
                    {{ $subscription->enabled_n8n == 1 ? 'checked' : '' }}>
                {{ Form::label('enabled_n8n', __('Enabled N8n'), ['class' => 'form-label']) }}
            </div>
        </div>
    </div>
</div>
<div class="modal-footer">

    {{ Form::submit(__('Update'), ['class' => 'btn btn-secondary btn-rounded']) }}
</div>
{{ Form::close() }}
