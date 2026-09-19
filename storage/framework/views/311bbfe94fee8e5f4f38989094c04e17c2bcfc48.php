<?php echo Form::open(['url' => url('n8n'), 'method' => 'POST']); ?>


<div class="modal-body">
    <div class="row">

        
        <div class="form-group col-md-12">
            <?php echo Form::label('module', __('Module'), ['class' => 'form-label']); ?>

            <?php echo Form::select('module', $modules, null, [
                'class' => 'form-control hidesearch module',
                'required' => true,
            ]); ?>

        </div>

        
        <div class="form-group col-md-12">
            <?php echo Form::label('method', __('Method'), ['class' => 'form-label']); ?>

            <?php echo Form::select('method', $method, null, [
                'class' => 'form-control hidesearch',
                'required' => true,
            ]); ?>

        </div>

        
        <div class="form-group col-md-12">
            <?php echo Form::label('url', __('Url'), ['class' => 'form-label']); ?>

            <?php echo Form::text('url', null, [
                'class' => 'form-control',
                'placeholder' => __('Enter url'),
                'required' => true,
            ]); ?>

        </div>

        <div class="form-group col-md-6">
            <?php echo e(Form::label('status', __('Enabled N8n'), ['class' => 'form-label'])); ?>

            <input type="hidden" name="status" value="0">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="flexSwitchCheck" name="status"
                    value="1">
                <label class="form-check-label" for="flexSwitchCheck"></label>
            </div>
        </div>
    </div>
</div>

<div class="modal-footer">
    <?php echo Form::submit(__('Create'), ['class' => 'btn btn-secondary ml-10']); ?>

</div>

<?php echo Form::close(); ?>

<?php /**PATH C:\xampp\htdocs\main_file\resources\views/n8n/create.blade.php ENDPATH**/ ?>