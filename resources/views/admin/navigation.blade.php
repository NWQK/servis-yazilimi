@php
    // Each entry keeps its own permission; empty groups are hidden automatically.
    $isSuperAdmin = auth()->user()->type === 'super admin';
    $isOwner = auth()->user()->type === 'owner';
    $hasHistory = $pricing_feature_settings === 'off' || ($subscription->enabled_logged_history ?? 0) == 1;
    $hasAutomation = !$isSuperAdmin && ($pricing_feature_settings === 'off' || ($subscription->enabled_n8n ?? 0) == 1);
    // Owners can still view their package when public pricing is disabled.
    $hasPricing = $isSuperAdmin || $isOwner || $pricing_feature_settings === 'on';
    $entry = static fn ($label, $route, $permission, $patterns = null, $visible = true) => [
        'label' => $label, 'route' => $route, 'patterns' => $patterns ?? [str_replace('.index', '.*', $route)],
        'visible' => $visible && ($permission === null || Gate::check($permission)),
    ];
    $group = static fn ($label, $icon, $entries) => ['label' => $label, 'icon' => $icon, 'entries' => $entries];
    $sections = [
        ['label' => 'Günlük işlemler', 'groups' => [
            $group('Müşteriler', 'ti-user', [$entry('Müşteriler', 'client.index', 'manage client')]),
            $group('Araçlar', 'ti-truck', [
                $entry('Araç listesi', 'vehicle.index', 'manage vehicle'),
                $entry('QR etiketleri', 'vehicle-qr.index', 'create vehicle', null, auth()->user()->type !== 'client'),
                $entry('Araç markaları', 'vehicle-type.index', 'manage vehicle type'),
                $entry('Araç modelleri', 'vehicle-brand.index', 'manage vehicle brand'),
            ]),
            $group('Randevular', 'ti-calendar', [
                $entry('Randevu talepleri', 'appointments.index', null, ['appointments.index', 'appointments.status'], $isOwner),
                $entry('Çalışma saatleri ve ayarlar', 'appointments.settings', null, ['appointments.settings*'], $isOwner),
                $entry('Randevu hizmetleri', 'appointments.catalog', null, ['appointments.catalog*'], $isSuperAdmin),
            ]),
            $group('Servisler', 'ti-tool', [
                $entry('Yeni servis oluştur', 'service.create', 'create service', ['service.create']),
                $entry('Servis listesi', 'service.index', 'manage service', ['service.index', 'service.edit', 'service.show']),
                $entry('Bugünkü servisler', 'service.today', 'manage service', ['service.today']),
                $entry('Servis panosu', 'service.kanban', 'kanban service', ['service.kanban']),
                $entry('Servis takvimi', 'calendar', 'manage service', ['calendar']),
                $entry('Servis türleri', 'service-type.index', 'manage service type'),
                $entry('Servis raporu', 'report.service', 'manage service report', ['report.service']),
            ]),
            $group('Ürünler ve stok', 'ti-package', [
                $entry('Ürün ve stok listesi', 'item.index', 'manage item', ['item.*']),
                $entry('Ürün kategorileri', 'item-category.index', 'manage item'),
                $entry('Ürün birimleri', 'unit.index', 'manage unit'),
            ]),
            $group('Faturalar', 'ti-file-text', [
                $entry('Fatura listesi', 'invoice.index', 'manage invoice', ['invoice.*', '!invoice.create']),
                $entry('Yeni fatura oluştur', 'invoice.create', 'create invoice', ['invoice.create']),
            ]),
            $group('Teklifler', 'ti-clipboard', [$entry('Teklifler', 'quotation.index', 'manage quotation')]),
        ]],
        ['label' => 'Mali işlemler', 'groups' => [
            $group('Finans', 'ti-chart-infographic', [
                $entry('Gider kayıtları', 'expense.index', 'manage expense'),
                $entry('Gelir raporu', 'report.income', 'manage income report', ['report.income']),
                $entry('Gider raporu', 'report.expense', 'manage expense report', ['report.expense']),
                $entry('Kâr ve zarar', 'report.profit_loss', 'manage profile and loss report', ['report.profit_loss']),
                $entry('Vergi oranları', 'tax.index', 'manage tax'),
            ]),
        ]],
        ['label' => $isSuperAdmin ? 'Platform yönetimi' : 'İşletme yönetimi', 'groups' => [
            $group('İşletmeler', 'ti-users', [$entry('İşletmeler', 'users.index', 'manage user', ['users.*'], $isSuperAdmin)]),
            $group('Personel ve yetkiler', 'ti-users', [
                $entry('Personel listesi', 'employee.index', 'manage employee'),
                $entry('Kullanıcı hesapları', 'users.index', 'manage user', ['users.*'], !$isSuperAdmin),
                $entry('Roller ve yetkiler', 'role.index', 'manage role', null, !$isSuperAdmin),
                $entry('Giriş geçmişi', 'logged.history', 'manage logged history', ['logged.history'], !$isSuperAdmin && $hasHistory),
            ]),
            $group('İşletme araçları', 'ti-notebook', [
                $entry('İletişim rehberi', 'contact.index', 'manage contact'),
                $entry('Not panosu', 'note.index', 'manage note'),
                $entry('Otomasyonlar', 'n8n.index', 'manage n8n', null, $hasAutomation),
            ]),
            $group('Site içeriği', 'ti-layout-rows', [
                $entry('Ana sayfa', 'homepage.index', 'manage home page'),
                $entry('Özel sayfalar', 'pages.index', 'manage Page'),
                $entry('Sık sorulan sorular', 'FAQ.index', 'manage FAQ'),
                $entry('Alt bilgi', 'footerSetting', 'manage footer', ['footerSetting']),
                $entry('Giriş sayfası', 'authPage.index', 'manage auth page'),
            ]),
            $group($isOwner ? 'Paketim ve abonelik' : 'Paketler ve abonelik', 'ti-package', [
                $entry($isOwner ? 'Paket bilgilerim' : 'Paketler', 'subscriptions.index', 'manage pricing packages', ['subscriptions.*'], $hasPricing),
                $entry('Abonelik işlemleri', 'subscription.transaction', 'manage pricing transation', ['subscription.transaction'], $hasPricing),
                $entry('Abonelik yönetimi', 'subscription-admin.index', null, ['subscription-admin.*'], $isSuperAdmin),
                $entry('Ek kapasite talepleri', 'capacity.index', null, ['capacity.*'], $isOwner || $isSuperAdmin),
            ]),
            $group('Kuponlar', 'ti-shopping-cart-discount', [
                $entry('Kupon listesi', 'coupons.index', 'manage coupon', ['coupons.index', 'coupons.create', 'coupons.edit']),
                $entry('Kupon geçmişi', 'coupons.history', 'manage coupon history', ['coupons.history']),
            ]),
            $group('Ayarlar', 'ti-settings', [
                $entry('Genel ayarlar', 'setting.index', null, ['setting.*'], Gate::any([
                    'manage account settings', 'manage password settings', 'manage general settings',
                    'manage email settings', 'manage payment settings', 'manage company settings',
                    'manage seo settings', 'manage google recaptcha settings',
                ])),
                $entry('E-posta bildirimleri', 'notification.index', 'manage notification'),
                $entry('İşletme giriş geçmişi', 'owner-logins.index', null, ['owner-logins.*'], $isSuperAdmin),
                $entry('SMS sistemi', 'appointments.sms-settings', null, ['appointments.sms-settings*', 'appointments.sms.retry'], $isSuperAdmin),
            ]),
        ]],
    ];
    $matches = static function ($patterns) use ($routeName) {
        $included = array_filter($patterns, static fn ($pattern) => !str_starts_with($pattern, '!'));
        $excluded = array_map(static fn ($pattern) => substr($pattern, 1), array_filter($patterns, static fn ($pattern) => str_starts_with($pattern, '!')));
        return \Illuminate\Support\Str::is($included, $routeName ?? '') && !\Illuminate\Support\Str::is($excluded, $routeName ?? '');
    };
