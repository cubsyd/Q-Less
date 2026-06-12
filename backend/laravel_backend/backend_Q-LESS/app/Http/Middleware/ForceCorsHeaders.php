<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ForceCorsHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = (string) $request->headers->get('Origin', '');

        if ($request->isMethod('OPTIONS')) {
            return $this->withCorsHeaders(response('', 204), $origin);
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $response = app(ExceptionHandler::class)->render($request, $exception);
        }

        return $this->withCorsHeaders($response, $origin);
    }

    private function withCorsHeaders(Response $response, string $origin): Response
    {
        if (!$this->isAllowedOrigin($origin)) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Vary', 'Origin');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }

    private function isAllowedOrigin(string $origin): bool
    {
        if ($origin === '') {
            return false;
        }

        $configuredOrigins = array_filter(array_map(
            'trim',
            explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', '')))
        ));

        if (in_array($origin, $configuredOrigins, true)) {
            return true;
        }

        $host = parse_url($origin, PHP_URL_HOST);

        return is_string($host)
            && (str_ends_with($host, '.up.railway.app') || str_ends_with($host, '.railway.app'));
    }
}
