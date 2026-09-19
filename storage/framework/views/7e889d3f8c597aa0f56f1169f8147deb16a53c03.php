<?php $__env->startSection('page-title'); ?>
    <?php echo e(__('Create')); ?>

<?php $__env->stopSection(); ?>
<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item">
        <a href="<?php echo e(route('dashboard')); ?>"><?php echo e(__('Dashboard')); ?></a>
    </li>
    <li class="breadcrumb-item" aria-current="page">
        <?php echo e(__('Create')); ?>

    </li>
<?php $__env->stopSection(); ?>
<?php $__env->startPush('css-page'); ?>
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
<?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
    <div class="row">
        <div class="col-12">
            <?php echo e(Form::open(['route' => 'client.store', 'method' => 'post', 'id' => 'masterForm'])); ?>

            <div id="createwizard" class="form-wizard row justify-content-center">
                <div class="col-12">
                    <div class="card shadow-sm mb-3">
                        <div class="card-body p-3">
                            <ul class="nav nav-pills nav-justified wizard-nav gap-2">
                                <li class="nav-item" data-target-form="#personalPane">
                                    <a href="#personalPane" data-bs-toggle="tab" class="nav-link active text-center">
                                        <i class="ph-duotone ph-user-circle"></i>
                                        <span class="d-none d-sm-inline ms-1"><?php echo e(__('Client Details')); ?></span>
                                    </a>
                                </li>
                                <li class="nav-item" data-target-form="#VehiclePane">
                                    <a href="#VehiclePane" data-bs-toggle="tab" class="nav-link text-center">
                                        <i class="ph-duotone ph-car"></i>
                                        <span class="d-none d-sm-inline ms-1"><?php echo e(__('Vehicle Details')); ?></span>
                                    </a>
                                </li>
                                <li class="nav-item" data-target-form="#attendancePane">
                                    <a href="#attendancePane" data-bs-toggle="tab" class="nav-link text-center">
                                        <i class="ph-duotone ph-wrench"></i>
                                        <span class="d-none d-sm-inline ms-1"><?php echo e(__('Service Details')); ?></span>
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
                                            <?php echo e(Form::label('name', __('Name'), ['class' => 'form-label fw-semibold'])); ?>

                                            <?php echo e(Form::text('name', old('name'), ['class' => 'form-control', 'placeholder' => __('Enter Name'), 'required' => true])); ?>

                                        </div>
                                        <div class="col-md-6">
                                            <?php echo e(Form::label('email', __('Email'), ['class' => 'form-label fw-semibold'])); ?>

                                            <?php echo e(Form::text('email', old('email'), ['class' => 'form-control', 'placeholder' => __('Enter Email')])); ?>

                                        </div>
                                        <div class="col-md-6">
                                            <?php echo e(Form::label('phone_number', __('Phone Number'), ['class' => 'form-label fw-semibold'])); ?>

                                            <?php echo e(Form::text('phone_number', old('phone_number'), ['class' => 'form-control', 'placeholder' => __('e.g. +91XXXXXXXXXX'), 'required' => true])); ?>

                                        </div>
                                        <div class="col-md-6">
                                            <?php echo e(Form::label('password', __('Password'), ['class' => 'form-label fw-semibold'])); ?>

                                            <?php echo e(Form::password('password', ['class' => 'form-control', 'placeholder' => __('Min 6 characters')])); ?>

                                        </div>
                                        <div class="col-md-6">
                                            <?php echo e(Form::label('gender', __('Gender'), ['class' => 'form-label fw-semibold'])); ?>

                                            <?php echo Form::select('gender', $gender, old('gender'), ['class' => 'form-control select2']); ?>

                                        </div>
                                        <div class="col-md-6">
                                            <?php echo e(Form::label('country', __('Country'), ['class' => 'form-label fw-semibold'])); ?>

                                            <?php echo e(Form::text('country', old('country'), ['class' => 'form-control', 'placeholder' => __('Enter country')])); ?>

                                        </div>
                                        <div class="col-md-6">
                                            <?php echo e(Form::label('state', __('State'), ['class' => 'form-label fw-semibold'])); ?>

                                            <?php echo e(Form::text('state', old('state'), ['class' => 'form-control', 'placeholder' => __('Enter state')])); ?>

                                        </div>
                                        <div class="col-md-6">
                                            <?php echo e(Form::label('city', __('City'), ['class' => 'form-label fw-semibold'])); ?>

                                            <?php echo e(Form::text('city', old('city'), ['class' => 'form-control', 'placeholder' => __('Enter city')])); ?>

                                        </div>
                                        <div class="col-md-6">
                                            <?php echo e(Form::label('zip_code', __('Zip Code'), ['class' => 'form-label fw-semibold'])); ?>

                                            <?php echo e(Form::text('zip_code', old('zip_code'), ['class' => 'form-control', 'placeholder' => __('Enter zip code')])); ?>

                                        </div>
                                        <div class="col-md-6">
                                            <?php echo e(Form::label('address', __('Address'), ['class' => 'form-label fw-semibold'])); ?>

                                            <?php echo e(Form::textarea('address', old('address'), ['class' => 'form-control', 'placeholder' => __('Enter address'), 'rows' => 2])); ?>

                                        </div>
                                        <div class="col-md-6">
                                            <?php echo e(Form::label('notes', __('Notes'), ['class' => 'form-label fw-semibold'])); ?>

                                            <?php echo e(Form::textarea('notes', old('notes'), ['class' => 'form-control', 'placeholder' => __('Enter notes'), 'rows' => 2])); ?>

                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane" id="VehiclePane">
                                    <?php $subscriptionData = currentSubscription(); ?>
                                    <?php if(settings()['openai_module'] == 'on' &&
                                            (Auth::user()->type !== 'super admin' ||
                                                ($subscriptionData['pricing_feature_settings'] === 'off' ||
                                                    $subscriptionData['subscription']->enabled_openai == 1))): ?>
                                        <div class="mb-3 text-end">
                                            <a href="javascript:void(0)" class="btn btn-primary btn-sm aiModal"
                                                data-size="lg" data-url="<?php echo e(route('generate.template', ['vehicle'])); ?>"
                                                data-title="<?php echo e(__('AI Content Generator')); ?>">
                                                <i class="ti ti-robot me-1"></i> <?php echo e(__('AI Content Generator')); ?>

                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <div class="row g-3">
                                        <div class="form-group col-md-6">
                                            <?php echo e(Form::label('type', __('Type'), ['class' => 'form-label'])); ?>

                                            <?php echo Form::select('type', $types, null, [
                                                'class' => 'form-control select2 ',
                                                'id' => 'type_id',
                                                'required' => 'required',
                                            ]); ?>

                                        </div>
                                        <div class="form-group col-md-6 col-lg-6">
                                            <?php echo e(Form::label('brand_id', __('Brand'), ['class' => 'form-label'])); ?>

                                            <div class="brand_div">
                                                <select class="form-control select2 brand" id="brand" name="brand">
                                                    <option value=""><?php echo e(__('Select Brand')); ?></option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <?php echo e(Form::label('model', __('Model'), ['class' => 'form-label'])); ?>

                                            <?php echo e(Form::text('model', null, ['class' => 'form-control', 'placeholder' => __('Enter model'), 'required' => 'required'])); ?>

                                        </div>
                                        <div class="form-group col-md-6">
                                            <?php echo e(Form::label('color', __('Color'), ['class' => 'form-label'])); ?>

                                            <?php echo e(Form::text('color', null, ['class' => 'form-control', 'placeholder' => __('Enter color'), 'required' => 'required'])); ?>

                                        </div>
                                        <div class="form-group col-md-6">
                                            <?php echo e(Form::label('license_plate', __('License Plate'), ['class' => 'form-label'])); ?>

                                            <?php echo e(Form::text('license_plate', null, ['class' => 'form-control', 'placeholder' => __('Enter license plate'), 'required' => 'required'])); ?>

                                        </div>
                                        <div class="form-group col-md-6">
                                            <?php echo e(Form::label('engine_type', __('Engine Type'), ['class' => 'form-label'])); ?>

                                            <?php echo e(Form::text('engine_type', null, ['class' => 'form-control', 'placeholder' => __('Enter engine type'), 'required' => 'required'])); ?>

                                        </div>
                                        <div class="form-group col-md-6">
                                            <?php echo e(Form::label('engine_no', __('Engine Number'), ['class' => 'form-label'])); ?>

                                            <?php echo e(Form::text('engine_no', null, ['class' => 'form-control', 'placeholder' => __('Enter engine number'), 'required' => 'required'])); ?>

                                        </div>
                                        <div class="form-group col-md-6">
                                            <?php echo e(Form::label('fuel_type', __('Fuel Type'), ['class' => 'form-label'])); ?>

                                            <?php echo e(Form::text('fuel_type', null, ['class' => 'form-control', 'placeholder' => __('Enter fuel type'), 'required' => 'required'])); ?>

                                        </div>
                                        <div class="form-group col-md-6">
                                            <?php echo e(Form::label('chassis_no', __('Chassis Number'), ['class' => 'form-label'])); ?>

                                            <?php echo e(Form::text('chassis_no', null, ['class' => 'form-control', 'placeholder' => __('Enter chassis number'), 'required' => 'required'])); ?>

                                        </div>
                                        <div class="form-group col-md-6">
                                            <?php echo e(Form::label('mileage', __('Mileage'), ['class' => 'form-label'])); ?>

                                            <?php echo e(Form::number('mileage', null, ['class' => 'form-control', 'placeholder' => __('Enter mileage'), 'required' => 'required'])); ?>

                                        </div>
                                        <div class="form-group col-md-6">
                                            <?php echo e(Form::label('last_service_date', __('Last Service Date'), ['class' => 'form-label'])); ?>

                                            <?php echo e(Form::date('last_service_date', null, ['class' => 'form-control', 'required' => 'required'])); ?>

                                        </div>
                                        <div class="form-group col-md-6">
                                            <?php echo e(Form::label('next_service_due_date', __('Next Service Due Date'), ['class' => 'form-label'])); ?>

                                            <?php echo e(Form::date('next_service_due_date', null, ['class' => 'form-control', 'required' => 'required'])); ?>

                                        </div>
                                        <div class="form-group col-md-6">
                                            <?php echo e(Form::label('insurance_details', __('Insurance Details'), ['class' => 'form-label'])); ?>

                                            <?php echo e(Form::textarea('insurance_details', null, ['class' => 'form-control', 'placeholder' => __('Enter insurance details'), 'rows' => 2])); ?>

                                        </div>
                                        <div class="form-group col-md-6">
                                            <?php echo e(Form::label('notes', __('Notes'), ['class' => 'form-label'])); ?>

                                            <?php echo e(Form::textarea('notes', null, ['class' => 'form-control', 'placeholder' => __('Enter notes'), 'rows' => 2, 'required' => 'required'])); ?>

                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane" id="attendancePane">
                                    <div class="card border mb-3">
                                        <div
                                            class="card-header bg-light py-2 px-3 d-flex align-items-center justify-content-between">
                                            <h6 class="mb-0 fw-semibold"><?php echo e(__('Service Information')); ?></h6>
                                            <?php if(settings()['openai_module'] == 'on' &&
                                                    (Auth::user()->type !== 'super admin' ||
                                                        ($subscriptionData['pricing_feature_settings'] === 'off' ||
                                                            $subscriptionData['subscription']->enabled_openai == 1))): ?>
                                                <a href="javascript:void(0)" class="btn btn-primary btn-sm aiModal"
                                                    data-size="lg" data-url="<?php echo e(route('generate.template', ['service'])); ?>"
                                                    data-title="<?php echo e(__('AI Content Generator')); ?>">
                                                    <i class="ti ti-robot me-1"></i> <?php echo e(__('AI Content Generator')); ?>

                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3">
                                                <div class="form-group col-md-4 col-lg-3">
                                                    <?php echo e(Form::label('assign', __('Assign To Employee'), ['class' => 'form-label'])); ?>

                                                    <?php echo Form::select('assign', $employees, null, ['class' => 'form-control select2', 'required' => 'required']); ?>

                                                </div>
                                                <div class="form-group col-md-4 col-lg-3">
                                                    <?php echo e(Form::label('service_date', __('Service Start Date'), ['class' => 'form-label'])); ?>

                                                    <?php echo e(Form::date('service_date', null, ['class' => 'form-control', 'required' => 'required'])); ?>

                                                </div>

                                                <div class="form-group col-md-4 col-lg-3">
                                                    <?php echo e(Form::label('due_date', __('Service Due Date'), ['class' => 'form-label'])); ?>

                                                    <?php echo e(Form::date('due_date', null, ['class' => 'form-control', 'required' => 'required'])); ?>

                                                </div>
                                                <div class="form-group col-md-4 col-lg-3">
                                                    <?php echo e(Form::label('service_time', __('Service Start Time'), ['class' => 'form-label'])); ?>

                                                    <?php echo e(Form::time('service_time', null, ['class' => 'form-control', 'required' => 'required'])); ?>

                                                </div>
                                                <div class="form-group col-md-4 col-lg-3">
                                                    <?php echo e(Form::label('due_time', __('Service Due Time'), ['class' => 'form-label'])); ?>

                                                    <?php echo e(Form::time('due_time', null, ['class' => 'form-control', 'required' => 'required'])); ?>

                                                </div>
                                                <div class="form-group col-md-4 col-lg-3">
                                                    <?php echo e(Form::label('status', __('Status'), ['class' => 'form-label'])); ?>

                                                    <?php echo Form::select('status', $status, null, ['class' => 'form-control select2', 'required' => 'required']); ?>

                                                </div>
                                                <div class="form-group col-md-12 col-lg-12">
                                                    <?php echo e(Form::label('notes', __('Notes'), ['class' => 'form-label'])); ?>

                                                    <?php echo e(Form::textarea('notes', null, ['class' => 'form-control', 'placeholder' => __('Enter notes'), 'rows' => 2])); ?>

                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card repeater">
                                        <div class="card-header">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <h5 class="mb-0"><?php echo e(__('Service Type')); ?></h5>
                                                <a class="btn btn-secondary d-flex align-items-center gap-2"
                                                    href="#" data-repeater-create="">
                                                    <i
                                                        class="ti ti-circle-plus align-text-bottom"></i><?php echo e(__('Add Type')); ?></a>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <table class="display dataTable cell-border" data-repeater-list="types">
                                                <thead>
                                                    <tr>
                                                        <th><?php echo e(__('Type')); ?></th>
                                                        <th><?php echo e(__('Rate')); ?></th>
                                                        <th><?php echo e(__('Tax')); ?></th>
                                                        <th><?php echo e(__('Note')); ?></th>
                                                        <th>#</th>
                                                    </tr>
                                                </thead>
                                                <tbody data-repeater-item>
                                                    <tr>
                                                        <td width="30%">
                                                            <?php echo Form::select('service_type', $types, null, [
                                                                'class' => 'form-control select2 service-type',
                                                            ]); ?>

                                                        </td>
                                                        <td>
                                                            <?php echo e(Form::text('rate', null, ['class' => 'form-control rate', 'readonly' => true])); ?>

                                                        </td>
                                                        <td width="30%">
                                                            <?php echo Form::select('tax', $taxes, null, [
                                                                'class' => 'form-control select2 tax',
                                                                'multiple' => true,
                                                            ]); ?>

                                                        </td>
                                                        <td>
                                                            <?php echo e(Form::textarea('note', null, ['class' => 'form-control note', 'rows' => 1, 'readonly' => true])); ?>

                                                        </td>
                                                        <td>
                                                            <a class="text-danger" data-repeater-delete
                                                                data-bs-toggle="tooltip"
                                                                data-bs-original-title="<?php echo e(__('Detete')); ?>"
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
                                                class="btn btn-secondary"><?php echo e(__('First')); ?></a>
                                        </div>
                                    </div>
                                    <div class="d-flex">
                                        <div class="previous me-2">
                                            <a href="javascript:void(0);"
                                                class="btn btn-secondary"><?php echo e(__('Back To Previous')); ?></a>
                                        </div>
                                        <div class="next">
                                            <a href="javascript:void(0);"
                                                class="btn btn-secondary"><?php echo e(__('Next Step')); ?></a>
                                        </div>
                                    </div>
                                    <div class="last">
                                        <button type="submit" form="masterForm" class="btn btn-secondary">
                                            <?php echo e(__('Finish & Create')); ?>

                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php echo e(Form::close()); ?>

        </div>
    </div>
