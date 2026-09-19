{{ Form::open(['url' => 'service-type', 'method' => 'post']) }}
<div class="modal-body">
    @php
        $subscriptionData = currentSubscription();
    @endphp
    @if (settings()['openai_module'] == 'on' &&
            (Auth::user()->type !== 'super admin' ||
                ($subscriptionData['pricing_feature_settings'] === 'off' ||
                    $subscriptionData['subscription']->enabled_openai == 1)))
        <div class="text-end">
            <a href="javascript:void(0)" class="btn btn-primary mb-2 aiModal" data-size="lg"
                data-url="{{ route('generate.template', ['service_type']) }}"
                data-title="{{ __('AI Content Generator') }}">
                <span>{{ __('AI Content Generator') }}</span>
            </a>
        </div>
    @endif
    <div class="row">
        <div class="form-group col-md-12">
            {{ Form::label('type', __('Type'), ['class' => 'form-label']) }}
            {{ Form::text('type', null, ['class' => 'form-control', 'placeholder' => __('Enter type'), 'required' => 'required']) }}
        </div>
        <div class="form-group col-md-12">
            {{ Form::label('rate', __('Rate'), ['class' => 'form-label']) }}
            {{ Form::number('rate', null, ['class' => 'form-control', 'placeholder' => __('Enter rate'), 'step' => '0.01', 'min' => 0, 'required' => 'required']) }}
        </div>
        <div class="form-group col-md-12">
            {{ Form::label('tax', __('Tax'), ['class' => 'form-label']) }}
            {!! Form::select('tax[]', $taxes, null, [
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
    {{ Form::submit(__('Create'), ['class' => 'btn btn-secondary ml-10']) }}
</div>
{{ Form::close() }}
