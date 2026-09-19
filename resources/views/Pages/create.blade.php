{{ Form::open(['url' => 'pages', 'method' => 'post']) }}
<div class="modal-body">
    @php
        $subscriptionData = currentSubscription();
    @endphp
    @if (settings()['openai_module'] == 'on' && Auth::user()->type == 'super admin' )
        <div class="text-start">
            <a href="javascript:void(0)" class="btn btn-primary mb-2 aiModal" data-size="lg"
                data-url="{{ route('generate.template', ['custom_page']) }}"
                data-title="{{ __('AI Content Generator') }}">
                <span>{{ __('AI Content Generator') }}</span>
            </a>
        </div>
    @endif
    <div class="row">
        <div class="form-group  col-md-12">
            {{ Form::label('title', __('Title'), ['class' => 'form-label']) }}
            {{ Form::text('title', null, ['class' => 'form-control', 'placeholder' => __('Enter Title')]) }}
        </div>
        <div class="form-group col-md-12">
            {{ Form::label('enabled', __('Enabled Page'), ['class' => 'form-label']) }}
            {{ Form::hidden('enabled', 0, ['class' => 'form-check-input']) }}
            <div class="form-check form-switch">
                {{ Form::checkbox('enabled', 1, true, ['class' => 'form-check-input', 'role' => 'switch', 'id' => 'flexSwitchCheckChecked']) }}
                {{ Form::label('', '', ['class' => 'form-check-label']) }}
            </div>
        </div>
        <div class="form-group  col-md-12">
            {{ Form::label('content', __('Content'), ['class' => 'form-label']) }}
            {{ Form::textarea('content', null, ['class' => 'form-control', 'id' => 'classic-editor']) }}
        </div>
    </div>
</div>
<div class="modal-footer">
    {{ Form::submit(__('Create'), ['class' => 'btn btn-secondary btn-rounded']) }}
</div>
{{ Form::close() }}
