<?php
namespace App\Routing;

use App\Services\BranchContext;
use Illuminate\Routing\UrlGenerator;

class BranchUrlGenerator extends UrlGenerator
{
    public function __construct(UrlGenerator $original)
    {
        // Preserve Laravel's request/session/signing resolvers and any configured URL formatting.
        foreach (get_object_vars($original) as $key=>$value) $this->$key = $value;
    }
    public function route($name, $parameters = [], $absolute = true)
    {
        $url = parent::route($name, $parameters, $absolute);
        $context = BranchContext::current();
        if ($context && BranchContext::businessRoute((string)$name) && !($context['grant'] && $name==='setting.index')) {
            $url = $this->appendContextQuery($url, ['_branch'=>$context['token']]);
        }
        return $url;
    }
    private function appendContextQuery(string $url, array $values): string
    {
        [$base, $fragment] = array_pad(explode('#', $url, 2), 2, null);
        [$path, $query] = array_pad(explode('?', $base, 2), 2, '');
        parse_str($query, $params);
        return $path.'?'.http_build_query(array_merge($params, $values)).($fragment !== null ? '#'.$fragment : '');
    }
}
