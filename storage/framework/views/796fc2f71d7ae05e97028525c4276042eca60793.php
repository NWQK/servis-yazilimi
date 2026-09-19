<?php
    $users = \Auth::user();
    $languages = \App\Models\Custom::languages();
    $userLang = \Auth::user()->lang;
    $profile = asset(Storage::url('upload/profile'));
?>
<header class="pc-header">
    <div class="header-wrapper">
        <div class="me-auto pc-mob-drp">
            <ul class="list-unstyled">
                <li class="pc-h-item header-mobile-collapse">
                    <a href="#" class="pc-head-link head-link-secondary ms-0" id="sidebar-hide">
                        <i class="ti ti-menu-2"></i>
                    </a>
                </li>
                <li class="pc-h-item pc-sidebar-popup">
                    <a href="#" class="pc-head-link head-link-secondary ms-0" id="mobile-collapse">
                        <i class="ti ti-menu-2"></i>
                    </a>
                </li>

                <?php if(\Auth::user()->type == 'owner'): ?>
                    <li class="dropdown pc-h-item" data-bs-toggle="tooltip" data-bs-original-title="Quick Access"
                        data-bs-placement="bottom">

                        <a class="pc-head-link head-link-secondary dropdown-toggle arrow-none me-0"
                            data-bs-toggle="dropdown" href="#" role="button">
                            <i class="ti ti-plus fs-18"></i>
                        </a>

                        <div class="dropdown-menu pc-h-dropdown overflow-auto" style="max-height: 400px;">
                            <a href="#" class="dropdown-item customModal"
                                data-url="<?php echo e(route('employee.create')); ?>" data-title="<?php echo e(__('Create Employee')); ?>"
                                data-size="lg">
                                <i class="ti ti-user-plus"></i><?php echo e(__('Create Employee')); ?>

                            </a>

                            <a href="<?php echo e(route('client.create')); ?>" class="dropdown-item" data-url="#"
                                data-title="<?php echo e(__('Create Client')); ?>" data-size="lg">
                                <i class="ti ti-user"></i>
                                <?php echo e(__('Create Client')); ?>

                            </a>

                            <a href="#" class="dropdown-item customModal" data-url="<?php echo e(route('item.create')); ?>"
                                data-title="<?php echo e(__('Create Item')); ?>" data-size="lg">
                                <i class="ti ti-shopping-cart"></i> <?php echo e(__('Create Item')); ?>

                            </a>

                            <a href="#" class="dropdown-item customModal" data-url="<?php echo e(route('vehicle.create')); ?>"
                                data-title="<?php echo e(__('Create vehicle')); ?>" data-size="lg">
                                <i class="ti ti-truck"></i> <?php echo e(__('Create vehicle')); ?>

                            </a>
                            <a href="#" class="dropdown-item customModal"
                                data-url="<?php echo e(route('service.create')); ?>" data-title="<?php echo e(__('Create Service')); ?>"
                                data-size="lg">
                                <i class="ti ti-tool"></i> <?php echo e(__('Create Service')); ?>

                            </a>

                            <a href="<?php echo e(route('quotation.create')); ?>" class="dropdown-item" data-url="#"
                                data-title="<?php echo e(__('quotation')); ?>" data-size="lg">
                                <i class="ti ti-file-text"></i> <?php echo e(__('quotation')); ?>

                            </a>

                            <a href="<?php echo e(route('invoice.create')); ?>" class="dropdown-item">
                                <i data-feather="file-text"></i> <?php echo e(__('Invoice')); ?>

                            </a>

                            <a href="#" class="dropdown-item customModal"
                                data-url="<?php echo e(route('expense.create')); ?>" data-title="<?php echo e(__('Create Expense')); ?>"
                                data-size="lg">
                                <i data-feather="file-text"></i> <?php echo e(__('Create Expense')); ?>

                            </a>

                            <a href="#" class="dropdown-item customModal" data-url="<?php echo e(route('n8n.create')); ?>"
                                data-title="<?php echo e(__('Create N8n')); ?>" data-size="lg">
                                <i class="ti ti-settings-automation"></i> <?php echo e(__('Create N8n')); ?>

                            </a>

                            <a href="#" class="dropdown-item customModal"
                                data-url="<?php echo e(route('contact.create')); ?>" data-title="<?php echo e(__('Create Contact')); ?>"
                                data-size="lg">
                                <i class="ti ti-phone-call"></i><?php echo e(__('Create Contact')); ?>

                            </a>

                            <a href="#" class="dropdown-item customModal" data-url="<?php echo e(route('note.create')); ?>"
                                data-title="<?php echo e(__('Create Note')); ?>" data-size="lg">
                                <i class="ti ti-notebook"></i><?php echo e(__('Create Note')); ?>

                            </a>

                            <a href="#" class="dropdown-item customModal"
                                data-url="<?php echo e(route('vehicle-type.create')); ?>"
                                data-title="<?php echo e(__('Create Vehicle Type')); ?>" data-size="lg">
                                <i class="ti ti-stack"></i><?php echo e(__('Create Vehicle Type')); ?>

                            </a>

                            <a href="#" class="dropdown-item customModal"
                                data-url="<?php echo e(route('vehicle-brand.create')); ?>"
                                data-title="<?php echo e(__('Create Vehicle Brand')); ?>" data-size="lg">
                                <i class="ti ti-clipboard"></i> <?php echo e(__('Create Vehicle Brand')); ?>

                            </a>

                            <a href="#" class="dropdown-item customModal"
                                data-url="<?php echo e(route('service-type.create')); ?>" data-title="<?php echo e(__('Service Type')); ?>"
                                data-size="lg">
                                <i class="ti ti-tag"></i> <?php echo e(__('Service Type')); ?>

                            </a>
                            <a href="#" class="dropdown-item customModal" data-url="<?php echo e(route('tax.create')); ?>"
                                data-title="<?php echo e(__('Create Tax')); ?>" data-size="lg">
                                <i class="ti ti-percentage"></i> <?php echo e(__('Create Tax')); ?>

                            </a>
                            <a href="#" class="dropdown-item customModal"
                                data-url="<?php echo e(route('unit.create')); ?>" data-title="<?php echo e(__('Create Unit')); ?>"
                                data-size="lg">
                                <i class="ti ti-antenna-bars-5"></i><?php echo e(__('Create Unit')); ?>

                            </a>
                        </div>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="ms-auto">
            <ul class="list-unstyled">

                <li class="dropdown pc-h-item" data-bs-toggle="tooltip"
                    data-bs-original-title="<?php echo e(__('Language')); ?>" data-bs-placement="bottom">
                    <a class="pc-head-link head-link-primary dropdown-toggle arrow-none me-0"
                        data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false"
                        aria-expanded="false">
                        <i class="ti ti-language"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end pc-h-dropdown">
                        <?php $__currentLoopData = $languages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $language): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php if($language != 'en'): ?>
                                <a href="<?php echo e(route('language.change', $language)); ?>"
                                    class="dropdown-item <?php echo e($userLang == $language ? 'active' : ''); ?>">
                                    <span class="align-middle"><?php echo e(ucfirst($language)); ?></span>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </li>
                <?php if(\Auth::user()->type == 'super admin' || \Auth::user()->type == 'owner'): ?>
                    <li class="dropdown pc-h-item pc-mega-menu" data-bs-toggle="tooltip"
                        data-bs-original-title="<?php echo e(__('Theme Settings')); ?>" data-bs-placement="bottom">
                        <a href="#" class="pc-head-link head-link-secondary dropdown-toggle arrow-none me-0"
                            data-bs-toggle="offcanvas" data-bs-target="#offcanvas_pc_layout">
                            <i class="ti ti-settings"></i>
                        </a>
                    </li>
                <?php endif; ?>
                <li class="dropdown pc-h-item header-user-profile">
                    <a class="pc-head-link head-link-primary dropdown-toggle arrow-none me-0"
                        data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false"
                        aria-expanded="false">
                        <img src="<?php echo e(!empty($users->profile) ? $profile . '/' . $users->profile : $profile . '/avatar.png'); ?>"
                            alt="user-image" class="user-avtar" />
                        <span>
                            <i class="ti ti-user-check"></i>
                        </span>
                    </a>
                    <div class="dropdown-menu dropdown-user-profile dropdown-menu-end pc-h-dropdown">
                        <div class="dropdown-header">
                            <h4>
                                <?php echo e(__('Good Morning')); ?>,
                                <span class="small text-muted"><?php echo e(\Auth::user()->name); ?></span>
                            </h4>
                            <p class="text-muted"><?php echo e(\Auth::user()->type); ?></p>

                            <div class="profile-notification-scroll position-relative"
                                style="max-height: calc(100vh - 280px)">
                                <hr />

                                <?php if (is_impersonating()) : ?>
                                    <a href="<?php echo e(route('impersonate.leave')); ?>" class="dropdown-item"
                                        data-actions="Account">
                                        <i class="ti ti-transfer-out"></i>
                                        <span><?php echo e(__('Leave')); ?></span>
                                    </a>
                                <?php endif; ?>
                                <a href="<?php echo e(route('logout')); ?>" class="dropdown-item"
                                    onclick="event.preventDefault(); document.getElementById('frm-logout').submit();">
                                    <i class="ti ti-logout"></i>
                                    <span><?php echo e(__('Logout')); ?></span>
                                    <form id="frm-logout" action="<?php echo e(route('logout')); ?>" method="POST"
                                        class="d-none">
                                        <?php echo e(csrf_field()); ?>

                                    </form>
                                </a>
                            </div>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</header>
<?php /**PATH C:\xampp\htdocs\main_file\resources\views/admin/header.blade.php ENDPATH**/ ?>