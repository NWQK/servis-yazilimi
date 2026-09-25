@php
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
@endphp
<nav class="pc-sidebar">
    <div class="navbar-wrapper">
        <div class="m-header">
            <a href="#" class="b-brand text-primary">
                @if ($theme_mode == 'dark')
                    <img src="{{ asset(Storage::url('upload/logo/')) . '/' . (isset($light_logo) && !empty($light_logo) ? $light_logo : 'logo.png') }}"
                        alt="" class="logo logo-lg" />
                @else
                    <img src="{{ asset(Storage::url('upload/logo/')) . '/' . (isset($admin_logo) && !empty($admin_logo) ? $admin_logo : 'logo.png') }}"
                        alt="" class="logo logo-lg" />
                @endif
            </a>
        </div>
        <div class="navbar-content">
            <ul class="pc-navbar">
                <li class="pc-item pc-caption">
                    <label>{{ __('Home') }}</label>
                    <i class="ti ti-dashboard"></i>
                </li>
                <li class="pc-item {{ in_array($routeName, ['dashboard', 'home', '']) ? 'active' : '' }}">
                    <a href="{{ route('dashboard') }}" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-dashboard"></i></span>
                        <span class="pc-mtext">{{ __('Dashboard') }}</span>
                    </a>
                </li>
                @if (auth()->user()->type === 'owner')
                    <li class="pc-item {{ request()->routeIs('appointments.index') ? 'active' : '' }}">
                        <a href="{{ route('appointments.index') }}" class="pc-link"><span class="pc-micon"><i class="ti ti-calendar"></i></span><span class="pc-mtext">Randevular</span></a>
                    </li>
                    <li class="pc-item {{ request()->routeIs('appointments.settings*') ? 'active' : '' }}">
                        <a href="{{ route('appointments.settings') }}" class="pc-link"><span class="pc-micon"><i class="ti ti-clock"></i></span><span class="pc-mtext">Randevu ayarları</span></a>
                    </li>
                @endif
                @if (\Auth::user()->type == 'super admin')
                    @if (Gate::check('manage user'))
                        <li class="pc-item {{ in_array($routeName, ['users.index', 'users.show']) ? 'active' : '' }}">
                            <a href="{{ route('users.index') }}" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-user-plus"></i></span>
                                <span class="pc-mtext">{{ __('Customers') }}</span>
                            </a>
                        </li>
                    @endif
                @else
                    @if (Gate::check('manage user') || Gate::check('manage role') || Gate::check('manage logged history'))
                        <li
                            class="pc-item pc-hasmenu {{ in_array($routeName, ['users.index', 'logged.history', 'role.index', 'role.create', 'role.edit']) ? 'pc-trigger active' : '' }}">
                            <a href="#!" class="pc-link">
                                <span class="pc-micon">
                                    <i class="ti ti-users"></i>
                                </span>
                                <span class="pc-mtext">{{ __('Staff Management') }}</span>
                                <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
                            </a>
                            <ul class="pc-submenu"
                                style="display: {{ in_array($routeName, ['users.index', 'logged.history', 'role.index', 'role.create', 'role.edit']) ? 'block' : 'none' }}">
                                @if (Gate::check('manage user'))
                                    <li class="pc-item {{ in_array($routeName, ['users.index']) ? 'active' : '' }}">
                                        <a class="pc-link" href="{{ route('users.index') }}">{{ __('Users') }}</a>
                                    </li>
                                @endif
                                @if (Gate::check('manage role'))
                                    <li
                                        class="pc-item  {{ in_array($routeName, ['role.index', 'role.create', 'role.edit']) ? 'active' : '' }}">
                                        <a class="pc-link" href="{{ route('role.index') }}">{{ __('Roles') }} </a>
                                    </li>
                                @endif
                                @if ($pricing_feature_settings == 'off' || $subscription->enabled_logged_history == 1)
                                    @if (Gate::check('manage logged history'))
                                        <li
                                            class="pc-item  {{ in_array($routeName, ['logged.history']) ? 'active' : '' }}">
                                            <a class="pc-link"
                                                href="{{ route('logged.history') }}">{{ __('Logged History') }}</a>
                                        </li>
                                    @endif
                                @endif
                            </ul>
                        </li>
                    @endif
                @endif
                @if (Gate::check('manage employee') ||
                        Gate::check('manage client') ||
                        Gate::check('manage driver') ||
                        Gate::check('manage contact') ||
                        Gate::check('manage support') ||
                        Gate::check('manage note') ||
                        Gate::check('manage n8n') ||
                        Gate::check('manage service report') ||
                        Gate::check('manage income report') ||
                        Gate::check('manage expense report') ||
                        Gate::check('manage profit and loss report'))

                    <li class="pc-item pc-caption">
                        <label>{{ __('Business Management') }}</label>
                        <i class="ti ti-chart-arcs"></i>
                    </li>
                    @if (Gate::check('manage employee'))
                        <li
                            class="pc-item {{ in_array($routeName, ['employee.index', 'employee.show']) ? 'active' : '' }}">
                            <a href="{{ route('employee.index') }}" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-user-plus"></i></span>
                                <span class="pc-mtext">{{ __('Employee') }}</span>
                            </a>
                        </li>
                    @endif
                    @if (Gate::check('manage client'))
                        <li
                            class="pc-item {{ in_array($routeName, ['client.index', 'client.create', 'client.show']) ? 'active' : '' }}">
                            <a href="{{ route('client.index') }}" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-user"></i></span>
                                <span class="pc-mtext">{{ __('Client') }}</span>
                            </a>
                        </li>
                    @endif
                    @if (Gate::check('manage item'))
                        <li class="pc-item {{ str_starts_with($routeName, 'item-category.') ? 'active' : '' }}">
                            <a href="{{ route('item-category.index') }}" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-folder"></i></span>
                                <span class="pc-mtext">Ürün Kategorileri</span>
                            </a>
                        </li>
                        <li class="pc-item {{ in_array($routeName, ['item.index']) ? 'active' : '' }}">
                            <a href="{{ route('item.index') }}" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-shopping-cart"></i></span>
                                <span class="pc-mtext">{{ __('Item') }}</span>
                            </a>
                        </li>
                    @endif
                    @if (Gate::check('manage vehicle'))
                        <li class="pc-item {{ in_array($routeName, ['vehicle.index']) ? 'active' : '' }}">
                            <a href="{{ route('vehicle.index') }}" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-truck"></i></span>
                                <span class="pc-mtext">{{ __('Vehicle') }}</span>
                            </a>
                        </li>
                    @endif
                    @if (Gate::check('manage service'))
                        <li class="pc-item {{ in_array($routeName, ['calendar']) ? 'active' : '' }}">
                            <a class="pc-link" href="{{ route('calendar') }}"> <span class="pc-micon"><i
                                        class="ti ti-calendar"></i></span>
                                <span class="pc-mtext">{{ __('Calendar') }}</span></a>
                        </li>
                    @endif
                    @if (Gate::check('manage service'))
                        <li
                            class="pc-item pc-hasmenu {{ in_array($routeName, ['service.index', 'service.today', 'service.create', 'service.edit', 'service.show']) ? ' pc-trigger active' : '' }}">
                            <a href="#!" class="pc-link">
                                <span class="pc-micon">
                                    <i class="ti ti-tool"></i>
                                </span>
                                <span class="pc-mtext">{{ __('Services') }}</span>
                                <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
                            </a>
                            <ul class="pc-submenu"
                                style="display: {{ in_array($routeName, ['service.index', 'service.create', 'service.edit', 'service.show', 'service.today']) ? 'block' : 'none' }}">
                                @if (Gate::check('create service'))
                                    <li
                                        class="pc-item {{ in_array($routeName, ['service.index', 'service.create', 'service.edit', 'service.show']) ? 'active' : '' }}">
                                        <a class="pc-link"
                                            href="{{ route('service.create') }}">{{ __('Create') }}</a>
                                    </li>
                                @endif
                                @if (Gate::check('manage service'))
                                    <li class="pc-item  {{ in_array($routeName, ['service.today']) ? 'active' : '' }}">
                                        <a class="pc-link" href="{{ route('service.today') }}">{{ __('Today') }}
                                        </a>
                                    </li>
                                @endif
                                @if (Gate::check('manage service'))
                                    <li
                                        class="pc-item {{ in_array($routeName, ['service.index', 'service.create', 'service.edit', 'service.show']) ? 'active' : '' }}">
                                        <a class="pc-link" href="{{ route('service.index') }}">{{ __('List') }}</a>
                                    </li>
                                @endif
                                @if (Gate::check('kanban service'))
                                    <li
                                        class="pc-item {{ in_array($routeName, ['service.index', 'service.create', 'service.edit', 'service.show']) ? 'active' : '' }}">
                                        <a class="pc-link"
                                            href="{{ route('service.kanban') }}">{{ __('Kanban') }}</a>
                                    </li>
                                @endif
                            </ul>
                        </li>
                    @endif

                    {{-- @if (Gate::check('manage invoice') && Gate::check('manage expense')) --}}
                    @if (Gate::check('manage invoice') || Gate::check('manage expense') || Gate::check('manage quotation'))
                        <li
                            class="pc-item pc-hasmenu {{ in_array($routeName, ['quotation.index', 'quotation.create', 'quotation.edit', 'quotation.show', 'invoice.index', 'invoice.create', 'invoice.edit', 'invoice.show', 'expense.index']) ? ' pc-trigger active' : '' }}">
                            <a href="#!" class="pc-link">
                                <span class="pc-micon">
                                    <i class="ti ti-file-text"></i>
                                </span>
                                <span class="pc-mtext">{{ __('Finance') }}</span>
                                <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
                            </a>
                            <ul class="pc-submenu"
                                style="display: {{ in_array($routeName, ['quotation.index', 'quotation.create', 'quotation.edit', 'quotation.show', 'invoice.index', 'invoice.create', 'invoice.edit', 'invoice.show', 'expense.index']) ? 'block' : 'none' }}">
                                @if (Gate::check('manage quotation'))
                                    <li
                                        class="pc-item {{ in_array($routeName, ['quotation.index', 'quotation.create', 'quotation.edit', 'quotation.show']) ? 'active' : '' }}">
                                        <a class="pc-link"
                                            href="{{ route('quotation.index') }}">{{ __('Quotation') }}</a>
                                    </li>
                                @endif
                                @if (Gate::check('manage invoice'))
                                    <li
                                        class="pc-item {{ in_array($routeName, ['invoice.index', 'invoice.create', 'invoice.edit', 'invoice.show']) ? 'active' : '' }}">
                                        <a class="pc-link"
                                            href="{{ route('invoice.index') }}">{{ __('Invoice') }}</a>
                                    </li>
                                @endif
                                @if (Gate::check('manage expense'))
                                    <li
                                        class="pc-item  {{ in_array($routeName, ['expense.index', 'expense.index']) ? 'active' : '' }}">
                                        <a class="pc-link" href="{{ route('expense.index') }}">{{ __('Expense') }}
                                        </a>
                                    </li>
                                @endif

                            </ul>
                        </li>
                    @endif


                    @if (Gate::check('manage service report') ||
                            Gate::check('manage income report') ||
                            Gate::check('manage expense report') ||
                            Gate::check('manage profile and loss report'))
                        <li
                            class="pc-item pc-hasmenu {{ in_array($routeName, ['report.service', 'report.income', 'report.expense', 'report.profit_loss']) ? ' pc-trigger active' : '' }}">
                            <a href="#!" class="pc-link">
                                <span class="pc-micon">
                                    <i class="ti ti-chart-infographic"></i>
                                </span>
                                <span class="pc-mtext">{{ __('Report') }}</span>
                                <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
                            </a>
                            <ul class="pc-submenu"
                                style="display: {{ in_array($routeName, ['report.service', 'report.income', 'report.expense', 'report.profit_loss']) ? 'block' : 'none' }}">
                                @if (Gate::check('manage service report'))
                                    <li
                                        class="pc-item {{ in_array($routeName, ['report.service']) ? 'active' : '' }}">
                                        <a class="pc-link"
                                            href="{{ route('report.service') }}">{{ __('Service') }}</a>
                                    </li>
                                @endif
                                @if (Gate::check('manage income report'))
                                    <li class="pc-item {{ in_array($routeName, ['report.income']) ? 'active' : '' }}">
                                        <a class="pc-link"
                                            href="{{ route('report.income') }}">{{ __('Income') }}</a>
                                    </li>
                                @endif
                                @if (Gate::check('manage expense report'))
                                    <li
                                        class="pc-item  {{ in_array($routeName, ['report.expense']) ? 'active' : '' }}">
                                        <a class="pc-link" href="{{ route('report.expense') }}">{{ __('Expense') }}
                                        </a>
                                    </li>
                                @endif
                                @if (Gate::check('manage profile and loss report'))
                                    <li
                                        class="pc-item {{ in_array($routeName, ['report.profit_loss']) ? 'active' : '' }}">
                                        <a class="pc-link"
                                            href="{{ route('report.profit_loss') }}">{{ __('Profit & Loss') }}</a>
                                    </li>
                                @endif

                            </ul>
                        </li>
                    @endif

                    @if (\Auth::user()->type !== 'super admin' && ($pricing_feature_settings == 'off' || $subscription->enabled_n8n == 1))
                        @if (Gate::check('manage n8n'))
                            <li class="pc-item {{ in_array($routeName, ['n8n.index']) ? 'active' : '' }} ">
                                <a href="{{ route('n8n.index') }}" class="pc-link">
                                    <span class="pc-micon"><i class="ti ti-settings-automation"></i></span>
                                    <span class="pc-mtext">{{ __('N8N') }}</span>
                                </a>
                            </li>
                        @endif
                    @endif

                    @if (Gate::check('manage contact'))
                        <li class="pc-item {{ in_array($routeName, ['contact.index']) ? 'active' : '' }}">
                            <a href="{{ route('contact.index') }}" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-phone-call"></i></span>
                                <span class="pc-mtext">{{ __('Contact Diary') }}</span>
                            </a>
                        </li>
                    @endif
                    @if (Gate::check('manage note'))
                        <li class="pc-item {{ in_array($routeName, ['note.index']) ? 'active' : '' }} ">
                            <a href="{{ route('note.index') }}" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-notebook"></i></span>
                                <span class="pc-mtext">{{ __('Notice Board') }}</span>
                            </a>
                        </li>
                    @endif
                @endif


                @if (Gate::check('manage vehicle type') ||
                        Gate::check('manage vehicle brand') ||
                        Gate::check('manage service type') ||
                        Gate::check('manage tax') ||
                        Gate::check('manage notification'))

                    <li class="pc-item pc-caption">
                        <label>{{ __('System Configuration') }}</label>
                        <i class="ti ti-chart-arcs"></i>
                    </li>

                    @if (Gate::check('manage vehicle type'))
                        <li class="pc-item {{ in_array($routeName, ['vehicle-type.index']) ? 'active' : '' }} ">
                            <a href="{{ route('vehicle-type.index') }}" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-stack"></i></span>
                                <span class="pc-mtext">{{ __('Vehicle Brand') }}</span>
                            </a>
                        </li>
                    @endif

                    @if (Gate::check('manage vehicle brand'))
                        <li class="pc-item {{ in_array($routeName, ['vehicle-brand.index']) ? 'active' : '' }} ">
                            <a href="{{ route('vehicle-brand.index') }}" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-clipboard"></i></span>
                                <span class="pc-mtext">{{ __('Vehicle Model') }}</span>
                            </a>
                        </li>
                    @endif

                    @if (Gate::check('manage service type'))
                        <li class="pc-item {{ in_array($routeName, ['service-type.index']) ? 'active' : '' }} ">
                            <a href="{{ route('service-type.index') }}" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-tag"></i></span>
                                <span class="pc-mtext">{{ __('Service Type') }}</span>
                            </a>
                        </li>
                    @endif

                    @if (Gate::check('manage tax'))
                        <li class="pc-item {{ in_array($routeName, ['tax.index']) ? 'active' : '' }}  ">
                            <a href="{{ route('tax.index') }}" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-percentage"></i></span>
                                <span class="pc-mtext">{{ __('Item Tax') }}</span>
                            </a>
                        </li>
                    @endif

                    @if (Gate::check('manage unit'))
                        <li class="pc-item {{ in_array($routeName, ['unit.index']) ? 'active' : '' }}  ">
                            <a href="{{ route('unit.index') }}" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-antenna-bars-5"></i></span>
                                <span class="pc-mtext">{{ __('Item Unit') }}</span>
                            </a>
                        </li>
                    @endif


                    @if (Gate::check('manage notification'))
                        <li class="pc-item {{ in_array($routeName, ['notification.index']) ? 'active' : '' }} ">
                            <a href="{{ route('notification.index') }}" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-bell"></i></span>
                                <span class="pc-mtext">{{ __('Email Notification') }}</span>
                            </a>
                        </li>
                    @endif
                @endif


                @if (Gate::check('manage pricing packages') ||
                        Gate::check('manage pricing transation') ||
                        Gate::check('manage account settings') ||
                        Gate::check('manage password settings') ||
                        Gate::check('manage general settings') ||
                        Gate::check('manage email settings') ||
                        Gate::check('manage payment settings') ||
                        Gate::check('manage company settings') ||
                        Gate::check('manage seo settings') ||
                        Gate::check('manage google recaptcha settings'))
                    <li class="pc-item pc-caption">
                        <label>{{ __('System Settings') }}</label>
                        <i class="ti ti-chart-arcs"></i>
                    </li>

                    @if (Gate::check('manage FAQ') || Gate::check('manage Page'))
                        <li
                            class="pc-item pc-hasmenu {{ in_array($routeName, ['homepage.index', 'FAQ.index', 'pages.index', 'footerSetting']) ? 'active' : '' }}">
                            <a href="#!" class="pc-link">
                                <span class="pc-micon">
                                    <i class="ti ti-layout-rows"></i>
                                </span>
                                <span class="pc-mtext">{{ __('CMS') }}</span>
                                <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
                            </a>
                            <ul class="pc-submenu"
                                style="display: {{ in_array($routeName, ['homepage.index', 'FAQ.index', 'pages.index', 'footerSetting']) ? 'block' : 'none' }}">
                                @if (Gate::check('manage home page'))
                                    <li
                                        class="pc-item {{ in_array($routeName, ['homepage.index']) ? 'active' : '' }} ">
                                        <a href="{{ route('homepage.index') }}"
                                            class="pc-link">{{ __('Home Page') }}</a>
                                    </li>
                                @endif
                                @if (Gate::check('manage Page'))
                                    <li class="pc-item {{ in_array($routeName, ['pages.index']) ? 'active' : '' }} ">
                                        <a href="{{ route('pages.index') }}"
                                            class="pc-link">{{ __('Custom Page') }}</a>
                                    </li>
                                @endif
                                @if (Gate::check('manage FAQ'))
                                    <li class="pc-item {{ in_array($routeName, ['FAQ.index']) ? 'active' : '' }} ">
                                        <a href="{{ route('FAQ.index') }}" class="pc-link">{{ __('FAQ') }}</a>
                                    </li>
                                @endif
                                @if (Gate::check('manage footer'))
                                    <li
                                        class="pc-item {{ in_array($routeName, ['footerSetting']) ? 'active' : '' }} ">
                                        <a href="{{ route('footerSetting') }}"
                                            class="pc-link">{{ __('Footer') }}</a>
                                    </li>
                                @endif
                                @if (Gate::check('manage auth page'))
                                    <li
                                        class="pc-item {{ in_array($routeName, ['authPage.index']) ? 'active' : '' }} ">
                                        <a href="{{ route('authPage.index') }}"
                                            class="pc-link">{{ __('Auth Page') }}</a>
                                    </li>
                                @endif
                            </ul>
                        </li>
                    @endif
                    @if (Auth::user()->type == 'super admin' || $pricing_feature_settings == 'on')
                        @if (Gate::check('manage pricing packages') || Gate::check('manage pricing transation'))
                            <li
                                class="pc-item pc-hasmenu {{ in_array($routeName, ['subscriptions.index', 'subscriptions.show', 'subscription.transaction']) ? 'pc-trigger active' : '' }}">
                                <a href="#!" class="pc-link">
                                    <span class="pc-micon">
                                        <i class="ti ti-package"></i>
                                    </span>
                                    <span class="pc-mtext">{{ __('Pricing') }}</span>
                                    <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
                                </a>
                                <ul class="pc-submenu"
                                    style="display: {{ in_array($routeName, ['subscriptions.index', 'subscriptions.show', 'subscription.transaction']) ? 'block' : 'none' }}">
                                    @if (Gate::check('manage pricing packages'))
                                        <li
                                            class="pc-item {{ in_array($routeName, ['subscriptions.index', 'subscriptions.show']) ? 'active' : '' }}">
                                            <a class="pc-link"
                                                href="{{ route('subscriptions.index') }}">{{ __('Packages') }}</a>
                                        </li>
                                    @endif
                                    @if (Gate::check('manage pricing transation'))
                                        <li
                                            class="pc-item {{ in_array($routeName, ['subscription.transaction']) ? 'active' : '' }}">
                                            <a class="pc-link"
                                                href="{{ route('subscription.transaction') }}">{{ __('Transactions') }}</a>
                                        </li>
                                    @endif
                                </ul>
                            </li>
                        @endif
                    @endif
                    @if (Gate::check('manage coupon') || Gate::check('manage coupon history'))
                        <li
                            class="pc-item pc-hasmenu {{ in_array($routeName, ['coupons.index', 'coupons.history']) ? 'active' : '' }}">
                            <a href="#!" class="pc-link">
                                <span class="pc-micon">
                                    <i class="ti ti-shopping-cart-discount"></i>
                                </span>
                                <span class="pc-mtext">{{ __('Coupons') }}</span>
                                <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
                            </a>
                            <ul class="pc-submenu"
                                style="display: {{ in_array($routeName, ['coupons.index', 'coupons.history']) ? 'block' : 'none' }}">
                                @if (Gate::check('manage coupon'))
                                    <li
                                        class="pc-item {{ in_array($routeName, ['coupons.index']) ? 'active' : '' }}">
                                        <a class="pc-link"
                                            href="{{ route('coupons.index') }}">{{ __('All Coupon') }}</a>
                                    </li>
                                @endif
                                @if (Gate::check('manage coupon history'))
                                    <li
                                        class="pc-item {{ in_array($routeName, ['coupons.history']) ? 'active' : '' }}">
                                        <a class="pc-link"
                                            href="{{ route('coupons.history') }}">{{ __('Coupon History') }}</a>
                                    </li>
                                @endif
                            </ul>
                        </li>
                    @endif
                    @if (Gate::check('manage account settings') ||
                            Gate::check('manage password settings') ||
                            Gate::check('manage general settings') ||
                            Gate::check('manage email settings') ||
                            Gate::check('manage payment settings') ||
                            Gate::check('manage company settings') ||
                            Gate::check('manage seo settings') ||
                            Gate::check('manage google recaptcha settings'))
                        <li class="pc-item {{ in_array($routeName, ['setting.index']) ? 'active' : '' }} ">
                            <a href="{{ route('setting.index') }}" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-settings"></i></span>
                                <span class="pc-mtext">{{ __('Settings') }}</span>
                            </a>
                        </li>
                    @endif

                @endif
            </ul>
            <div class="w-100 text-center">
                <div class="badge theme-version badge rounded-pill bg-light text-dark f-12"></div>
            </div>
        </div>
    </div>
</nav>
