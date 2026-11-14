<?php

namespace App\Http\Middleware;

use App\Http\Resources\ErrorResource;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnsureAuthToken
{
    public function handle(Request $request, Closure $next): JsonResponse
    {
        $token = $request->bearerToken();
        $expectedToken = config('services.api_auth_token');
        if (!$token || !$expectedToken || !hash_equals($expectedToken, $token)) {
            return (new ErrorResource('Invalid token', 403))->response();
        }
        return $next($request);
    }
}
