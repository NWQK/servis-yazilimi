@php($setupWarnings = app(\App\Services\OwnerSetupWarnings::class)->forUser(auth()->user()))
<li class="dropdown pc-h-item" id="owner-setup-warnings">
    <a href="#" id="owner-setup-warnings-toggle" class="pc-head-link head-link-secondary dropdown-toggle arrow-none me-0 position-relative"
        data-bs-toggle="dropdown" aria-expanded="false" aria-label="Kurulum uyarıları, {{ count($setupWarnings) }} eksik" title="Kurulum uyarıları" role="button">
        <i class="ti ti-alert-triangle" aria-hidden="true"></i>
        @if(count($setupWarnings))<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark">{{ count($setupWarnings) }}</span>@endif
    </a>
    <div class="dropdown-menu dropdown-menu-end pc-h-dropdown" aria-labelledby="owner-setup-warnings-toggle" style="width:370px;max-width:calc(100vw - 24px)">
        <div class="px-3 py-2 border-bottom"><strong>Kurulum uyarıları</strong><small class="d-block text-muted">Tamamladığınız adımlar otomatik kaldırılır.</small></div>
        <div style="max-height:min(440px, 65vh);overflow-y:auto">
            @forelse($setupWarnings as $warning)
                <div class="p-3 border-bottom" data-warning="{{ $warning['key'] }}">
                    <strong class="d-block">{{ $warning['title'] }}</strong>
                    <p class="small text-muted my-2" style="white-space:normal">{{ $warning['description'] }}</p>
                    @if(!empty($warning['steps']))
                        <ol class="list-unstyled d-flex flex-wrap align-items-center gap-1 small mb-3" aria-label="Hazırlama adımları">
                            @foreach($warning['steps'] as $step)
                                <li><span class="badge bg-light-secondary text-dark">{{ $loop->iteration }}. {{ $step }}</span>@unless($loop->last)<span class="mx-1" aria-hidden="true">→</span>@endunless</li>
                            @endforeach
                        </ol>
                    @endif
                    <div class="d-flex flex-column gap-2">
                        @foreach($warning['actions'] as $action)
                            @if(!empty($action['modal']))
                                <a href="#" class="btn btn-sm btn-outline-secondary text-start customModal" data-size="lg" data-url="{{ $action['url'] }}" data-title="Hazır marka ve modelleri yükle">{{ $action['label'] }}</a>
                            @else
                                <a href="{{ $action['url'] }}" class="btn btn-sm btn-outline-secondary text-start">{{ $action['label'] }}</a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="p-3 mb-0 text-muted">Kurulum adımlarınız tamamlandı. Yeni uyarı yok.</p>
            @endforelse
        </div>
        <div class="border-top">
            @can('manage item')<a href="{{ route('inventory.setup') }}" class="dropdown-item py-2">Ürün ve stok rehberi</a>@endcan
            @can('manage service')<a href="{{ route('service.setup') }}" class="dropdown-item py-2">Servis oluşturma rehberi</a>@endcan
        </div>
    </div>
</li>
