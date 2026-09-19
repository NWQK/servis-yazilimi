@extends('layouts.app')

@section('page-title')
    {{ __('Invoices') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
    </li>
    <li class="breadcrumb-item" aria-current="page">
        <a href="{{ route('invoice.index') }}">{{ __('Invoices') }}</a>
    </li>
    <li class="breadcrumb-item" aria-current="page">
        {{ __('Create Invoice') }}
    </li>
@endsection

@section('content')
    <div class="row mt-1">
        {{ Form::model($invoice, ['route' => ['invoice.update', encrypt($invoice->id)], 'method' => 'put']) }}
        <div class="col-sm-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-2 col-lg-3">
                            {{ Form::label('invoice_id', __('Invoice ID'), ['class' => 'form-label']) }}
                            {{ Form::text('invoice_id', invoicePrefix() . $invoiceId, ['class' => 'form-control', 'required' => 'required', 'readonly']) }}
                        </div>
                        <div class="form-group col-md-2 col-lg-3">
                            {{ Form::label('invoice_date', __('Invoice Date'), ['class' => 'form-label']) }}
                            {{ Form::date('invoice_date', null, ['class' => 'form-control', 'required' => 'required']) }}
                        </div>
                        <div class="form-group col-md-3 col-lg-3">
                            {{ Form::label('client', __('Client'), ['class' => 'form-label']) }}
                            {{ Form::select('client', $clients, null, ['class' => 'form-control select2', 'id' => 'client_id', 'required' => 'required']) }}
                        </div>
                        <div class="form-group col-md-3 col-lg-3">
                            {{ Form::label('service_id', __('Service'), ['class' => 'form-label']) }}
                            <div class="service_div">
                                <select class="form-control select2 service" id="service" name="service">
                                    <option value="">{{ __('Select Service') }}</option>

                                    @foreach ($serviceData as $service)
                                        <option value="{{ $service['id'] }}"
                                            {{ $selectedService == $service['id'] ? 'selected' : '' }}>
                                            {{ $service['name'] }}
                                        </option>
                                    @endforeach

                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card repeater">
            <div class="card-header">
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">{{ __('Service Type') }}</h5>
                    <a class="btn btn-secondary d-flex align-items-center gap-2" href="#" data-repeater-create="">
                        <i class="ti ti-circle-plus align-text-bottom"></i>{{ __('Add Service Type') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table class="display dataTable cell-border">
                    <thead>
                        <tr>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Rate') }}</th>
                            <th>{{ __('Tax') }}</th>
                            <th>{{ __('Note') }}</th>
                            <th>#</th>
                        </tr>
                    </thead>
                    <tbody data-repeater-list="types">

                        @if (!empty($existingTypes) && count($existingTypes) > 0)
                            @foreach ($existingTypes as $index => $type)
                                <tr data-repeater-item>
                                    <td width="30%">
                                        {!! Form::select('types[' . $index . '][service_type]', $types, $type['service_type'], [
                                            'class' => 'form-control select2 service-type',
                                        ]) !!}
                                    </td>
                                    <td>
                                        {{ Form::text('types[' . $index . '][rate]', $type['rate'], [
                                            'class' => 'form-control rate',
                                            'readonly' => true,
                                        ]) }}
                                    </td>
                                    <td width="30%">
                                        {!! Form::select('types[' . $index . '][tax][]', $taxs, $type['tax'], [
                                            'class' => 'form-control select2 tax',
                                            'multiple' => true,
                                        ]) !!}
                                    </td>
                                    <td>
                                        {{ Form::textarea('types[' . $index . '][note]', $type['note'], [
                                            'class' => 'form-control note',
                                            'rows' => 1,
                                            'readonly' => true,
                                        ]) }}
                                    </td>
                                    <td>
                                        <a class="text-danger" data-repeater-delete href="#">
                                            <i data-feather="trash-2"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            <!-- Optional: empty row only if no existing types -->
                            <tr data-repeater-item>
                                <td width="30%">
                                    {!! Form::select('types[INDEX][service_type]', $types, null, [
                                        'class' => 'form-control select2 service-type',
                                    ]) !!}
                                </td>
                                <td>
                                    {{ Form::text('types[INDEX][rate]', null, [
                                        'class' => 'form-control rate',
                                        'readonly' => true,
                                    ]) }}
                                </td>
                                <td width="30%">
                                    {!! Form::select(
                                        'types[INDEX][tax][]',
                                        $taxs,
                                        [],
                                        [
                                            'class' => 'form-control select2 tax',
                                            'multiple' => true,
                                        ],
                                    ) !!}
                                </td>
                                <td>
                                    {{ Form::textarea('types[INDEX][note]', null, [
                                        'class' => 'form-control note',
                                        'rows' => 1,
                                        'readonly' => true,
                                    ]) }}
                                </td>
                                <td>
                                    <a class="text-danger" data-repeater-delete href="#">
                                        <i data-feather="trash-2"></i>
                                    </a>
                                </td>
                            </tr>
                        @endif

                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-sm-12">
            <div class="card">
                <div class="card-body">
                    <div class="card location">
                        <div class="card-body">
                            @if (!empty($invoice->items->toArray()) && count($invoice->items->toArray()) > 0)
                                @foreach ($invoice->items as $key => $item)
                                    <div class="row location_list">
                                        <div class="form-group col-md-3 col-lg-3">
                                            {{ Form::label('item', __('Item'), ['class' => 'form-label']) }}
                                            {!! Form::select('item[' . $key . ']', $items, $item->item, [
                                                'class' => 'form-control select2 item_name_select',
                                                'id' => 'item_id',
                                            ]) !!}
                                        </div>
                                        <div class="form-group col-md-1 col-lg-1">
                                            {{ Form::label('quantity', __('Quantity'), ['class' => 'form-label']) }}
                                            {{ Form::number('quantity[' . $key . ']', $item->quantity, ['class' => 'form-control quantity', 'required' => 'required']) }}
                                        </div>
                                        <div class="form-group col-md-1 col-lg-1">
                                            {{ Form::label('amount', __('Amount'), ['class' => 'form-label']) }}
                                            {{ Form::number('amount[' . $key . ']', $item->amount, ['class' => 'form-control amount', 'step' => '0.01', 'required' => 'required']) }}
                                        </div>
                                        <div class="form-group col-md-2 col-lg-2">
                                            {{ Form::label('tax', __('Tax'), ['class' => 'form-label']) }}
                                            {!! Form::select('tax[' . $key . '][]', $taxs, $item->tax ? explode(',', $item->tax) : [], [
                                                'class' => 'form-control tax_id select2',
                                                'multiple' => true,
                                            ]) !!}
                                        </div>
                                        <div class="form-group col-md-4 col-lg-4">
                                            {{ Form::label('description', __('Description'), ['class' => 'form-label']) }}
                                            {{ Form::textarea('description[' . $key . ']', $item->description, ['class' => 'form-control description', 'rows' => 2]) }}
                                        </div>
                                        <div class="col-1 m-auto">
                                            <a class="avtar avtar-xs btn-link-danger text-danger location_list_remove"
                                                href="javascript:void(0)">
                                                <i data-feather="trash-2"></i>
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="row location_list">
                                    <div class="form-group col-md-3 col-lg-3">
                                        {{ Form::label('item', __('Item'), ['class' => 'form-label']) }}
                                        {!! Form::select('item[0]', $items, null, [
                                            'class' => 'form-control select2 item_name_select',
                                            'id' => 'item_id',
                                        ]) !!}
                                    </div>
                                    <div class="form-group col-md-1 col-lg-1">
                                        {{ Form::label('quantity', __('Quantity'), ['class' => 'form-label']) }}
                                        {{ Form::number('quantity[0]', null, ['class' => 'form-control quantity', 'required' => 'required']) }}
                                    </div>
                                    <div class="form-group col-md-1 col-lg-1">
                                        {{ Form::label('amount', __('Amount'), ['class' => 'form-label']) }}
                                        {{ Form::number('amount[0]', null, ['class' => 'form-control amount', 'step' => '0.01', 'required' => 'required']) }}
                                    </div>
                                    <div class="form-group col-md-2 col-lg-2">
                                        {{ Form::label('tax', __('Tax'), ['class' => 'form-label']) }}
                                        {!! Form::select(
                                            'tax[0][]',
                                            $taxs,
                                            [],
                                            [
                                                'class' => 'form-control tax_id select2',
                                                'multiple' => true,
                                            ],
                                        ) !!}
                                    </div>
                                    <div class="form-group col-md-4 col-lg-4">
                                        {{ Form::label('description', __('Description'), ['class' => 'form-label']) }}
                                        {{ Form::textarea('description[0]', null, ['class' => 'form-control description', 'rows' => 2]) }}
                                    </div>
                                    <div class="col-1 m-auto">
                                        <a class="avtar avtar-xs btn-link-danger text-danger location_list_remove"
                                            href="javascript:void(0)">
                                            <i data-feather="trash-2"></i>
                                        </a>
                                    </div>
                                </div>
                            @endif

                            <div class="location_list_results"></div>
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="d-flex justify-content-between">
                                        <a href="javascript:void(0)" class="btn btn-primary btn-xs location_clone">
                                            <i class="ti ti-plus"></i>
                                        </a>
                                        {{ Form::submit(__('Update'), ['class' => 'btn btn-secondary']) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {{ Form::close() }}
    </div>
@endsection

@push('script-page')
    <script src="{{ asset('js/jquery-ui.min.js') }}"></script>
    <script src="{{ asset('js/jquery.repeater.min.js') }}"></script>

    <script>
        var selector = "body";
        if ($(selector + " .repeater").length) {
            var $dragAndDrop = $("body .repeater tbody").sortable({
                handle: '.sort-handler'
            });
            var $repeater = $(selector + ' .repeater').repeater({
                initEmpty: false,
                defaultValues: {
                    'status': 1
                },
                show: function() {
                    $(this).slideDown(0);
                    $(this).find('.select2').select2(); // Initialize select2 only on the new row's elements
                },
                hide: function(deleteElement) {
                    if (confirm('Are you sure you want to delete this element?')) {
                        $(this).slideUp(deleteElement);
                        $(this).remove();
                    }
                },
                ready: function(setIndexes) {
                    $dragAndDrop.on('drop', setIndexes);
                },
                isFirstItemUndeletable: true
            });

        }
    </script>
    <script>
        $(document).on('change', '.service-type', function() {
            var $row = $(this).closest('tr');
            var serviceTypeId = $(this).val();

            $.ajax({
                url: "{{ route('service.type') }}",
                type: 'post',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    type_id: serviceTypeId
                },
                success: function(data) {
                    var item = JSON.parse(data);
                    $row.find('.rate').val(item.rate);
                    var taxArray = item.tax ? item.tax.split(',') : [];
                    $row.find('.tax').val(taxArray).trigger('change');
                    $row.find('.note').val(item.note);
                }
            });
        });
    </script>
    <script>
        $('#client_id').on('change', function() {
            "use strict";
            var client_id = $(this).val();
            if (client_id) {
                var url = '{{ route('client.service', ':id') }}';
                url = url.replace(':id', client_id);
                $.ajax({
                    url: url,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        client_id: client_id,
                    },
                    contentType: false,
                    processData: false,
                    type: 'GET',
                    success: function(data) {
                        $('.service').empty().append(
                            '<option value="">{{ __('Select Service') }}</option>');
                        $.each(data, function(key, value) {
                            $('.service').append('<option value="' + value['id'] + '">' + value[
                                'name'] + '</option>');
                        });
                        // Auto-select the first client's service and trigger change to populate types
                        var firstService = $('.service option').eq(1)
                            .val(); // Skip "Select Service" option
                        if (firstService) {
                            $('.service').val(firstService).trigger('change');
                        }
                        select2();
                    },
                });
            } else {
                $('.service').empty().append('<option value="">{{ __('Select Service') }}</option>');

                select2();
            }
        });
        $(document).on('change', '#service', function() {

            var service_id = $(this).val();

            if (service_id) {

                var url = '{{ route('get.service.type', ':id') }}';
                url = url.replace(':id', service_id);

                $.ajax({
                    url: url,
                    type: "GET",
                    success: function(types) {
                        if (!types || types.length === 0) {
                            return;
                        }
                        $('.repeater tbody').empty();

                        $.each(types, function(key, type) {

                            var taxArray = [];
                            if (type.tax) {
                                taxArray = type.tax.split(',').map(function(id) {
                                    return id.trim();
                                });
                            }

                            var row = `
                    <tr data-repeater-item>
                        <td width="30%">
                            <select name="types[INDEX][service_type]" class="form-control select2 service-type">
                                <option value="${type.id}" selected>${type.type}</option>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="types[INDEX][rate]" class="form-control rate" value="${type.rate}" readonly>
                        </td>
                        <td width="30%">
                            <select name="types[INDEX][tax][]" class="form-control select2 tax" multiple>
                                @foreach ($taxs as $id => $tax_name)
                                    <option value="{{ $id }}">{{ $tax_name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <textarea name="types[INDEX][note]" class="form-control note" rows="1" readonly>${type.note || ''}</textarea>
                        </td>
                        <td>
                            <a data-repeater-delete class="text-danger">
                                <i data-feather="trash-2"></i>
                            </a>
                        </td>
                    </tr>`;

                            $('.repeater tbody').append(row);

                            var currentRow = $('.repeater tbody tr:last');
                            currentRow.find('.tax').val(taxArray).trigger('change');
                        });

                        if (window.$repeater && typeof $repeater.setIndexes === 'function') {
                            $repeater.setIndexes();
                        } else if (window.$repeater && typeof $repeater.ready === 'function') {
                            $repeater.ready();
                        }
                        $('.repeater tbody tr').each(function(index) {
                            $(this).find('[name]').each(function() {
                                var name = $(this).attr('name');
                                if (name && !name.includes('[' + index + ']')) {
                                    var newName = name.replace(/\[INDEX\]/g, '[' +
                                        index + ']');
                                    $(this).attr('name', newName);
                                }
                            });
                        });

                        $('.select2').select2();
                        feather.replace();
                    }
                });

            } else {
                $('.repeater tbody').empty();
            }
        });
    </script>
    <script>
        $(document).on('change', '#item_id', function() {
            var invoiceItemId = $(this).val();
            $.ajax({
                url: "{{ route('invoice.item') }}",
                type: 'post',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    'item_id': invoiceItemId
                },
                cache: false,
                context: this,
                success: function(data) {
                    var item = JSON.parse(data);
                    $(this).parents('.location_list').find('.quantity').val(1);
                    $(this).parents('.location_list').find('.amount').val(item.item.sales_price);
                    $(this).parents('.location_list').find('.description').val(item.item.notes);
                    $(this).parents('.location_list').find('.tax_id').val(item.item.taxs.split(","));
                    select2();
                },
            });
        });
    </script>

    <script>
        $(document).ready(function() {
            $('.location_list_remove').show();
            if ($('.location_list').length == 1) {
                $('.location_list_remove').hide();
            }
        });

        $('.location').on('click', '.location_list_remove', function() {
            if ($('.location_list').length > 1) {
                $(this).parent().parent().remove();
            }
            $('.location_list_remove').show();
            if ($('.location_list').length == 1) {
                $('.location_list_remove').hide();
            }
        });

        $('.location').on('click', '.location_clone', function() {
            var clonedlocation = $('.location_clone').closest('.location').find('.location_list').first().clone();
            clonedlocation.find('input[type="number"], input[type="text"], textarea, select').val('');
            clonedlocation.find('.select2-container').remove();
            $('.location_list_results').append(clonedlocation);

            $('.location_list').each(function(index) {
                $(this).find('input, textarea, select').each(function() {
                    var name = $(this).attr('name');
                    if (name) {
                        var updatedName = name.replace(/\[\d+\]/, '[' + index + ']');
                        $(this).attr('name', updatedName);
                    }
                });
            });

            $('.location_list_remove').show();
            if ($('.location_list').length === 1) {
                $('.location_list_remove').hide();
            }
            select2();
        });
    </script>
@endpush
