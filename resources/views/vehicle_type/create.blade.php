{{ Form::open(['url' => 'vehicle-type', 'method' => 'post']) }}
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
                data-url="{{ route('generate.template', ['vehicle_type']) }}"
                data-title="{{ __('AI Content Generator') }}">
                <span>{{ __('AI Content Generator') }}</span>
            </a>
        </div>
    @endif
    <div class="row">
        <div class="form-group col-md-12">
            {{ Form::label('type', __('Brand'), ['class' => 'form-label']) }}
            {{ Form::text('type', null, ['class' => 'form-control', 'placeholder' => __('Enter brand'), 'required' => 'required']) }}
        </div>
    </div>
</div>
<div class="modal-footer">
    {{ Form::submit(__('Create'), ['class' => 'btn btn-secondary ml-10']) }}
</div>
{{ Form::close() }}
