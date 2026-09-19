<?php $__env->startSection('page-title'); ?>
    <?php echo e(__('Item')); ?>

<?php $__env->stopSection(); ?>
<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item">
        <a href="<?php echo e(route('dashboard')); ?>"><?php echo e(__('Dashboard')); ?></a>
    </li>
    <li class="breadcrumb-item" aria-current="page">

        <?php echo e(__('Item')); ?>

    </li>
<?php $__env->stopSection(); ?>



<?php $__env->startSection('content'); ?>
    <div class="row">
        <div class="col-sm-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5><?php echo e(__('Item List')); ?></h5>
                        </div>
                        <?php if(Gate::check('create item')): ?>
                            <div class="col-auto">
                                <a class="btn btn-secondary customModal" href="#" data-size="lg"
                                    data-url="<?php echo e(route('item.create')); ?>" data-title="<?php echo e(__('Create Item')); ?>"> <i
                                        class="ti ti-circle-plus align-text-bottom"></i>
                                    <?php echo e(__('Create Item')); ?>

                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="dt-responsive table-responsive">
                        <table class="table table-hover advance-datatable">
                            <thead>
                                <tr>
                                    <th><?php echo e(__('Title')); ?></th>
                                    <th><?php echo e(__('Item Code')); ?></th>
                                    <th><?php echo e(__('Quantity')); ?></th>
                                    <th><?php echo e(__('Units')); ?></th>
                                    <th><?php echo e(__('Purchase Price')); ?></th>
                                    <th><?php echo e(__('Sales Price')); ?></th>
                                    <th><?php echo e(__('Purchase Date')); ?></th>
                                    <?php if(Gate::check('edit item') || Gate::check('delete item') || Gate::check('show item')): ?>
                                        <th class="text-right"><?php echo e(__('Action')); ?></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr>
                                        <td><?php echo e($item->title); ?></td>
                                        <td><?php echo e($item->item_code); ?></td>
                                        <td><?php echo e($item->quantity); ?></td>
                                        <td><?php echo e(!empty($item->unit) ? $item->unit->unit : '-'); ?> </td>
                                        <td><?php echo e(priceFormat($item->purchase_price)); ?> </td>
                                        <td><?php echo e(priceFormat($item->sales_price)); ?> </td>
                                        <td><?php echo e(dateFormat($item->purchase_date)); ?> </td>
                                        <?php if(Gate::check('edit item') || Gate::check('delete item') || Gate::check('show item')): ?>
                                            <td>
                                                <div class="cart-action">
                                                    <?php echo Form::open(['method' => 'DELETE', 'route' => ['item.destroy', $item->id]]); ?>

                                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('show item')): ?>
                                                        <a class="avtar avtar-xs btn-link-warning text-warning customModal"
                                                            data-size="lg" data-bs-toggle="tooltip"
                                                            data-bs-original-title="<?php echo e(__('Show')); ?>" href="#"
                                                            data-url="<?php echo e(route('item.show', $item->id)); ?>"
                                                            data-title="<?php echo e(__('Details')); ?>"> <i data-feather="eye"></i></a>
                                                    <?php endif; ?>
                                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('edit item')): ?>
                                                        <a class="avtar avtar-xs btn-link-secondary text-secondary customModal"
                                                            data-size="lg" data-bs-toggle="tooltip"
                                                            data-bs-original-title="<?php echo e(__('Edit')); ?>" href="#"
                                                            data-url="<?php echo e(route('item.edit', $item->id)); ?>"
                                                            data-title="<?php echo e(__('Edit')); ?>"> <i data-feather="edit"></i></a>
                                                    <?php endif; ?>
                                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('delete item')): ?>
                                                        <a class=" avtar avtar-xs btn-link-danger text-danger confirm_dialog"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-original-title="<?php echo e(__('Detete')); ?>" href="#"> <i
                                                                data-feather="trash-2"></i></a>
                                                    <?php endif; ?>
                                                    <?php echo Form::close(); ?>

                                                </div>

                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\main_file\resources\views/item/index.blade.php ENDPATH**/ ?>