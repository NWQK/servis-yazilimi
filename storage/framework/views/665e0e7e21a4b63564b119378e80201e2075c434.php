<?php $__env->startSection('page-title'); ?>
    <?php echo e(__('Vehicle')); ?>

<?php $__env->stopSection(); ?>
<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item">
        <a href="<?php echo e(route('dashboard')); ?>"><?php echo e(__('Dashboard')); ?></a>
    </li>
    <li class="breadcrumb-item" aria-current="page">

        <?php echo e(__('Vehicle')); ?>

    </li>
<?php $__env->stopSection(); ?>



<?php $__env->startSection('content'); ?>
    <div class="row">
        <div class="col-sm-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5><?php echo e(__('Vehicle List')); ?></h5>
                        </div>
                        <?php if(Gate::check('create vehicle')): ?>
                            <div class="col-auto">
                                <a class="btn btn-secondary customModal" href="#" data-size="lg"
                                    data-url="<?php echo e(route('vehicle.create')); ?>" data-title="<?php echo e(__('Create Vehicle')); ?>"> <i
                                        class="ti ti-circle-plus align-text-bottom"></i>
                                    <?php echo e(__('Create Vehicle')); ?>

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
                                    <th><?php echo e(__('ID')); ?></th>
                                    <th><?php echo e(__('Client')); ?></th>
                                    <th><?php echo e(__('Type')); ?></th>
                                    <th><?php echo e(__('Brand')); ?></th>
                                    <th><?php echo e(__('Model')); ?></th>
                                    <th><?php echo e(__('License Plate')); ?></th>
                                    <th><?php echo e(__('Color')); ?></th>
                                    <th><?php echo e(__('Engine Type')); ?></th>
                                    <?php if(Gate::check('edit vehicle') || Gate::check('delete vehicle') || Gate::check('show vehicle')): ?>
                                        <th class="text-right"><?php echo e(__('Action')); ?></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $__currentLoopData = $vehicles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $vehicle): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr>
                                        <td><?php echo e(vehiclePrefix() . $vehicle->vehicle_id); ?> </td>
                                        <td><?php echo e(!empty($vehicle->clients) ? $vehicle->clients->name : '-'); ?> </td>
                                        <td><?php echo e(!empty($vehicle->types) ? $vehicle->types->type : '-'); ?> </td>
                                        <td><?php echo e(!empty($vehicle->brands) ? $vehicle->brands->name : '-'); ?> </td>
                                        <td><?php echo e($vehicle->model); ?> </td>
                                        <td><?php echo e($vehicle->license_plate); ?> </td>
                                        <td><?php echo e($vehicle->color); ?> </td>
                                        <td><?php echo e($vehicle->engine_type); ?> </td>
                                        <?php if(Gate::check('edit vehicle') || Gate::check('delete vehicle') || Gate::check('show vehicle')): ?>
                                        <td>
                                                <div class="cart-action">
                                                    <?php echo Form::open(['method' => 'DELETE', 'route' => ['vehicle.destroy', $vehicle->id]]); ?>

                                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('show vehicle')): ?>
                                                        <a class="avtar avtar-xs btn-link-warning text-warning customModal"
                                                            data-size="lg" data-bs-toggle="tooltip"
                                                            data-bs-original-title="<?php echo e(__('Show')); ?>" href="#"
                                                            data-url="<?php echo e(route('vehicle.show', $vehicle->id)); ?>"
                                                            data-title="<?php echo e(__('Details')); ?>"> <i data-feather="eye"></i></a>
                                                    <?php endif; ?>
                                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('edit vehicle')): ?>
                                                        <a class="avtar avtar-xs btn-link-secondary text-secondary customModal"
                                                            data-size="lg" data-bs-toggle="tooltip"
                                                            data-bs-original-title="<?php echo e(__('Edit')); ?>" href="#"
                                                            data-url="<?php echo e(route('vehicle.edit', $vehicle->id)); ?>"
                                                            data-title="<?php echo e(__('Edit')); ?>"> <i data-feather="edit"></i></a>
                                                    <?php endif; ?>
                                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('delete vehicle')): ?>
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

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\main_file\resources\views/vehicle/index.blade.php ENDPATH**/ ?>