@endphp
<li class="pc-item {{ in_array($routeName, ['dashboard', 'home', '']) ? 'active' : '' }}">
    <a href="{{ route('dashboard') }}" class="pc-link">
        <span class="pc-micon"><i class="ti ti-dashboard"></i></span>
        <span class="pc-mtext">Ana sayfa</span>
    </a>
</li>
@foreach ($sections as $section)
    @php
        $visibleGroups = [];
        foreach ($section['groups'] as $menuGroup) {
            $menuGroup['entries'] = array_values(array_filter($menuGroup['entries'], static fn ($item) => $item['visible']));
            if ($menuGroup['entries']) {
                $visibleGroups[] = $menuGroup;
            }
        }
    @endphp
    @if ($visibleGroups)
        <li class="pc-item pc-caption"><label>{{ $section['label'] }}</label></li>
        @foreach ($visibleGroups as $menuGroup)
            @php
                $expanded = collect($menuGroup['entries'])->contains(static fn ($item) => $matches($item['patterns']));
                $single = count($menuGroup['entries']) === 1;
            @endphp
            <li class="pc-item {{ !$single ? 'pc-hasmenu' : '' }} {{ $expanded ? ($single ? 'active' : 'pc-trigger active') : '' }}">
                <a href="{{ $single ? route($menuGroup['entries'][0]['route']) : '#!' }}" class="pc-link">
                    <span class="pc-micon"><i class="ti {{ $menuGroup['icon'] }}"></i></span>
                    <span class="pc-mtext">{{ $single ? $menuGroup['entries'][0]['label'] : $menuGroup['label'] }}</span>
                    @unless ($single)<span class="pc-arrow"><i data-feather="chevron-right"></i></span>@endunless
                </a>
                @unless ($single)
                    <ul class="pc-submenu" style="display: {{ $expanded ? 'block' : 'none' }}">
                        @foreach ($menuGroup['entries'] as $item)
                            <li class="pc-item {{ $matches($item['patterns']) ? 'active' : '' }}">
                                <a class="pc-link" href="{{ route($item['route']) }}" @if ($matches($item['patterns'])) aria-current="page" @endif>{{ $item['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endunless
            </li>
        @endforeach
    @endif
@endforeach
