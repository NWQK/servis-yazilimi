@extends('layouts.app')
@section('page-title')
    {{ __('Service') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item" aria-current="page"><a href="{{ route('service.index') }}"> {{ __('Service') }}</a></li>
    <li class="breadcrumb-item" aria-current="page"> {{ __('Create') }}</li>
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
                    $(this).find('.select2').select2();
                },
                hide: function(deleteElement) {
                    if (confirm('Bu satırı silmek istediğinizden emin misiniz?')) {
                        var el = $(this).parent().parent();
                        var id = $(el.find('.type_id')).val();
                        $.ajax({
                            url: '{{ route('service.type.destroy') }}',
                            type: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: {
                                'id': id
                            },
                            cache: false,
                            success: function(data) {
                                $(this).slideUp(deleteElement);
                                $(this).remove();
                            },
                        });
                    }
                },
                ready: function(setIndexes) {
                    $dragAndDrop.on('drop', setIndexes);
                },
                isFirstItemUndeletable: true
            });
            var value = $(selector + " .repeater").attr('data-value');
            if (typeof value != 'undefined' && value.length != 0) {
                value = JSON.parse(value);
                value = value.map((item, index) => {
                    item.service_type = item.type_id;
                    item['tax[]'] = item.tax ? item.tax.toString().split(',').map(Number) : [];
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
        }
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
                    select2(); // ← replaced manual .select2({ minimumResultsForSearch: -1 })
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
@endpush



@section('content')
    {{ Form::model($service, ['route' => ['service.update', $service->id], 'method' => 'PUT']) }}
    <div class="row mt-4">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-body">
                    <div class="info-group">
                        <div class="row">
                            <div class="form-group col-md-4 col-lg-3">
                                {{ Form::label('client', __('Client'), ['class' => 'form-label']) }}
                                {!! Form::select('client', $clients, null, [
                                    'class' => 'form-control select2',
                                    'id' => 'client_id',
                                    'required' => 'required',
                                ]) !!}

                            </div>
                            <div class="form-group col-md-4 col-lg-3">
                                <input type="hidden" id="edit_vehicle" value="{{ $service->vehicle }}">
                                {{ Form::label('vehicle_id', __('Vehicle'), ['class' => 'form-label']) }}
                                <div class="vehicle_div">
                                    <select class="form-control select2 vehicle" id="vehicle" name="vehicle">
                                        <option value="">{{ __('Select Vehicle') }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group col-md-4 col-lg-3">
                                {{ Form::label('assign', __('Assign To'), ['class' => 'form-label']) }}
                                {!! Form::select('assign', $employees, null, ['class' => 'form-control select2 ', 'required' => 'required']) !!}
                            </div>
                            <div class="form-group col-md-4 col-lg-3">
                                {{ Form::label('service_date', __('Service Start Date'), ['class' => 'form-label']) }}
                                {{ Form::date('service_date', null, ['class' => 'form-control']) }}
                            </div>

                            <div class="form-group col-md-4 col-lg-3">
                                {{ Form::label('due_date', __('Service Due Date'), ['class' => 'form-label']) }}
                                {{ Form::date('due_date', null, ['class' => 'form-control']) }}
                            </div>
                            <div class="form-group col-md-4 col-lg-3">
                                {{ Form::label('service_time', __('Service Start Time'), ['class' => 'form-label']) }}
                                {{ Form::time('service_time', null, ['class' => 'form-control']) }}
                            </div>
                            <div class="form-group col-md-4 col-lg-3">
                                {{ Form::label('due_time', __('Service Due Time'), ['class' => 'form-label']) }}
                                {{ Form::time('due_time', null, ['class' => 'form-control']) }}
                            </div>
                            <div class="form-group col-md-4 col-lg-3">
                                {{ Form::label('status', __('Status'), ['class' => 'form-label']) }}
                                {!! Form::select('status', $status, null, ['class' => 'form-control select2 ', 'required' => 'required']) !!}
                            </div>
                            @include('service.external_labor_field', ['laborAmount' => $service->external_labor_amount ?? 0])
                            <div class="form-group col-md-12 col-lg-12">
                                {{ Form::label('notes', __('Notes'), ['class' => 'form-label']) }}
                                {{ Form::textarea('notes', null, ['class' => 'form-control', 'placeholder' => __('Enter notes'), 'rows' => 2]) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- @dd($service->types) --}}
            <div class="card repeater" data-value='{!! json_encode($service->types) !!}'>
                <div class="card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">{{ __('Service Type') }}</h5>
                        <a class="btn btn-secondary d-flex align-items-center gap-2" href="#" data-repeater-create="">
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
                        <tbody data-repeater-item>
                            <tr>
                                {{ Form::hidden('id', null, ['class' => 'form-control type_id']) }}
                                <td width="30%">
                                    {!! Form::select('service_type', $types, null, [
                                        'class' => 'form-control select2 service_type',
                                    ]) !!}
                                </td>
                                <td>
                                    {{ Form::text('rate', null, ['class' => 'form-control rate', 'readonly' => true]) }}
                                </td>
                                <td width="30%">
                                    {!! Form::select('tax', $taxes, null, [
                                        'class' => 'form-control select2 tax',
                                        'multiple' => true,
                                    ]) !!}
                                </td>
                                <td>
                                    {{ Form::textarea('note', null, ['class' => 'form-control note', 'rows' => 1, 'readonly' => true]) }}
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
