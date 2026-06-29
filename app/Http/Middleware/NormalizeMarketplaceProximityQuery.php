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
        $shouldRedirect = false;

        foreach (['near_lat', 'near_lng'] as $key) {
            $value = $request->query($key);

            if (! is_string($value) || ! is_numeric($value)) {
                continue;
            }

            $coordinate = (float) $value;

            if (! $this->isCoordinateWithinBounds($key, $coordinate)) {
                continue;
            }

            $normalized[$key] = number_format(round($coordinate, 3), 3, '.', '');
            $shouldRedirect = $shouldRedirect || $normalized[$key] !== $value;
        }

        if ($normalized !== []) {
            $request->query->add($normalized);

            $queryString = Arr::query($request->query->all());

            if ($shouldRedirect) {
                return redirect()->to($request->url().($queryString === '' ? '' : '?'.$queryString));
            }

            $request->server->set('QUERY_STRING', $queryString);
            $request->server->set(
                'REQUEST_URI',
                $request->getBaseUrl().$request->getPathInfo().($queryString === '' ? '' : '?'.$queryString),
            );
        }

        return $next($request);
    }

    private function isCoordinateWithinBounds(string $key, float $coordinate): bool
    {
        return match ($key) {
            'near_lat' => $coordinate >= -90.0 && $coordinate <= 90.0,
            'near_lng' => $coordinate >= -180.0 && $coordinate <= 180.0,
            default => false,
        };
    }
}
