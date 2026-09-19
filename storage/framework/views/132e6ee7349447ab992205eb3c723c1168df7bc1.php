<?php echo e(Form::open(['url' => 'item', 'method' => 'post'])); ?>

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
                data-url="<?php echo e(route('generate.template', ['item'])); ?>" data-title="<?php echo e(__('AI Content Generator')); ?>">
                <span><?php echo e(__('AI Content Generator')); ?></span>
            </a>
        </div>
    <?php endif; ?>
    <div class="row">
        <div class="form-group col-md-6">
            <?php echo e(Form::label('title', __('Title'), ['class' => 'form-label'])); ?>

            <?php echo e(Form::text('title', null, ['class' => 'form-control', 'placeholder' => __('Enter title'), 'required' => 'required'])); ?>

        </div>
        <div class="form-group col-md-6">
            <?php echo e(Form::label('item_code', __('Item Code'), ['class' => 'form-label'])); ?>

            <?php echo e(Form::text('item_code', null, ['class' => 'form-control', 'placeholder' => __('Enter item code'), 'required' => 'required'])); ?>

        </div>
        <div class="form-group col-md-6">
            <?php echo e(Form::label('quantity', __('Quantity'), ['class' => 'form-label'])); ?>

            <?php echo e(Form::number('quantity', null, ['class' => 'form-control', 'placeholder' => __('Enter quantity'), 'required' => 'required'])); ?>

        </div>
        <div class="form-group col-md-6">
            <?php echo e(Form::label('units', __('Unit'), ['class' => 'form-label'])); ?>

            <?php echo Form::select('units', $units, null, ['class' => 'form-control select2 ', 'required' => 'required']); ?>

        </div>
        <div class="form-group col-md-6">
            <?php echo e(Form::label('purchase_date', __('Purchase Date'), ['class' => 'form-label'])); ?>

            <?php echo e(Form::date('purchase_date', null, ['class' => 'form-control', 'required' => 'required'])); ?>

        </div>

        <div class="form-group col-md-6">
            <?php echo e(Form::label('taxs', __('Tax'), ['class' => 'form-label'])); ?>

            <?php echo Form::select('taxs[]', $taxs, null, [
                'class' => 'form-control select2 select2',
                'multiple',
                'required' => 'required',
            ]); ?>

        </div>

        <div class="form-group col-md-6">
            <?php echo e(Form::label('purchase_price', __('Purchase Price'), ['class' => 'form-label'])); ?>

            <?php echo e(Form::number('purchase_price', null, ['class' => 'form-control', 'placeholder' => __('Enter purchase price'), 'required' => 'required'])); ?>

        </div>
        <div class="form-group col-md-6">
            <?php echo e(Form::label('sales_price', __('Sales Price'), ['class' => 'form-label'])); ?>

            <?php echo e(Form::number('sales_price', null, ['class' => 'form-control', 'placeholder' => __('Enter sales price'), 'required' => 'required'])); ?>

        </div>
        <div class="form-group col-md-6">
            <?php echo e(Form::label('manufacturer_by', __('Manufacturer By'), ['class' => 'form-label'])); ?>

            <?php echo e(Form::text('manufacturer_by', null, ['class' => 'form-control', 'placeholder' => __('Enter manufacturer by'), 'required' => 'required'])); ?>

        </div>
        <div class="form-group col-md-6">
            <?php echo e(Form::label('warranty_information', __('Warranty Information'), ['class' => 'form-label'])); ?>

            <?php echo e(Form::textarea('warranty_information', null, ['class' => 'form-control', 'placeholder' => __('Enter warranty information'), 'rows' => 2])); ?>

        </div>
        <div class="form-group col-md-12">
            <?php echo e(Form::label('notes', __('Notes'), ['class' => 'form-label'])); ?>

            <?php echo e(Form::textarea('notes', null, ['class' => 'form-control', 'placeholder' => __('Enter notes'), 'rows' => 2])); ?>

        </div>
    </div>
</div>
<div class="modal-footer">
    <?php echo e(Form::submit(__('Create'), ['class' => 'btn btn-secondary ml-10'])); ?>

</div>
<?php echo e(Form::close()); ?>

<?php /**PATH C:\xampp\htdocs\main_file\resources\views/item/create.blade.php ENDPATH**/ ?>