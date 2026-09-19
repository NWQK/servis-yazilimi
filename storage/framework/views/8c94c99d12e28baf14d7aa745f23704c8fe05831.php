<?php
    $profile = asset(Storage::url('upload/profile/'));
?>
<?php $__env->startSection('page-title'); ?>
    <?php echo e(__('Client')); ?>

<?php $__env->stopSection(); ?>
<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item">
        <a href="<?php echo e(route('dashboard')); ?>"><?php echo e(__('Dashboard')); ?></a>
    </li>
    <li class="breadcrumb-item" aria-current="page">
        <?php echo e(__('Client')); ?>

    </li>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
    <div class="row">
        <div class="col-sm-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5><?php echo e(__('Client List')); ?></h5>
                        </div>
                        <?php if(Gate::check('create client')): ?>
                            <div class="col-auto">
                                <a class="btn btn-secondary" href="<?php echo e(route('client.create')); ?>" data-size="lg"
                                    data-url="#" data-title="<?php echo e(__('Create Client')); ?>"> <i
                                        class="ti ti-circle-plus align-text-bottom"></i>
                                    <?php echo e(__('Create Client')); ?>

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
                                    <th><?php echo e(__('Email')); ?></th>
                                    <th><?php echo e(__('Phone Number')); ?></th>
                                    <th><?php echo e(__('Address')); ?></th>
                                    <?php if(Gate::check('edit client') || Gate::check('delete client')): ?>
                                        <th class="text-right"><?php echo e(__('Action')); ?></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $client): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr>
                                        <td><?php echo e(!empty($client->clients) ? clientPrefix() . $client->clients->client_id : '-'); ?>

                                        </td>
                                        <td class="table-user">
                                            <img src="<?php echo e(!empty($client->avatar) ? asset(Storage::url('upload/profile')) . '/' . $client->avatar : asset(Storage::url('upload/profile')) . '/avatar.png'); ?>"
                                                alt="" class="mr-2 avatar-sm rounded-circle user-avatar">
                                            <a href="#"
                                                class="text-body font-weight-semibold"><?php echo e($client->name); ?></a>
                                        </td>
                                        <td><?php echo e($client->email); ?> </td>
                                        <td><?php echo e(!empty($client->phone_number) ? $client->phone_number : '-'); ?> </td>
                                        <td><?php echo e(!empty($client->clients) ? $client->clients->address : '-'); ?> </td>
                                        <?php if(Gate::check('edit client') || Gate::check('delete client')): ?>
                                            <td>
                                                <div class="cart-action">
                                                    <?php echo Form::open(['method' => 'DELETE', 'route' => ['client.destroy', $client->id]]); ?>

                                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('show client')): ?>
                                                        <a class="avtar avtar-xs btn-link-warning text-warning" data-size="lg"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-original-title="<?php echo e(__('Show')); ?>"
                                                            href="<?php echo e(route('client.show', encrypt($client->id))); ?>"
                                                            data-url="#" data-title="<?php echo e(__('Show')); ?>"> <i
                                                                data-feather="eye"></i></a>
                                                    <?php endif; ?>

                                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('edit client')): ?>
                                                        <a class="avtar avtar-xs btn-link-secondary text-secondary "
                                                            data-size="lg" data-bs-toggle="tooltip"
                                                            data-bs-original-title="<?php echo e(__('Edit')); ?>"
                                                            href="<?php echo e(route('client.edit', encrypt($client->id))); ?>"
                                                            data-url="#" data-title="<?php echo e(__('Edit')); ?>"> <i
                                                                data-feather="edit"></i></a>
                                                    <?php endif; ?>
                                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('delete client')): ?>
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

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\main_file\resources\views/client/index.blade.php ENDPATH**/ ?>