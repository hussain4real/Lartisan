<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

class NormalizeMarketplaceProximityQuery
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $normalized = [];

        foreach (['near_lat', 'near_lng'] as $key) {
            $value = $request->query($key);

            if (! is_string($value) || ! is_numeric($value)) {
                continue;
            }

            $normalized[$key] = number_format(round((float) $value, 3), 3, '.', '');
        }

        if ($normalized !== []) {
            $request->query->add($normalized);

            $queryString = Arr::query($request->query->all());

            $request->server->set('QUERY_STRING', $queryString);
            $request->server->set(
                'REQUEST_URI',
                $request->getBaseUrl().$request->getPathInfo().($queryString === '' ? '' : '?'.$queryString),
            );
        }

        return $next($request);
    }
}
