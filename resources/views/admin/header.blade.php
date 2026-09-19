@php
    $users = \Auth::user();
    $languages = \App\Models\Custom::languages();
    $userLang = \Auth::user()->lang;
    $profile = asset(Storage::url('upload/profile'));
@endphp
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

                @if (\Auth::user()->type == 'owner')
                    <li class="dropdown pc-h-item" data-bs-toggle="tooltip" data-bs-original-title="Quick Access"
                        data-bs-placement="bottom">

                        <a class="pc-head-link head-link-secondary dropdown-toggle arrow-none me-0"
                            data-bs-toggle="dropdown" href="#" role="button">
                            <i class="ti ti-plus fs-18"></i>
                        </a>

                        <div class="dropdown-menu pc-h-dropdown overflow-auto" style="max-height: 400px;">
                            <a href="#" class="dropdown-item customModal"
                                data-url="{{ route('employee.create') }}" data-title="{{ __('Create Employee') }}"
                                data-size="lg">
                                <i class="ti ti-user-plus"></i>{{ __('Create Employee') }}
                            </a>

                            <a href="{{ route('client.create') }}" class="dropdown-item" data-url="#"
                                data-title="{{ __('Create Client') }}" data-size="lg">
                                <i class="ti ti-user"></i>
                                {{ __('Create Client') }}
                            </a>

                            <a href="#" class="dropdown-item customModal" data-url="{{ route('item.create') }}"
                                data-title="{{ __('Create Item') }}" data-size="lg">
                                <i class="ti ti-shopping-cart"></i> {{ __('Create Item') }}
                            </a>

                            <a href="#" class="dropdown-item customModal" data-url="{{ route('vehicle.create') }}"
                                data-title="{{ __('Create vehicle') }}" data-size="lg">
                                <i class="ti ti-truck"></i> {{ __('Create vehicle') }}
                            </a>
                            <a href="#" class="dropdown-item customModal"
                                data-url="{{ route('service.create') }}" data-title="{{ __('Create Service') }}"
                                data-size="lg">
                                <i class="ti ti-tool"></i> {{ __('Create Service') }}
                            </a>

                            <a href="{{ route('quotation.create') }}" class="dropdown-item" data-url="#"
                                data-title="{{ __('quotation') }}" data-size="lg">
                                <i class="ti ti-file-text"></i> {{ __('quotation') }}
                            </a>

                            <a href="{{ route('invoice.create') }}" class="dropdown-item">
                                <i data-feather="file-text"></i> {{ __('Invoice') }}
                            </a>

                            <a href="#" class="dropdown-item customModal"
                                data-url="{{ route('expense.create') }}" data-title="{{ __('Create Expense') }}"
                                data-size="lg">
                                <i data-feather="file-text"></i> {{ __('Create Expense') }}
                            </a>

                            <a href="#" class="dropdown-item customModal" data-url="{{ route('n8n.create') }}"
                                data-title="{{ __('Create N8n') }}" data-size="lg">
                                <i class="ti ti-settings-automation"></i> {{ __('Create N8n') }}
                            </a>

                            <a href="#" class="dropdown-item customModal"
                                data-url="{{ route('contact.create') }}" data-title="{{ __('Create Contact') }}"
                                data-size="lg">
                                <i class="ti ti-phone-call"></i>{{ __('Create Contact') }}
                            </a>

                            <a href="#" class="dropdown-item customModal" data-url="{{ route('note.create') }}"
                                data-title="{{ __('Create Note') }}" data-size="lg">
                                <i class="ti ti-notebook"></i>{{ __('Create Note') }}
                            </a>

                            <a href="#" class="dropdown-item customModal"
                                data-url="{{ route('vehicle-type.create') }}"
                                data-title="{{ __('Create Vehicle Type') }}" data-size="lg">
                                <i class="ti ti-stack"></i>{{ __('Create Vehicle Type') }}
                            </a>

                            <a href="#" class="dropdown-item customModal"
                                data-url="{{ route('vehicle-brand.create') }}"
                                data-title="{{ __('Create Vehicle Brand') }}" data-size="lg">
                                <i class="ti ti-clipboard"></i> {{ __('Create Vehicle Brand') }}
                            </a>

                            <a href="#" class="dropdown-item customModal"
                                data-url="{{ route('service-type.create') }}" data-title="{{ __('Service Type') }}"
                                data-size="lg">
                                <i class="ti ti-tag"></i> {{ __('Service Type') }}
                            </a>
                            <a href="#" class="dropdown-item customModal" data-url="{{ route('tax.create') }}"
                                data-title="{{ __('Create Tax') }}" data-size="lg">
                                <i class="ti ti-percentage"></i> {{ __('Create Tax') }}
                            </a>
                            <a href="#" class="dropdown-item customModal"
                                data-url="{{ route('unit.create') }}" data-title="{{ __('Create Unit') }}"
                                data-size="lg">
                                <i class="ti ti-antenna-bars-5"></i>{{ __('Create Unit') }}
                            </a>
                        </div>
                    </li>
                @endif
            </ul>
        </div>
        <div class="ms-auto">
            <ul class="list-unstyled">

                <li class="dropdown pc-h-item" data-bs-toggle="tooltip"
                    data-bs-original-title="{{ __('Language') }}" data-bs-placement="bottom">
                    <a class="pc-head-link head-link-primary dropdown-toggle arrow-none me-0"
                        data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false"
                        aria-expanded="false">
                        <i class="ti ti-language"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end pc-h-dropdown">
                        @foreach ($languages as $language)
                            @if ($language != 'en')
                                <a href="{{ route('language.change', $language) }}"
                                    class="dropdown-item {{ $userLang == $language ? 'active' : '' }}">
                                    <span class="align-middle">{{ ucfirst($language) }}</span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </li>
                @if (\Auth::user()->type == 'super admin' || \Auth::user()->type == 'owner')
                    <li class="dropdown pc-h-item pc-mega-menu" data-bs-toggle="tooltip"
                        data-bs-original-title="{{ __('Theme Settings') }}" data-bs-placement="bottom">
                        <a href="#" class="pc-head-link head-link-secondary dropdown-toggle arrow-none me-0"
                            data-bs-toggle="offcanvas" data-bs-target="#offcanvas_pc_layout">
                            <i class="ti ti-settings"></i>
                        </a>
                    </li>
                @endif
                <li class="dropdown pc-h-item header-user-profile">
                    <a class="pc-head-link head-link-primary dropdown-toggle arrow-none me-0"
                        data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false"
                        aria-expanded="false">
                        <img src="{{ !empty($users->profile) ? $profile . '/' . $users->profile : $profile . '/avatar.png' }}"
                            alt="user-image" class="user-avtar" />
                        <span>
                            <i class="ti ti-user-check"></i>
                        </span>
                    </a>
                    <div class="dropdown-menu dropdown-user-profile dropdown-menu-end pc-h-dropdown">
                        <div class="dropdown-header">
                            <h4>
                                {{ __('Good Morning') }},
                                <span class="small text-muted">{{ \Auth::user()->name }}</span>
                            </h4>
                            <p class="text-muted">{{ \Auth::user()->type }}</p>

                            <div class="profile-notification-scroll position-relative"
                                style="max-height: calc(100vh - 280px)">
                                <hr />

                                @impersonating()
                                    <a href="{{ route('impersonate.leave') }}" class="dropdown-item"
                                        data-actions="Account">
                                        <i class="ti ti-transfer-out"></i>
                                        <span>{{ __('Leave') }}</span>
                                    </a>
                                @endImpersonating
                                <a href="{{ route('logout') }}" class="dropdown-item"
                                    onclick="event.preventDefault(); document.getElementById('frm-logout').submit();">
                                    <i class="ti ti-logout"></i>
                                    <span>{{ __('Logout') }}</span>
                                    <form id="frm-logout" action="{{ route('logout') }}" method="POST"
                                        class="d-none">
                                        {{ csrf_field() }}
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
