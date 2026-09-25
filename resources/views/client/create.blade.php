@extends('layouts.app')
@section('page-title')
    {{ __('Create') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
    </li>
    <li class="breadcrumb-item" aria-current="page">
        {{ __('Create') }}
    </li>
@endsection
@push('css-page')
    <style>
        #createwizard .wizard-nav .nav-link {
            transition: all .3s ease;
            border-radius: 8px;
            padding: 10px 16px;
            font-weight: 500;
        }

        #createwizard .wizard-nav .nav-link.active {
            background: var(--bs-secondary) !important;
            color: #fff !important;
            border-color: var(--bs-secondary) !important;
        }

        #createwizard .wizard-nav .nav-link.done {
            background: var(--bs-success) !important;
            color: #fff !important;
        }

        #createwizard #theme-progress-bar {
            background: var(--bs-secondary) !important;
            transition: width .3s ease, background-color .3s ease;
        }

        .wizard-nav .nav-link i {
            font-size: 18px;
            vertical-align: middle;
        }

        .wizard-nav .nav-link span {
            vertical-align: middle;
        }
    </style>
@endpush
@section('content')
    <div class="row">
        <div class="col-12">
            {{ Form::open(['route' => 'client.store', 'method' => 'post', 'id' => 'masterForm']) }}
            <div id="createwizard" class="form-wizard row justify-content-center">
                <div class="col-12">
                    <div class="card shadow-sm mb-3">
                        <div class="card-body p-3">
                            <ul class="nav nav-pills nav-justified wizard-nav gap-2">
                                <li class="nav-item" data-target-form="#personalPane">
                                    <a href="#personalPane" data-bs-toggle="tab" class="nav-link active text-center">
                                        <i class="ph-duotone ph-user-circle"></i>
                                        <span class="d-none d-sm-inline ms-1">{{ __('Client Details') }}</span>
                                    </a>
                                </li>
                                <li class="nav-item" data-target-form="#VehiclePane">
                                    <a href="#VehiclePane" data-bs-toggle="tab" class="nav-link text-center">
                                        <i class="ph-duotone ph-car"></i>
                                        <span class="d-none d-sm-inline ms-1">{{ __('Vehicle Details') }}</span>
                                    </a>
                                </li>
                                <li class="nav-item" data-target-form="#attendancePane">
                                    <a href="#attendancePane" data-bs-toggle="tab" class="nav-link text-center">
                                        <i class="ph-duotone ph-wrench"></i>
                                        <span class="d-none d-sm-inline ms-1">{{ __('Service Details') }}</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="tab-content">
                                <div class="progress mb-4" style="height:6px;border-radius:3px;">
                                    <div class="bar progress-bar progress-bar-striped progress-bar-animated"
                                        id="theme-progress-bar" style="background-color:var(--bs-secondary) !important;">
                                    </div>
                                </div>
                                <div class="tab-pane show active" id="personalPane">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            {{ Form::label('name', __('Name'), ['class' => 'form-label fw-semibold']) }}
                                            {{ Form::text('name', old('name'), ['class' => 'form-control', 'placeholder' => __('Enter Name'), 'required' => true]) }}
                                        </div>
                                        <div class="col-md-6">
                                            {{ Form::label('email', __('Email'), ['class' => 'form-label fw-semibold']) }}
                                            {{ Form::email('email', old('email'), ['class' => 'form-control', 'placeholder' => __('Enter Email')]) }}
                                        </div>
                                        <div class="col-md-6">
                                            {{ Form::label('phone_number', __('Phone Number'), ['class' => 'form-label fw-semibold']) }}
                                            {{ Form::text('phone_number', old('phone_number'), ['class' => 'form-control', 'placeholder' => __('e.g. +91XXXXXXXXXX'), 'required' => true]) }}
                                        </div>
                                        <div class="col-md-6">
                                            {{ Form::label('state', __('State'), ['class' => 'form-label fw-semibold']) }}
                                            {{ Form::text('state', old('state'), ['class' => 'form-control', 'placeholder' => __('Enter state')]) }}
                                        </div>
                                        <div class="col-md-6">
                                            {{ Form::label('city', __('City'), ['class' => 'form-label fw-semibold']) }}
                                            {{ Form::text('city', old('city'), ['class' => 'form-control', 'placeholder' => __('Enter city')]) }}
                                        </div>
                                        <div class="col-md-6">
                                            {{ Form::label('zip_code', __('Zip Code'), ['class' => 'form-label fw-semibold']) }}
                                            {{ Form::text('zip_code', old('zip_code'), ['class' => 'form-control', 'placeholder' => __('Enter zip code')]) }}
                                        </div>
                                        <div class="col-md-6">
                                            {{ Form::label('address', __('Address'), ['class' => 'form-label fw-semibold']) }}
                                            {{ Form::textarea('address', old('address'), ['class' => 'form-control', 'placeholder' => __('Enter address'), 'rows' => 2]) }}
                                        </div>
                                        <div class="col-md-6">
                                            {{ Form::label('notes', __('Notes'), ['class' => 'form-label fw-semibold']) }}
                                            {{ Form::textarea('notes', old('notes'), ['class' => 'form-control', 'placeholder' => __('Enter notes'), 'rows' => 2]) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane" id="VehiclePane">
                                    @include('vehicle_qr.select')
                                    @php $subscriptionData = currentSubscription(); @endphp
                                    @if (settings()['openai_module'] == 'on' &&
                                            (Auth::user()->type !== 'super admin' ||
                                                ($subscriptionData['pricing_feature_settings'] === 'off' ||
                                                    $subscriptionData['subscription']->enabled_openai == 1)))
                                        <div class="mb-3 text-end">
                                            <a href="javascript:void(0)" class="btn btn-primary btn-sm aiModal"
                                                data-size="lg" data-url="{{ route('generate.template', ['vehicle']) }}"
                                                data-title="{{ __('AI Content Generator') }}">
                                                <i class="ti ti-robot me-1"></i> {{ __('AI Content Generator') }}
                                            </a>
                                        </div>
                                    @endif
                                    <div class="row g-3">
                                        <div class="form-group col-md-6">
                                            {{ Form::label('type', __('Brand'), ['class' => 'form-label']) }}
                                            {!! Form::select('type', $types, null, [
                                                'class' => 'form-control select2 ',
                                                'id' => 'type_id',
                                                'required' => 'required',
                                            ]) !!}
                                        </div>
                                        <div class="form-group col-md-6 col-lg-6">
                                            {{ Form::label('brand_id', __('Model'), ['class' => 'form-label']) }}
                                            <div class="brand_div">
                                                <select class="form-control select2 brand" id="brand" name="brand">
                                                    <option value="">{{ __('Select Model') }}</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group col-md-6">
                                            {{ Form::label('color', __('Color'), ['class' => 'form-label']) }}
                                            {{ Form::text('color', null, ['class' => 'form-control', 'placeholder' => __('Enter color')]) }}
                                        </div>
                                        <div class="form-group col-md-6">
                                            {{ Form::label('license_plate', __('License Plate'), ['class' => 'form-label']) }}
                                            {{ Form::text('license_plate', null, ['class' => 'form-control', 'placeholder' => __('Enter license plate'), 'required' => 'required']) }}
                                        </div>
                                        <div class="form-group col-md-6">
                                            {{ Form::label('engine_type', __('Engine Type'), ['class' => 'form-label']) }}
                                            {{ Form::text('engine_type', null, ['class' => 'form-control', 'placeholder' => __('Enter engine type')]) }}
                                        </div>
                                        <div class="form-group col-md-6">
                                            {{ Form::label('engine_no', __('Engine Number'), ['class' => 'form-label']) }}
                                            {{ Form::text('engine_no', null, ['class' => 'form-control', 'placeholder' => __('Enter engine number')]) }}
                                        </div>
                                        <div class="form-group col-md-6">
                                            {{ Form::label('fuel_type', __('Fuel Type'), ['class' => 'form-label']) }}
                                            {{ Form::text('fuel_type', null, ['class' => 'form-control', 'placeholder' => __('Enter fuel type')]) }}
                                        </div>
                                        <div class="form-group col-md-6">
                                            {{ Form::label('chassis_no', __('Chassis Number'), ['class' => 'form-label']) }}
                                            {{ Form::text('chassis_no', null, ['class' => 'form-control', 'placeholder' => __('Enter chassis number')]) }}
                                        </div>
                                        <div class="form-group col-md-6">
                                            {{ Form::label('mileage', __('Mileage'), ['class' => 'form-label']) }}
                                            {{ Form::number('mileage', null, ['class' => 'form-control', 'placeholder' => __('Enter mileage')]) }}
                                        </div>
                                        <div class="form-group col-md-6">
                                            {{ Form::label('last_service_date', __('Last Service Date'), ['class' => 'form-label']) }}
                                            {{ Form::date('last_service_date', null, ['class' => 'form-control']) }}
                                        </div>
                                        <div class="form-group col-md-6">
                                            {{ Form::label('next_service_due_date', __('Next Service Due Date'), ['class' => 'form-label']) }}
                                            {{ Form::date('next_service_due_date', null, ['class' => 'form-control']) }}
                                        </div>
                                        <div class="form-group col-md-6">
                                            {{ Form::label('insurance_details', __('Insurance Details'), ['class' => 'form-label']) }}
                                            {{ Form::textarea('insurance_details', null, ['class' => 'form-control', 'placeholder' => __('Enter insurance details'), 'rows' => 2]) }}
                                        </div>
                                        <div class="form-group col-md-6">
                                            {{ Form::label('vehicle_notes', __('Notes'), ['class' => 'form-label']) }}
                                            {{ Form::textarea('vehicle_notes', null, ['class' => 'form-control', 'placeholder' => __('Enter notes'), 'rows' => 2]) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane" id="attendancePane">
                                    <div class="card border mb-3">
                                        <div
                                            class="card-header bg-light py-2 px-3 d-flex align-items-center justify-content-between">
                                            <h6 class="mb-0 fw-semibold">{{ __('Service Information') }}</h6>
                                            @if (settings()['openai_module'] == 'on' &&
                                                    (Auth::user()->type !== 'super admin' ||
                                                        ($subscriptionData['pricing_feature_settings'] === 'off' ||
                                                            $subscriptionData['subscription']->enabled_openai == 1)))
                                                <a href="javascript:void(0)" class="btn btn-primary btn-sm aiModal"
                                                    data-size="lg" data-url="{{ route('generate.template', ['service']) }}"
                                                    data-title="{{ __('AI Content Generator') }}">
                                                    <i class="ti ti-robot me-1"></i> {{ __('AI Content Generator') }}
                                                </a>
                                            @endif
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3">
                                                <div class="form-group col-md-4 col-lg-3">
                                                    {{ Form::label('assign', __('Assign To Employee'), ['class' => 'form-label']) }}
                                                    {!! Form::select('assign', $employees, null, ['class' => 'form-control select2', 'required' => 'required']) !!}
                                                </div>
                                                <div class="form-group col-md-4 col-lg-3">
                                                    {{ Form::label('service_date', __('Service Start Date'), ['class' => 'form-label']) }}
                                                    {{ Form::date('service_date', null, ['class' => 'form-control', 'required' => 'required']) }}
                                                </div>

                                                <div class="form-group col-md-4 col-lg-3">
                                                    {{ Form::label('due_date', __('Service Due Date'), ['class' => 'form-label']) }}
                                                    {{ Form::date('due_date', null, ['class' => 'form-control', 'required' => 'required']) }}
                                                </div>
                                                <div class="form-group col-md-4 col-lg-3">
                                                    {{ Form::label('service_time', __('Service Start Time'), ['class' => 'form-label']) }}
                                                    {{ Form::time('service_time', null, ['class' => 'form-control', 'required' => 'required']) }}
                                                </div>
                                                <div class="form-group col-md-4 col-lg-3">
                                                    {{ Form::label('due_time', __('Service Due Time'), ['class' => 'form-label']) }}
                                                    {{ Form::time('due_time', null, ['class' => 'form-control', 'required' => 'required']) }}
                                                </div>
                                                <div class="form-group col-md-4 col-lg-3">
                                                    {{ Form::label('status', __('Status'), ['class' => 'form-label']) }}
                                                    {!! Form::select('status', $status, null, ['class' => 'form-control select2', 'required' => 'required']) !!}
                                                </div>
                                                @include('service.external_labor_field', [])
                                                <div class="form-group col-md-12 col-lg-12">
                                                    {{ Form::label('service_notes', __('Notes'), ['class' => 'form-label']) }}
                                                    {{ Form::textarea('service_notes', null, ['class' => 'form-control', 'placeholder' => __('Enter notes'), 'rows' => 2]) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card repeater">
                                        <div class="card-header">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <h5 class="mb-0">{{ __('Service Type') }}</h5>
                                                <a class="btn btn-secondary d-flex align-items-center gap-2"
                                                    href="#" data-repeater-create="">
                                                    <i
                                                        class="ti ti-circle-plus align-text-bottom"></i>{{ __('Add Type') }}</a>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <table class="display dataTable cell-border" data-repeater-list="types">
                                                <thead>
                                                    <tr>
                                                        <th>{{ __('Service Type') }}</th>
                                                        <th>{{ __('Rate') }}</th>
                                                        <th>{{ __('Tax') }}</th>
                                                        <th>{{ __('Note') }}</th>
                                                        <th>#</th>
                                                    </tr>
                                                </thead>
                                                <tbody data-repeater-item>
                                                    <tr>
                                                        <td width="30%">
                                                            {!! Form::select('service_type', $serviceTypes, null, [
                                                                'class' => 'form-control select2 service-type',
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
                                                            <a class="text-danger" data-repeater-delete
                                                                data-bs-toggle="tooltip"
                                                                data-bs-original-title="{{ __('Detete') }}"
                                                                href="#"> <i data-feather="trash-2"></i></a>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex wizard justify-content-between flex-wrap gap-2 mt-3">
                                    <div class="first">
                                        <div class="first">
                                            <a href="javascript:void(0);"
                                                class="btn btn-secondary">{{ __('First') }}</a>
                                        </div>
                                    </div>
                                    <div class="d-flex">
                                        <div class="previous me-2">
                                            <a href="javascript:void(0);"
                                                class="btn btn-secondary">{{ __('Back To Previous') }}</a>
                                        </div>
                                        <div class="next">
                                            <a href="javascript:void(0);"
                                                class="btn btn-secondary">{{ __('Next Step') }}</a>
                                        </div>
                                    </div>
                                    <div class="last">
                                        <button type="submit" form="masterForm" class="btn btn-secondary">
                                            {{ __('Finish & Create') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {{ Form::close() }}
        </div>
    </div>
@endsection
@push('script-page')
    <script src="{{ asset('assets/js/plugins/wizard.min.js') }}"></script>
    <script src="{{ asset('js/jquery-ui.min.js') }}"></script>
    <script src="{{ asset('js/jquery.repeater.min.js') }}"></script>
    <script>
        new Wizard('#createwizard', {
            validate: true,
            progress: true
        });

        $(document).ready(function() {
            $('#type_id').on('change', function() {
                var typeId = $(this).val();

                if (!typeId) {
                    $('#brand').empty().append('<option value="">Model seçin</option>').trigger('change');
                    return;
                }

                var url = "{{ route('vehicle.brand', ':id') }}";
                url = url.replace(':id', typeId);

                $.ajax({
                    url: url,
                    type: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        console.log("Received brands:", data);
                        var brandSelect = $('#brand');
                        brandSelect.empty();
                        brandSelect.append('<option value="">Model seçin</option>');
                        $.each(data, function(key, value) {
                            brandSelect.append('<option value="' + key + '">' + value +
                                '</option>');
                        });
                        brandSelect.trigger('change');
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX error:", error);
                    }
                });
            });
        });

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
                    $(this).slideDown();
                    select2();
                },
                hide: function(deleteElement) {
                    if (confirm('Bu satırı silmek istediğinizden emin misiniz?')) {
                        $(this).slideUp(deleteElement);
                        $(this).remove();
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
                $repeater.setList(value);
            }
        }

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
                        $('.vehicle').append('<option value="' + value['id'] + '">' + value[
                            'name'] + '</option>');
                    });
                    select2();
                },
            });
        });

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
@endpush
