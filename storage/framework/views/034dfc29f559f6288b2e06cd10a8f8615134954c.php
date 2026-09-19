<?php
    $admin_logo = getSettingsValByName('company_logo');
    $ids = parentId();
    $authUser = \App\Models\User::find($ids);
    $subscription = \App\Models\Subscription::find($authUser->subscription);
    $routeName = \Request::route()->getName();
    $pricing_feature_settings = getSettingsValByIdName(1, 'pricing_feature');

    $theme_mode = getSettingsValByName('theme_mode');
    $light_logo = getSettingsValByName('light_logo');
    if (auth()->user()->type != 'super admin') {
        $light_logo = getSettingsValByName('company_light_logo');
    }
?>
<nav class="pc-sidebar">
    <div class="navbar-wrapper">
        <div class="m-header">
            <a href="#" class="b-brand text-primary">
                <?php if($theme_mode == 'dark'): ?>
                    <img src="<?php echo e(asset(Storage::url('upload/logo/')) . '/' . (isset($light_logo) && !empty($light_logo) ? $light_logo : 'logo.png')); ?>"
                        alt="" class="logo logo-lg" />
                <?php else: ?>
                    <img src="<?php echo e(asset(Storage::url('upload/logo/')) . '/' . (isset($admin_logo) && !empty($admin_logo) ? $admin_logo : 'logo.png')); ?>"
                        alt="" class="logo logo-lg" />
                <?php endif; ?>
            </a>
        </div>
        <div class="navbar-content">
            <ul class="pc-navbar">
                <li class="pc-item pc-caption">
                    <label><?php echo e(__('Home')); ?></label>
                    <i class="ti ti-dashboard"></i>
                </li>
                <li class="pc-item <?php echo e(in_array($routeName, ['dashboard', 'home', '']) ? 'active' : ''); ?>">
                    <a href="<?php echo e(route('dashboard')); ?>" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-dashboard"></i></span>
                        <span class="pc-mtext"><?php echo e(__('Dashboard')); ?></span>
                    </a>
                </li>
                <?php if(\Auth::user()->type == 'super admin'): ?>
                    <?php if(Gate::check('manage user')): ?>
                        <li class="pc-item <?php echo e(in_array($routeName, ['users.index', 'users.show']) ? 'active' : ''); ?>">
                            <a href="<?php echo e(route('users.index')); ?>" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-user-plus"></i></span>
                                <span class="pc-mtext"><?php echo e(__('Customers')); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if(Gate::check('manage user') || Gate::check('manage role') || Gate::check('manage logged history')): ?>
                        <li
                            class="pc-item pc-hasmenu <?php echo e(in_array($routeName, ['users.index', 'logged.history', 'role.index', 'role.create', 'role.edit']) ? 'pc-trigger active' : ''); ?>">
                            <a href="#!" class="pc-link">
                                <span class="pc-micon">
                                    <i class="ti ti-users"></i>
                                </span>
                                <span class="pc-mtext"><?php echo e(__('Staff Management')); ?></span>
                                <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
                            </a>
                            <ul class="pc-submenu"
                                style="display: <?php echo e(in_array($routeName, ['users.index', 'logged.history', 'role.index', 'role.create', 'role.edit']) ? 'block' : 'none'); ?>">
                                <?php if(Gate::check('manage user')): ?>
                                    <li class="pc-item <?php echo e(in_array($routeName, ['users.index']) ? 'active' : ''); ?>">
                                        <a class="pc-link" href="<?php echo e(route('users.index')); ?>"><?php echo e(__('Users')); ?></a>
                                    </li>
                                <?php endif; ?>
                                <?php if(Gate::check('manage role')): ?>
                                    <li
                                        class="pc-item  <?php echo e(in_array($routeName, ['role.index', 'role.create', 'role.edit']) ? 'active' : ''); ?>">
                                        <a class="pc-link" href="<?php echo e(route('role.index')); ?>"><?php echo e(__('Roles')); ?> </a>
                                    </li>
                                <?php endif; ?>
                                <?php if($pricing_feature_settings == 'off' || $subscription->enabled_logged_history == 1): ?>
                                    <?php if(Gate::check('manage logged history')): ?>
                                        <li
                                            class="pc-item  <?php echo e(in_array($routeName, ['logged.history']) ? 'active' : ''); ?>">
                                            <a class="pc-link"
                                                href="<?php echo e(route('logged.history')); ?>"><?php echo e(__('Logged History')); ?></a>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if(Gate::check('manage employee') ||
                        Gate::check('manage client') ||
                        Gate::check('manage driver') ||
                        Gate::check('manage contact') ||
                        Gate::check('manage support') ||
                        Gate::check('manage note') ||
                        Gate::check('manage n8n') ||
                        Gate::check('manage service report') ||
                        Gate::check('manage income report') ||
                        Gate::check('manage expense report') ||
                        Gate::check('manage profit and loss report')): ?>

                    <li class="pc-item pc-caption">
                        <label><?php echo e(__('Business Management')); ?></label>
                        <i class="ti ti-chart-arcs"></i>
                    </li>
                    <?php if(Gate::check('manage employee')): ?>
                        <li
                            class="pc-item <?php echo e(in_array($routeName, ['employee.index', 'employee.show']) ? 'active' : ''); ?>">
                            <a href="<?php echo e(route('employee.index')); ?>" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-user-plus"></i></span>
                                <span class="pc-mtext"><?php echo e(__('Employee')); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if(Gate::check('manage client')): ?>
                        <li
                            class="pc-item <?php echo e(in_array($routeName, ['client.index', 'client.create', 'client.show']) ? 'active' : ''); ?>">
                            <a href="<?php echo e(route('client.index')); ?>" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-user"></i></span>
                                <span class="pc-mtext"><?php echo e(__('Client')); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if(Gate::check('manage item')): ?>
                        <li class="pc-item <?php echo e(in_array($routeName, ['item.index']) ? 'active' : ''); ?>">
                            <a href="<?php echo e(route('item.index')); ?>" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-shopping-cart"></i></span>
                                <span class="pc-mtext"><?php echo e(__('Item')); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if(Gate::check('manage vehicle')): ?>
                        <li class="pc-item <?php echo e(in_array($routeName, ['vehicle.index']) ? 'active' : ''); ?>">
                            <a href="<?php echo e(route('vehicle.index')); ?>" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-truck"></i></span>
                                <span class="pc-mtext"><?php echo e(__('Vehicle')); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if(Gate::check('manage service')): ?>
                        <li class="pc-item <?php echo e(in_array($routeName, ['calendar']) ? 'active' : ''); ?>">
                            <a class="pc-link" href="<?php echo e(route('calendar')); ?>"> <span class="pc-micon"><i
                                        class="ti ti-calendar"></i></span>
                                <span class="pc-mtext"><?php echo e(__('Calendar')); ?></span></a>
                        </li>
                    <?php endif; ?>
                    <?php if(Gate::check('manage service')): ?>
                        <li
                            class="pc-item pc-hasmenu <?php echo e(in_array($routeName, ['service.index', 'service.today', 'service.create', 'service.edit', 'service.show']) ? ' pc-trigger active' : ''); ?>">
                            <a href="#!" class="pc-link">
                                <span class="pc-micon">
                                    <i class="ti ti-tool"></i>
                                </span>
                                <span class="pc-mtext"><?php echo e(__('Services')); ?></span>
                                <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
                            </a>
                            <ul class="pc-submenu"
                                style="display: <?php echo e(in_array($routeName, ['service.index', 'service.create', 'service.edit', 'service.show', 'service.today']) ? 'block' : 'none'); ?>">
                                <?php if(Gate::check('create service')): ?>
                                    <li
                                        class="pc-item <?php echo e(in_array($routeName, ['service.index', 'service.create', 'service.edit', 'service.show']) ? 'active' : ''); ?>">
                                        <a class="pc-link"
                                            href="<?php echo e(route('service.create')); ?>"><?php echo e(__('Create')); ?></a>
                                    </li>
                                <?php endif; ?>
                                <?php if(Gate::check('manage service')): ?>
                                    <li class="pc-item  <?php echo e(in_array($routeName, ['service.today']) ? 'active' : ''); ?>">
                                        <a class="pc-link" href="<?php echo e(route('service.today')); ?>"><?php echo e(__('Today')); ?>

                                        </a>
                                    </li>
                                <?php endif; ?>
                                <?php if(Gate::check('manage service')): ?>
                                    <li
                                        class="pc-item <?php echo e(in_array($routeName, ['service.index', 'service.create', 'service.edit', 'service.show']) ? 'active' : ''); ?>">
                                        <a class="pc-link" href="<?php echo e(route('service.index')); ?>"><?php echo e(__('List')); ?></a>
                                    </li>
                                <?php endif; ?>
                                <?php if(Gate::check('kanban service')): ?>
                                    <li
                                        class="pc-item <?php echo e(in_array($routeName, ['service.index', 'service.create', 'service.edit', 'service.show']) ? 'active' : ''); ?>">
                                        <a class="pc-link"
                                            href="<?php echo e(route('service.kanban')); ?>"><?php echo e(__('Kanban')); ?></a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    
                    <?php if(Gate::check('manage invoice') || Gate::check('manage expense') || Gate::check('manage quotation')): ?>
                        <li
                            class="pc-item pc-hasmenu <?php echo e(in_array($routeName, ['quotation.index', 'quotation.create', 'quotation.edit', 'quotation.show', 'invoice.index', 'invoice.create', 'invoice.edit', 'invoice.show', 'expense.index']) ? ' pc-trigger active' : ''); ?>">
                            <a href="#!" class="pc-link">
                                <span class="pc-micon">
                                    <i class="ti ti-file-text"></i>
                                </span>
                                <span class="pc-mtext"><?php echo e(__('Finance')); ?></span>
                                <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
                            </a>
                            <ul class="pc-submenu"
                                style="display: <?php echo e(in_array($routeName, ['quotation.index', 'quotation.create', 'quotation.edit', 'quotation.show', 'invoice.index', 'invoice.create', 'invoice.edit', 'invoice.show', 'expense.index']) ? 'block' : 'none'); ?>">
                                <?php if(Gate::check('manage quotation')): ?>
                                    <li
                                        class="pc-item <?php echo e(in_array($routeName, ['quotation.index', 'quotation.create', 'quotation.edit', 'quotation.show']) ? 'active' : ''); ?>">
                                        <a class="pc-link"
                                            href="<?php echo e(route('quotation.index')); ?>"><?php echo e(__('Quotation')); ?></a>
                                    </li>
                                <?php endif; ?>
                                <?php if(Gate::check('manage invoice')): ?>
                                    <li
                                        class="pc-item <?php echo e(in_array($routeName, ['invoice.index', 'invoice.create', 'invoice.edit', 'invoice.show']) ? 'active' : ''); ?>">
                                        <a class="pc-link"
                                            href="<?php echo e(route('invoice.index')); ?>"><?php echo e(__('Invoice')); ?></a>
                                    </li>
                                <?php endif; ?>
                                <?php if(Gate::check('manage expense')): ?>
                                    <li
                                        class="pc-item  <?php echo e(in_array($routeName, ['expense.index', 'expense.index']) ? 'active' : ''); ?>">
                                        <a class="pc-link" href="<?php echo e(route('expense.index')); ?>"><?php echo e(__('Expense')); ?>

                                        </a>
                                    </li>
                                <?php endif; ?>

                            </ul>
                        </li>
                    <?php endif; ?>


                    <?php if(Gate::check('manage service report') ||
                            Gate::check('manage income report') ||
                            Gate::check('manage expense report') ||
                            Gate::check('manage profile and loss report')): ?>
                        <li
                            class="pc-item pc-hasmenu <?php echo e(in_array($routeName, ['report.service', 'report.income', 'report.expense', 'report.profit_loss']) ? ' pc-trigger active' : ''); ?>">
                            <a href="#!" class="pc-link">
                                <span class="pc-micon">
                                    <i class="ti ti-chart-infographic"></i>
                                </span>
                                <span class="pc-mtext"><?php echo e(__('Report')); ?></span>
                                <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
                            </a>
                            <ul class="pc-submenu"
                                style="display: <?php echo e(in_array($routeName, ['report.service', 'report.income', 'report.expense', 'report.profit_loss']) ? 'block' : 'none'); ?>">
                                <?php if(Gate::check('manage service report')): ?>
                                    <li
                                        class="pc-item <?php echo e(in_array($routeName, ['report.service']) ? 'active' : ''); ?>">
                                        <a class="pc-link"
                                            href="<?php echo e(route('report.service')); ?>"><?php echo e(__('Service')); ?></a>
                                    </li>
                                <?php endif; ?>
                                <?php if(Gate::check('manage income report')): ?>
                                    <li class="pc-item <?php echo e(in_array($routeName, ['report.income']) ? 'active' : ''); ?>">
                                        <a class="pc-link"
                                            href="<?php echo e(route('report.income')); ?>"><?php echo e(__('Income')); ?></a>
                                    </li>
                                <?php endif; ?>
                                <?php if(Gate::check('manage expense report')): ?>
                                    <li
                                        class="pc-item  <?php echo e(in_array($routeName, ['report.expense']) ? 'active' : ''); ?>">
                                        <a class="pc-link" href="<?php echo e(route('report.expense')); ?>"><?php echo e(__('Expense')); ?>

                                        </a>
                                    </li>
                                <?php endif; ?>
                                <?php if(Gate::check('manage profile and loss report')): ?>
                                    <li
                                        class="pc-item <?php echo e(in_array($routeName, ['report.profit_loss']) ? 'active' : ''); ?>">
                                        <a class="pc-link"
                                            href="<?php echo e(route('report.profit_loss')); ?>"><?php echo e(__('Profit & Loss')); ?></a>
                                    </li>
                                <?php endif; ?>

                            </ul>
                        </li>
                    <?php endif; ?>

                    <?php if(\Auth::user()->type !== 'super admin' && ($pricing_feature_settings == 'off' || $subscription->enabled_n8n == 1)): ?>
                        <?php if(Gate::check('manage n8n')): ?>
                            <li class="pc-item <?php echo e(in_array($routeName, ['n8n.index']) ? 'active' : ''); ?> ">
                                <a href="<?php echo e(route('n8n.index')); ?>" class="pc-link">
                                    <span class="pc-micon"><i class="ti ti-settings-automation"></i></span>
                                    <span class="pc-mtext"><?php echo e(__('N8N')); ?></span>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if(Gate::check('manage contact')): ?>
                        <li class="pc-item <?php echo e(in_array($routeName, ['contact.index']) ? 'active' : ''); ?>">
                            <a href="<?php echo e(route('contact.index')); ?>" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-phone-call"></i></span>
                                <span class="pc-mtext"><?php echo e(__('Contact Diary')); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if(Gate::check('manage note')): ?>
                        <li class="pc-item <?php echo e(in_array($routeName, ['note.index']) ? 'active' : ''); ?> ">
                            <a href="<?php echo e(route('note.index')); ?>" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-notebook"></i></span>
                                <span class="pc-mtext"><?php echo e(__('Notice Board')); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>


                <?php if(Gate::check('manage vehicle type') ||
                        Gate::check('manage vehicle brand') ||
                        Gate::check('manage service type') ||
                        Gate::check('manage tax') ||
                        Gate::check('manage notification')): ?>

                    <li class="pc-item pc-caption">
                        <label><?php echo e(__('System Configuration')); ?></label>
                        <i class="ti ti-chart-arcs"></i>
                    </li>

                    <?php if(Gate::check('manage vehicle type')): ?>
                        <li class="pc-item <?php echo e(in_array($routeName, ['vehicle-type.index']) ? 'active' : ''); ?> ">
                            <a href="<?php echo e(route('vehicle-type.index')); ?>" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-stack"></i></span>
                                <span class="pc-mtext"><?php echo e(__('Vehicle Type')); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if(Gate::check('manage vehicle brand')): ?>
                        <li class="pc-item <?php echo e(in_array($routeName, ['vehicle-brand.index']) ? 'active' : ''); ?> ">
                            <a href="<?php echo e(route('vehicle-brand.index')); ?>" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-clipboard"></i></span>
                                <span class="pc-mtext"><?php echo e(__('Vehicle Brand')); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if(Gate::check('manage service type')): ?>
                        <li class="pc-item <?php echo e(in_array($routeName, ['service-type.index']) ? 'active' : ''); ?> ">
                            <a href="<?php echo e(route('service-type.index')); ?>" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-tag"></i></span>
                                <span class="pc-mtext"><?php echo e(__('Service Type')); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if(Gate::check('manage tax')): ?>
                        <li class="pc-item <?php echo e(in_array($routeName, ['tax.index']) ? 'active' : ''); ?>  ">
                            <a href="<?php echo e(route('tax.index')); ?>" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-percentage"></i></span>
                                <span class="pc-mtext"><?php echo e(__('Item Tax')); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if(Gate::check('manage unit')): ?>
                        <li class="pc-item <?php echo e(in_array($routeName, ['unit.index']) ? 'active' : ''); ?>  ">
                            <a href="<?php echo e(route('unit.index')); ?>" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-antenna-bars-5"></i></span>
                                <span class="pc-mtext"><?php echo e(__('Item Unit')); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>


                    <?php if(Gate::check('manage notification')): ?>
                        <li class="pc-item <?php echo e(in_array($routeName, ['notification.index']) ? 'active' : ''); ?> ">
                            <a href="<?php echo e(route('notification.index')); ?>" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-bell"></i></span>
                                <span class="pc-mtext"><?php echo e(__('Email Notification')); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>


                <?php if(Gate::check('manage pricing packages') ||
                        Gate::check('manage pricing transation') ||
                        Gate::check('manage account settings') ||
                        Gate::check('manage password settings') ||
                        Gate::check('manage general settings') ||
                        Gate::check('manage email settings') ||
                        Gate::check('manage payment settings') ||
                        Gate::check('manage company settings') ||
                        Gate::check('manage seo settings') ||
                        Gate::check('manage google recaptcha settings')): ?>
                    <li class="pc-item pc-caption">
                        <label><?php echo e(__('System Settings')); ?></label>
                        <i class="ti ti-chart-arcs"></i>
                    </li>

                    <?php if(Gate::check('manage FAQ') || Gate::check('manage Page')): ?>
                        <li
                            class="pc-item pc-hasmenu <?php echo e(in_array($routeName, ['homepage.index', 'FAQ.index', 'pages.index', 'footerSetting']) ? 'active' : ''); ?>">
                            <a href="#!" class="pc-link">
                                <span class="pc-micon">
                                    <i class="ti ti-layout-rows"></i>
                                </span>
                                <span class="pc-mtext"><?php echo e(__('CMS')); ?></span>
                                <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
                            </a>
                            <ul class="pc-submenu"
                                style="display: <?php echo e(in_array($routeName, ['homepage.index', 'FAQ.index', 'pages.index', 'footerSetting']) ? 'block' : 'none'); ?>">
                                <?php if(Gate::check('manage home page')): ?>
                                    <li
                                        class="pc-item <?php echo e(in_array($routeName, ['homepage.index']) ? 'active' : ''); ?> ">
                                        <a href="<?php echo e(route('homepage.index')); ?>"
                                            class="pc-link"><?php echo e(__('Home Page')); ?></a>
                                    </li>
                                <?php endif; ?>
                                <?php if(Gate::check('manage Page')): ?>
                                    <li class="pc-item <?php echo e(in_array($routeName, ['pages.index']) ? 'active' : ''); ?> ">
                                        <a href="<?php echo e(route('pages.index')); ?>"
                                            class="pc-link"><?php echo e(__('Custom Page')); ?></a>
                                    </li>
                                <?php endif; ?>
                                <?php if(Gate::check('manage FAQ')): ?>
                                    <li class="pc-item <?php echo e(in_array($routeName, ['FAQ.index']) ? 'active' : ''); ?> ">
                                        <a href="<?php echo e(route('FAQ.index')); ?>" class="pc-link"><?php echo e(__('FAQ')); ?></a>
                                    </li>
                                <?php endif; ?>
                                <?php if(Gate::check('manage footer')): ?>
                                    <li
                                        class="pc-item <?php echo e(in_array($routeName, ['footerSetting']) ? 'active' : ''); ?> ">
                                        <a href="<?php echo e(route('footerSetting')); ?>"
                                            class="pc-link"><?php echo e(__('Footer')); ?></a>
                                    </li>
                                <?php endif; ?>
                                <?php if(Gate::check('manage auth page')): ?>
                                    <li
                                        class="pc-item <?php echo e(in_array($routeName, ['authPage.index']) ? 'active' : ''); ?> ">
                                        <a href="<?php echo e(route('authPage.index')); ?>"
                                            class="pc-link"><?php echo e(__('Auth Page')); ?></a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                    <?php if(Auth::user()->type == 'super admin' || $pricing_feature_settings == 'on'): ?>
                        <?php if(Gate::check('manage pricing packages') || Gate::check('manage pricing transation')): ?>
                            <li
                                class="pc-item pc-hasmenu <?php echo e(in_array($routeName, ['subscriptions.index', 'subscriptions.show', 'subscription.transaction']) ? 'pc-trigger active' : ''); ?>">
                                <a href="#!" class="pc-link">
                                    <span class="pc-micon">
                                        <i class="ti ti-package"></i>
                                    </span>
                                    <span class="pc-mtext"><?php echo e(__('Pricing')); ?></span>
                                    <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
                                </a>
                                <ul class="pc-submenu"
                                    style="display: <?php echo e(in_array($routeName, ['subscriptions.index', 'subscriptions.show', 'subscription.transaction']) ? 'block' : 'none'); ?>">
                                    <?php if(Gate::check('manage pricing packages')): ?>
                                        <li
                                            class="pc-item <?php echo e(in_array($routeName, ['subscriptions.index', 'subscriptions.show']) ? 'active' : ''); ?>">
                                            <a class="pc-link"
                                                href="<?php echo e(route('subscriptions.index')); ?>"><?php echo e(__('Packages')); ?></a>
                                        </li>
                                    <?php endif; ?>
                                    <?php if(Gate::check('manage pricing transation')): ?>
                                        <li
                                            class="pc-item <?php echo e(in_array($routeName, ['subscription.transaction']) ? 'active' : ''); ?>">
                                            <a class="pc-link"
                                                href="<?php echo e(route('subscription.transaction')); ?>"><?php echo e(__('Transactions')); ?></a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if(Gate::check('manage coupon') || Gate::check('manage coupon history')): ?>
                        <li
                            class="pc-item pc-hasmenu <?php echo e(in_array($routeName, ['coupons.index', 'coupons.history']) ? 'active' : ''); ?>">
                            <a href="#!" class="pc-link">
                                <span class="pc-micon">
                                    <i class="ti ti-shopping-cart-discount"></i>
                                </span>
                                <span class="pc-mtext"><?php echo e(__('Coupons')); ?></span>
                                <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
                            </a>
                            <ul class="pc-submenu"
                                style="display: <?php echo e(in_array($routeName, ['coupons.index', 'coupons.history']) ? 'block' : 'none'); ?>">
                                <?php if(Gate::check('manage coupon')): ?>
                                    <li
                                        class="pc-item <?php echo e(in_array($routeName, ['coupons.index']) ? 'active' : ''); ?>">
                                        <a class="pc-link"
                                            href="<?php echo e(route('coupons.index')); ?>"><?php echo e(__('All Coupon')); ?></a>
                                    </li>
                                <?php endif; ?>
                                <?php if(Gate::check('manage coupon history')): ?>
                                    <li
                                        class="pc-item <?php echo e(in_array($routeName, ['coupons.history']) ? 'active' : ''); ?>">
                                        <a class="pc-link"
                                            href="<?php echo e(route('coupons.history')); ?>"><?php echo e(__('Coupon History')); ?></a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                    <?php if(Gate::check('manage account settings') ||
                            Gate::check('manage password settings') ||
                            Gate::check('manage general settings') ||
                            Gate::check('manage email settings') ||
                            Gate::check('manage payment settings') ||
                            Gate::check('manage company settings') ||
                            Gate::check('manage seo settings') ||
                            Gate::check('manage google recaptcha settings')): ?>
                        <li class="pc-item <?php echo e(in_array($routeName, ['setting.index']) ? 'active' : ''); ?> ">
                            <a href="<?php echo e(route('setting.index')); ?>" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-settings"></i></span>
                                <span class="pc-mtext"><?php echo e(__('Settings')); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>

                <?php endif; ?>
            </ul>
            <div class="w-100 text-center">
                <div class="badge theme-version badge rounded-pill bg-light text-dark f-12"></div>
            </div>
        </div>
    </div>
</nav>
<?php /**PATH C:\xampp\htdocs\main_file\resources\views/admin/menu.blade.php ENDPATH**/ ?>