<?php $__env->stopSection(); ?>
<?php $__env->startPush('script-page'); ?>
    <script src="<?php echo e(asset('assets/js/plugins/wizard.min.js')); ?>"></script>
    <script src="<?php echo e(asset('js/jquery-ui.min.js')); ?>"></script>
    <script src="<?php echo e(asset('js/jquery.repeater.min.js')); ?>"></script>
    <script>
        new Wizard('#createwizard', {
            validate: true,
            progress: true
        });

        $(document).ready(function() {
            $('#type_id').on('change', function() {
                var typeId = $(this).val();

                if (!typeId) {
                    $('#brand').empty().append('<option value="">Select Brand</option>').trigger('change');
                    return;
                }

                var url = "<?php echo e(route('vehicle.brand', ':id')); ?>";
                url = url.replace(':id', typeId);

                $.ajax({
                    url: url,
                    type: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        console.log("Received brands:", data);
                        var brandSelect = $('#brand');
                        brandSelect.empty();
                        brandSelect.append('<option value="">Select Brand</option>');
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
            var value = $(selector + " .repeater").attr('data-value');
            if (typeof value != 'undefined' && value.length != 0) {
                value = JSON.parse(value);
                $repeater.setList(value);
            }
        }

        $('#client_id').on('change', function() {
            "use strict";
            var client_id = $(this).val();
            var url = '<?php echo e(route('client.vehicle', ':id')); ?>';
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
                url: "<?php echo e(route('service.type')); ?>",
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
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\main_file\resources\views/client/create.blade.php ENDPATH**/ ?>