<?php $__env->startSection('page-title'); ?>
    <?php echo e(__('Service')); ?>

<?php $__env->stopSection(); ?>
<?php $__env->startPush('script-page'); ?>
    <script src="<?php echo e(asset('js/jquery-ui.min.js')); ?>"></script>
    <script src="<?php echo e(asset('js/jquery.repeater.min.js')); ?>"></script>

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
    </script>
    <script>
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
    </script>

    <script>
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

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item"><a href="<?php echo e(route('dashboard')); ?>"><?php echo e(__('Dashboard')); ?></a></li>
    <li class="breadcrumb-item" aria-current="page"><a href="<?php echo e(route('service.index')); ?>"> <?php echo e(__('Service')); ?></a></li>
    <li class="breadcrumb-item" aria-current="page"> <?php echo e(__('Create')); ?></li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <?php echo e(Form::open(['url' => 'service', 'method' => 'post'])); ?>

    <div class="row mt-4">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5><?php echo e(__('Service Create')); ?></h5>
                        </div>
                        <?php
                            $subscriptionData = currentSubscription();
                        ?>
                        <?php if(settings()['openai_module'] == 'on' &&
                                (Auth::user()->type !== 'super admin' ||
                                    ($subscriptionData['pricing_feature_settings'] === 'off' ||
                                        $subscriptionData['subscription']->enabled_openai == 1))): ?>
                            <div class="col-auto">
                                <a href="javascript:void(0)" class="btn btn-primary mb-2 aiModal" data-size="lg"
                                    data-url="<?php echo e(route('generate.template', ['service'])); ?>"
                                    data-title="<?php echo e(__('AI Content Generator')); ?>">
                                    <span><?php echo e(__('AI Content Generator')); ?></span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="info-group">
                        <div class="row">
                            <div class="form-group col-md-4 col-lg-3">
                                <?php echo e(Form::label('client', __('Client'), ['class' => 'form-label'])); ?>

                                <?php echo Form::select('client', $clients, null, [
                                    'class' => 'form-control select2',
                                    'id' => 'client_id',
                                    'required' => 'required',
                                ]); ?>

                            </div>
                            <div class="form-group col-md-4 col-lg-3">
                                <?php echo e(Form::label('vehicle_id', __('Vehicle'), ['class' => 'form-label'])); ?>

                                <div class="vehicle_div">
                                    <select class="form-control select2 vehicle" id="vehicle" name="vehicle">
                                        <option value=""><?php echo e(__('Select Vehicle')); ?></option>
                                    </select>
                                </div>
                            </div>

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
            </div>
            <div class="card repeater">
                <div class="card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="mb-0"><?php echo e(__('Service Type')); ?></h5>
                        <a class="btn btn-secondary d-flex align-items-center gap-2" href="#" data-repeater-create="">
                            <i class="ti ti-circle-plus align-text-bottom"></i><?php echo e(__('Add Type')); ?></a>
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
                                    <a class="text-danger" data-repeater-delete data-bs-toggle="tooltip"
                                        data-bs-original-title="<?php echo e(__('Detete')); ?>" href="#"> <i
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
                <?php echo e(Form::submit(__('Create'), ['class' => 'btn btn-secondary btn-rounded', 'id' => 'invoice-submit'])); ?>

            </div>
        </div>
    </div>
    <?php echo e(Form::close()); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\main_file\resources\views/service/create.blade.php ENDPATH**/ ?>