<header class="sr-public-header">
    <div class="sr-public-nav">
        <a href="{{ route('marketing.index') }}" class="sr-public-logo" aria-label="Sanayi Randevu"><img src="{{ asset('images/brand/sanayirandevu-logo.svg') }}" width="254" height="36" alt="sanayirandevu.com"></a>
        <nav class="sr-public-links" aria-label="Hızlı bağlantılar">
            @unless(request()->routeIs('vehicle-history.*'))
                <a class="sr-public-action" href="{{ route('vehicle-history.index') }}">Şasi sorgula <span aria-hidden="true">↗</span></a>
            @endunless
            @if($directoryHeader ?? false)
                <a class="sr-public-action" href="{{ route('login') }}">İşletme girişi <span aria-hidden="true">↗</span></a>
            @else
                <span class="sr-public-label">{{ $portalHeader ?? false ? 'Müşteri ekranı' : 'Online randevu' }}</span>
            @endif
        </nav>
    </div>
</header>
