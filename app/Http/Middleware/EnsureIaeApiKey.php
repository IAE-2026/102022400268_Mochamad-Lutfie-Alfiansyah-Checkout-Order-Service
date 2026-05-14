<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIaeApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = (string) config('services.iae.api_key', '102022400268');
        $providedKey = (string) $request->header('X-IAE-KEY', '');

        if ($providedKey === '' || ! hash_equals($expectedKey, $providedKey)) {
            return ApiResponse::error('Missing or invalid X-IAE-KEY header', null, 401);
        }

        return $next($request);
    }
}
