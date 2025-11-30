<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\ApiClient;

class ApiKeyAuth
{
    public function handle($request, Closure $next)
    {
        $key = $request->header('X-API-KEY');

        if (!$key || !ApiClient::where('api_key', $key)->where('is_active', true)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing API key'
            ], 401);
        }

        return $next($request);
    }
}
