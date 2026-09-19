<?php $__env->startSection('page-title'); ?>
    <?php echo e(__('N8N Template')); ?>

<?php $__env->stopSection(); ?>
<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item"><a href="<?php echo e(route('dashboard')); ?>"><?php echo e(__('Dashboard')); ?></a></li>
    <li class="breadcrumb-item" aria-current="page"> <?php echo e(__('N8N Template')); ?></li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <div class="row">
        <div class="col-sm-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5><?php echo e(__('N8N Template')); ?></h5>
                        </div>
                        <?php if(Gate::check('create n8n')): ?>
                            <div class="col-auto">
                                <a href="#" class="btn btn-secondary customModal" data-size="md"
                                    data-url="<?php echo e(route('n8n.create')); ?>" data-title="<?php echo e(__('Create n8n')); ?>">
                                    <i class="ti ti-circle-plus align-text-bottom"></i> <?php echo e(__('Create n8n')); ?>

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
                                    <th><?php echo e(__('Module')); ?></th>
                                    <th><?php echo e(__('URL')); ?></th>
                                    <th><?php echo e(__('Enable N8n')); ?></th>

                                    <th><?php echo e(__('Action')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $__currentLoopData = $n8ns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $n8n): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr>
                                        <td><?php echo e(\App\Models\N8n::module()[$n8n->module]); ?></td>
                                        <td><?php echo e($n8n->url); ?></td>
                                        <td>
                                            <?php if($n8n->status == 1): ?>
                                                <span class="d-inline badge text-bg-success"><?php echo e(__('Enable')); ?></span>
                                            <?php else: ?>
                                                <span class="d-inline badge text-bg-danger"><?php echo e(__('Disable')); ?></span>
                                            <?php endif; ?>

                                        </td>

                                        <td>
                                            <div class="cart-action">
                                                <?php echo Form::open(['method' => 'DELETE', 'route' => ['n8n.destroy', $n8n->id]]); ?>

                                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('edit n8n')): ?>
                                                    <a class="avtar avtar-xs btn-link-secondary text-secondary customModal"
                                                        data-bs-toggle="tooltip" data-bs-original-title="<?php echo e(__('Edit')); ?>"
                                                        href="#" data-title="<?php echo e(__('Edit N8n')); ?>"
                                                        data-url="<?php echo e(route('n8n.edit', $n8n->id)); ?>"> <i
                                                            data-feather="edit"></i></a>
                                                <?php endif; ?>

                                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('delete n8n')): ?>
                                                    <a class=" avtar avtar-xs btn-link-danger text-danger confirm_dialog"
                                                        data-bs-toggle="tooltip" data-bs-original-title="<?php echo e(__('Detete')); ?>"
                                                        href="#"> <i data-feather="trash-2"></i></a>
                                                <?php endif; ?>

                                                <?php echo Form::close(); ?>

                                            </div>

                                        </td>
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

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\main_file\resources\views/n8n/index.blade.php ENDPATH**/ ?>