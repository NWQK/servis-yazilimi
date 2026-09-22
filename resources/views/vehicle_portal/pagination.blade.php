@if ($paginator->hasPages())
<nav class="pagination" aria-label="{{ $anchor }} sayfaları">
    @if ($paginator->previousPageUrl())<a href="{{ $paginator->previousPageUrl() }}#{{ $anchor }}">← Önceki</a>@endif
    <span>{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
    @if ($paginator->nextPageUrl())<a href="{{ $paginator->nextPageUrl() }}#{{ $anchor }}">Sonraki →</a>@endif
</nav>
@endif
