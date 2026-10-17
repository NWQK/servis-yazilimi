@if(auth()->user()?->type === 'branch_manager' || \App\Services\BranchContext::current())
<div class="alert alert-light border d-flex flex-wrap align-items-center justify-content-between gap-2">
@if($branch = \App\Services\BranchContext::current())
<div><span class="badge bg-danger">Seçili şube</span> <strong>{{ $branch['owner']->name }}</strong> <small>· #{{ $branch['owner']->id }} · {{ $branch['group']->name }}</small></div>
@else<strong>Şube merkez hesabı</strong>@endif
<a class="btn btn-sm btn-outline-primary" href="{{ route('branches.portal') }}">Şubelerim / şube değiştir</a>
</div>
@if($branch)
<script>
(function() {
    const token = @json($branch['token']);
    // Each tab carries its own immutable context. Never read a shared active-branch cookie.
    function decorate(root) {
        root.querySelectorAll('form').forEach(form => {
            const url = new URL(form.action || location.href, location.href);
            if (url.origin !== location.origin || !url.searchParams.has('_branch')) return;
            let field = form.querySelector('input[name="_branch"]');
            if (!field) { field = document.createElement('input'); field.type='hidden'; field.name='_branch'; form.appendChild(field); }
            field.value = token;
        });
    }
    document.addEventListener('DOMContentLoaded', function () {
        decorate(document);
        new MutationObserver(()=>decorate(document)).observe(document.body,{childList:true,subtree:true});
        if (window.jQuery) jQuery.ajaxPrefilter(function(options) {
            const url = new URL(options.url || location.href, location.href);
            if (url.origin === location.origin && url.searchParams.has('_branch')) {
                options.headers = Object.assign({}, options.headers, {'X-Branch-Context': token});
            }
        });
    });
})();
</script>
@endif
@endif
