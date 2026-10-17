<?php
namespace App\Http\Middleware;

use Closure;

/** Deployments run through Artisan; browser requests cannot configure or migrate the server. */
class OperationalRouteGuard
{
    public function handle($request, Closure $next)
    {
        $name = (string) $request->route()?->getName();
        abort_if(str_starts_with($name, 'LaravelInstaller::') || str_starts_with($name, 'LaravelUpdater::'), 404);
        abort_if(\Illuminate\Support\Str::is(['n8n.*','generate.template*','generate.prompt.response','openai.settings'], $name), 410);
        return $next($request);
    }
}
