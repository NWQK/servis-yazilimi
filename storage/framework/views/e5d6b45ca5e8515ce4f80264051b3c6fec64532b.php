<?php $__env->startSection('page-title'); ?>
    <?php echo e(__('Invoices')); ?>

<?php $__env->stopSection(); ?>
<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item">
        <a href="<?php echo e(route('dashboard')); ?>"><?php echo e(__('Dashboard')); ?></a>
    </li>
    <li class="breadcrumb-item" aria-current="page">

        <?php echo e(__('Invoices')); ?>

    </li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <div class="row">
        <div class="col-sm-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5><?php echo e(__('Invoices List')); ?></h5>
                        </div>
                        <?php if(Gate::check('create invoice')): ?>
                            <div class="col-auto">
                                <a class="btn btn-secondary" href="<?php echo e(route('invoice.create')); ?>" data-size="lg"
                                    data-url="" data-title="<?php echo e(__('Create Invoice')); ?>"> <i
                                        class="ti ti-circle-plus align-text-bottom"></i>
                                    <?php echo e(__('Create Invoice')); ?>

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
                                    <th> <?php echo e(__('Invoice')); ?></th>
                                    <th> <?php echo e(__('Client')); ?></th>
                                    <th> <?php echo e(__('Service')); ?></th>
                                    <th> <?php echo e(__('Invoice Date')); ?></th>
                                    <th> <?php echo e(__('Total Amount')); ?></th>
                                    <th> <?php echo e(__('Status')); ?></th>
                                    <?php if(Gate::check('edit invoice') || Gate::check('delete invoice') || Gate::check('show invoice')): ?>
                                        <th class="text-right"> <?php echo e(__('Action')); ?></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $__currentLoopData = $invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr>
                                        <td><?php echo e(invoicePrefix() . $invoice->invoice_id); ?></td>
                                        <td><?php echo e(!empty($invoice->clients) ? $invoice->clients->name : '-'); ?></td>
                                        <td>
                                            <?php echo e(servicePrefix()); ?><?php echo e(!empty($invoice->services) ? $invoice->services->service_id : ''); ?>

                                        </td>
                                        <td><?php echo e(dateFormat($invoice->invoice_date)); ?></td>
                                        <td><?php echo e(priceFormat( number_format($invoice->getInvoiceAllTotalAmount(), 2))); ?></td>
                                        <td>
                                            <?php if($invoice->status == 0): ?>
                                                <span
                                                    class="badge bg-light-danger ml-3"><?php echo e(__(\App\Models\Invoice::statues()[$invoice->status])); ?></span>
                                            <?php elseif($invoice->status == 1): ?>
                                                <span
                                                    class="badge bg-light-warning ml-3"><?php echo e(__(\App\Models\Invoice::statues()[$invoice->status])); ?></span>
                                            <?php elseif($invoice->status == 2): ?>
                                                <span
                                                    class="badge bg-light-success ml-3"><?php echo e(__(\App\Models\Invoice::statues()[$invoice->status])); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <?php if(Gate::check('edit invoice') || Gate::check('delete invoice') || Gate::check('show invoice')): ?>
                                            <td>
                                                <div class="cart-action">
                                                    <?php echo Form::open(['method' => 'DELETE', 'route' => ['invoice.destroy', $invoice->id]]); ?>

                                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('show invoice')): ?>
                                                        <a class="avtar avtar-xs btn-link-warning text-warning" data-bs-toggle="tooltip"
                                                            data-bs-original-title="<?php echo e(__('Detail')); ?>"
                                                            href="<?php echo e(route('invoice.show', \Illuminate\Support\Facades\Crypt::encrypt($invoice->id))); ?>">
                                                            <i data-feather="eye"></i></a>
                                                    <?php endif; ?>
                                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('edit invoice')): ?>
                                                        <a class="avtar avtar-xs btn-link-secondary text-secondary"
                                                            data-size="lg" data-bs-toggle="tooltip"
                                                            data-bs-original-title="<?php echo e(__('Edit')); ?>" href="<?php echo e(route('invoice.edit',  encrypt($invoice->id))); ?>"
                                                            data-title="<?php echo e(__('Edit')); ?>"> <i data-feather="edit"></i></a>
                                                    <?php endif; ?>
                                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('delete invoice')): ?>
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

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\main_file\resources\views/invoice/index.blade.php ENDPATH**/ ?>