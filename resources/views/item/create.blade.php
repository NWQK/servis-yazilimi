{{ Form::open(['url' => 'item', 'method' => 'post']) }}
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
                data-url="{{ route('generate.template', ['item']) }}" data-title="{{ __('AI Content Generator') }}">
                <span>{{ __('AI Content Generator') }}</span>
            </a>
        </div>
    @endif
    <div class="row">
        <div class="form-group col-md-6">
            {{ Form::label('title', __('Title'), ['class' => 'form-label']) }}
            {{ Form::text('title', null, ['class' => 'form-control', 'placeholder' => __('Enter title'), 'required' => 'required']) }}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('item_code', __('Item Code'), ['class' => 'form-label']) }}
            {{ Form::text('item_code', null, ['class' => 'form-control', 'placeholder' => __('Enter item code'), 'required' => 'required']) }}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('quantity', __('Quantity'), ['class' => 'form-label']) }}
            {{ Form::number('quantity', null, ['class' => 'form-control', 'placeholder' => __('Enter quantity'), 'required' => 'required']) }}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('units', __('Unit'), ['class' => 'form-label']) }}
            {!! Form::select('units', $units, null, ['class' => 'form-control select2 ', 'required' => 'required']) !!}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('purchase_date', __('Purchase Date'), ['class' => 'form-label']) }}
            {{ Form::date('purchase_date', null, ['class' => 'form-control', 'required' => 'required']) }}
        </div>

        <div class="form-group col-md-6">
            {{ Form::label('taxs', __('Tax'), ['class' => 'form-label']) }}
            {!! Form::select('taxs[]', $taxs, null, [
                'class' => 'form-control select2 select2',
                'multiple',
                'required' => 'required',
            ]) !!}
        </div>

        <div class="form-group col-md-6">
            {{ Form::label('purchase_price', __('Purchase Price'), ['class' => 'form-label']) }}
            {{ Form::number('purchase_price', null, ['class' => 'form-control', 'placeholder' => __('Enter purchase price'), 'required' => 'required']) }}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('sales_price', __('Sales Price'), ['class' => 'form-label']) }}
            {{ Form::number('sales_price', null, ['class' => 'form-control', 'placeholder' => __('Enter sales price'), 'required' => 'required']) }}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('manufacturer_by', __('Manufacturer By'), ['class' => 'form-label']) }}
            {{ Form::text('manufacturer_by', null, ['class' => 'form-control', 'placeholder' => __('Enter manufacturer by'), 'required' => 'required']) }}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('warranty_information', __('Warranty Information'), ['class' => 'form-label']) }}
            {{ Form::textarea('warranty_information', null, ['class' => 'form-control', 'placeholder' => __('Enter warranty information'), 'rows' => 2]) }}
        </div>
        <div class="form-group col-md-12">
            {{ Form::label('notes', __('Notes'), ['class' => 'form-label']) }}
            {{ Form::textarea('notes', null, ['class' => 'form-control', 'placeholder' => __('Enter notes'), 'rows' => 2]) }}
        </div>
    </div>
</div>
<div class="modal-footer">
    {{ Form::submit(__('Create'), ['class' => 'btn btn-secondary ml-10']) }}
</div>
{{ Form::close() }}
