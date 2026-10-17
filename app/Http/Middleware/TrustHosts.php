<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    public function handle(\Illuminate\Http\Request $request, $next)
    {
        return parent::handle($request,function($request)use($next){
            $request->getHost(); // Reject before a controller can persist data or dispatch mail/SMS.
            return $next($request);
        });
    }
    /**
     * Get the host patterns that should be trusted.
     *
     * @return array<int, string|null>
     */
    public function hosts()
    {
        $host=parse_url((string)config('app.url'),PHP_URL_HOST);
        if(!$host)return ['(?!)'];
        $hosts=[$host];
        if($host!=='localhost' && !filter_var($host,FILTER_VALIDATE_IP) && !str_contains($host,':'))
            $hosts[]=str_starts_with($host,'www.')?substr($host,4):'www.'.$host;
        return array_map(fn($name)=>'^'.preg_quote($name,'/').'$',array_unique($hosts));
    }
}
