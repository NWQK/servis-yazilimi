@extends('layouts.app')
@section('page-title')
    {{ __('Quotation') }}
@endsection
@push('script-page')
    <script src="{{ asset('js/jquery-ui.min.js') }}"></script>
    <script src="{{ asset('js/jquery.repeater.min.js') }}"></script>

    <script>
        $('.repeater').each(function() {
            var $container = $(this);

            var $dragAndDrop = $container.find('tbody').sortable({
                handle: '.sort-handler'
            });

            var $repeater = $container.repeater({
                initEmpty: false,
                defaultValues: {
                    'status': 1
                },
                show: function() {
                    $(this).slideDown(0);
                    // Init select2 only on uninitialized selects in this new row
                    $(this).find('.select2').each(function() {
                        if (!$(this).hasClass('select2-hidden-accessible')) {
                            $(this).select2({
                                width: '100%',
                                dropdownParent: $(this).closest('form').length ?
                                    $(this).closest('form') : $(document.body)
                            });
                        }
                    });
                },
                hide: function(deleteElement) {
                    if (confirm('Bu satırı silmek istediğinizden emin misiniz?')) {
                        var $row = $(this);
                        var id = $row.find('.type_id').val();
                        var type = $row.closest('tbody').attr('data-type');

                        if (id && id !== '' && id !== '0') {
                            $.ajax({
                                url: '{{ route('service.type.destroy') }}',
                                type: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                },
                                data: {
                                    'id': id,
                                    'type': type
                                },
                                cache: false,
                                success: function() {
                                    $row.slideUp(function() {
                                        $row.remove();
                                    });
                                },
                            });
                        } else {
                            // New row with no ID — just remove directly
                            $row.slideUp(function() {
                                $row.remove();
                            });
                        }
                    }
                },
                ready: function(setIndexes) {
                    $dragAndDrop.on('drop', setIndexes);
                },
                isFirstItemUndeletable: true
            });

            var value = $container.attr('data-value');
            if (typeof value != 'undefined' && value.length != 0) {
                value = JSON.parse(value);
                value = value.map((item, index) => {
                    if (item.item_id) item.item = item.item_id;
                    if (item.type_id) item.service_type = item.type_id;
                    item.tax = item.tax ? item.tax.toString().split(',') : [];
                    return item;
                });

                $repeater.setList(value);

                setTimeout(function() {
                    $('[data-repeater-item]').each(function(rowIndex) {
                        var $row = $(this);
                        var taxValues = value[rowIndex] ? value[rowIndex]['tax[]'] : [];

                        $row.find('.select2').select2();

                        if (taxValues && taxValues.length > 0) {
                            $row.find('.tax').val(taxValues).trigger('change');
                        }
                    });
                }, 500);
            }
        });
    </script>

    <script>
        $('#client_id').on('change', function() {
            "use strict";
            var client_id = $(this).val();
            var url = '{{ route('client.vehicle', ':id') }}';
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
                    $('.vehicle').empty();
                    var vehicle =
                        `<select class="form-control select2 vehicle" id="vehicle" name="vehicle"></select>`;
                    $('.vehicle_div').html(vehicle);

                    $.each(data, function(key, value) {
                        var vehicle_id = $('#edit_vehicle').val();
                        if (value['id'] == vehicle_id) {
                            $('.vehicle').append('<option selected value="' + value['id'] +
                                '">' + value['name'] + '</option>');
                        } else {
                            $('.vehicle').append('<option value="' + value['id'] + '">' + value[
                                'name'] + '</option>');
                        }
                    });
                    select2();
                },
            });
        });
        $('#client_id').trigger('change');
    </script>

    <script>
        $(document).on('change', '.service_type', function() {
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
        $(document).on('change', '.item', function() {
            var $row = $(this).closest('tr');
            var itemId = $(this).val();

            $.ajax({
                url: "{{ route('quotation.item') }}",
                type: 'get',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    'item_id': itemId
                },
                success: function(data) {
                    var item = JSON.parse(data);
                    $row.find('.quantity').val(1);
                    $row.find('.amount').val(item.sales_price);
                    var taxArray = item.taxs ? item.taxs.split(',') : [];
                    $row.find('.tax').val(taxArray).trigger('change');
                    $row.find('.description').val(item.notes);
                }
            });
        });
    </script>
@endpush

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item" aria-current="page"><a href="{{ route('quotation.index') }}"> {{ __('Quotation') }}</a></li>
    <li class="breadcrumb-item" aria-current="page"> {{ __('Create') }}</li>
@endsection

@section('content')
    {{ Form::model($quotation, ['route' => ['quotation.update', $quotation->id], 'method' => 'PUT']) }}

    <div class="row mt-4">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-body">
                    <div class="info-group">
                        <div class="row">
                            <div class="form-group col-md-4 col-lg-3">
                                {{ Form::label('client', __('Client'), ['class' => 'form-label']) }}
                                {!! Form::select('client_id', $clients, null, [
                                    'class' => 'form-control select2',
                                    'id' => 'client_id',
                                    'required' => 'required',
                                ]) !!}
                            </div>
                            <div class="form-group col-md-4 col-lg-3">
                                {{ Form::label('vehicle_id', __('Vehicle'), ['class' => 'form-label']) }}
                                <input type="hidden" id="edit_vehicle" value="{{ $quotation->vehicle_id }}">
                                <div class="vehicle_div">
                                    <select class="form-control select2 vehicle" id="vehicle" name="vehicle">
                                        <option value="">{{ __('Select Vehicle') }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group col-md-4 col-lg-3">
                                {{ Form::label('quotation_date', __('Quotation Date'), ['class' => 'form-label']) }}
                                {{ Form::date('quotation_date', null, ['class' => 'form-control', 'required' => 'required']) }}
                            </div>

                            <div class="form-group col-md-4 col-lg-3">
                                {{ Form::label('status', __('Status'), ['class' => 'form-label']) }}
                                {!! Form::select('status', $status, null, ['class' => 'form-control select2', 'required' => 'required']) !!}
                            </div>
                            <div class="form-group col-md-12 col-lg-12">
                                {{ Form::label('notes', __('Notes'), ['class' => 'form-label']) }}
                                {{ Form::textarea('notes', null, ['class' => 'form-control', 'placeholder' => __('Enter notes'), 'rows' => 2]) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card repeater" data-value='{!! json_encode($quotation->types) !!}'>
                <div class="card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">{{ __('Service Type') }}</h5>
                        <a class="btn btn-secondary d-flex align-items-center gap-2" href="#!" data-repeater-create="">
                            <i class="ti ti-circle-plus align-text-bottom"></i>{{ __('Add Type') }}</a>
                    </div>
                </div>
                <div class="card-body">
                    <table class="display dataTable cell-border" data-repeater-list="types">
                        <thead>
                            <tr>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Rate') }}</th>
                                <th>{{ __('Tax') }}</th>
                                <th>{{ __('Note') }}</th>
                                <th>#</th>
                            </tr>
                        </thead>
                        <tbody data-repeater-item data-type="qtype">
                            <tr>
                                {{ Form::hidden('id', null, ['class' => 'form-control type_id']) }}

                                <td width="30%">
                                    {!! Form::select('service_type', $types, null, [
                                        'class' => 'form-control select2 service_type',
                                    ]) !!}
                                </td>

                                <td>
                                    {{ Form::text('rate', null, ['class' => 'form-control rate', 'readonly']) }}
                                </td>

                                <td width="25%">
                                    {!! Form::select('tax', $taxes, null, [
                                        'class' => 'form-control select2 tax',
                                        'multiple',
                                    ]) !!}
                                </td>

                                <td>
                                    {{ Form::textarea('note', null, ['class' => 'form-control note', 'rows' => 1, 'readonly']) }}
                                </td>

                                <td>
                                    <a class="text-danger" data-repeater-delete href="#">
                                        <i data-feather="trash-2"></i>
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card repeater" data-value='{!! json_encode($quotation->items) !!}'>
                <div class="card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">{{ __('Item') }}</h5>
                        <a class="btn btn-secondary d-flex align-items-center gap-2" href="#!" data-repeater-create="">
                            <i class="ti ti-circle-plus align-text-bottom"></i>{{ __('Add Item') }}</a>
                    </div>
                </div>
                <div class="card-body">
                    <table class="display dataTable cell-border" data-repeater-list="items">
                        <thead>
                            <tr>
                                <th>{{ __('Item') }}</th>
                                <th>{{ __('Quantity') }}</th>
                                <th>{{ __('Amount') }}</th>
                                <th>{{ __('Tax') }}</th>
                                <th>{{ __('Note') }}</th>
                                <th>#</th>
                            </tr>
                        </thead>
                        <tbody data-repeater-item data-type="qitem">
                            <tr>
                                {{ Form::hidden('id', null, ['class' => 'form-control type_id']) }}
                                <td width="30%">
                                    {!! Form::select('item', $invoiceItems, null, [
                                        'class' => 'form-control select2 item',
                                    ]) !!}
                                </td>
                                <td>
                                    {{ Form::number('quantity', null, ['class' => 'form-control quantity']) }}
                                </td>
                                <td>
                                    {{ Form::number('amount', null, ['class' => 'form-control amount']) }}
                                </td>
                                <td width="25%">
                                    {!! Form::select('tax', $taxes, null, [
                                        'class' => 'form-control select2 tax',
                                        'multiple',
                                    ]) !!}
                                </td>
                                <td>
                                    {{ Form::textarea('description', null, ['class' => 'form-control description', 'rows' => 1]) }}
                                </td>
                                <td>
                                    <a class="text-danger" data-repeater-delete data-bs-toggle="tooltip"
                                        data-bs-original-title="{{ __('Detete') }}" href="#"> <i
                                            data-feather="trash-2"></i></a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="group-button text-end">
                {{ Form::submit(__('Update'), ['class' => 'btn btn-secondary btn-rounded', 'id' => 'invoice-submit']) }}
            </div>
        </div>
    </div>
    {{ Form::close() }}
@endsection
