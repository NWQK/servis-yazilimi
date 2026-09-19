<?php echo e(Form::open(['url' => 'vehicle', 'method' => 'post'])); ?>

<div class="modal-body">
    <?php
        $subscriptionData = currentSubscription();
    ?>
    <?php if(settings()['openai_module'] == 'on' &&
            (Auth::user()->type !== 'super admin' ||
                ($subscriptionData['pricing_feature_settings'] === 'off' ||
                    $subscriptionData['subscription']->enabled_openai == 1))): ?>
        <div class="text-end">
            <a href="javascript:void(0)" class="btn btn-primary mb-2 aiModal" data-size="lg"
                data-url="<?php echo e(route('generate.template', ['vehicle'])); ?>" data-title="<?php echo e(__('AI Content Generator')); ?>">
                <span><?php echo e(__('AI Content Generator')); ?></span>
            </a>
        </div>
    <?php endif; ?>
    <div class="row">
        <div class="form-group col-md-6">
            <?php echo e(Form::label('client', __('Client'), ['class' => 'form-label'])); ?>

            <?php echo Form::select('client', $clients, null, ['class' => 'form-control select2 ', 'required' => 'required']); ?>

        </div>
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
<div class="modal-footer">
    <?php echo e(Form::submit(__('Create'), ['class' => 'btn btn-secondary ml-10'])); ?>

</div>
<?php echo e(Form::close()); ?>


<script>
    $(document).ready(function() {
        // Assuming your brand Select2 is already initialized globally (via $('.select2').select2() elsewhere on the page).
        // If not, add this line to init it once on page load:
        // $('#brand').select2({ width: '100%', placeholder: 'Select Brand', allowClear: true });

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
                    console.log("Received brands:", data); // Keep this for debugging

                    var brandSelect = $('#brand');

                    // Clear and rebuild options (no destroy needed)
                    brandSelect.empty();
                    brandSelect.append('<option value="">Select Brand</option>');

                    // Append new options
                    $.each(data, function(key, value) {
                        brandSelect.append('<option value="' + key + '">' + value +
                            '</option>');
                    });

                    // Trigger change to update Select2 UI
                    brandSelect.trigger('change');
                },
                error: function(xhr, status, error) {
                    console.error("AJAX error:", error);
                }
            });
        });
    });
</script>
<?php /**PATH C:\xampp\htdocs\main_file\resources\views/vehicle/create.blade.php ENDPATH**/ ?>