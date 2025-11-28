<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ApiClient;

class ApiKeyAuth
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('X-API-KEY') ?? $request->get('api_key');

        if (!$key) {
            return response()->json(['message' => 'API key required'], 401);
        }

        $client = ApiClient::where('api_key', $key)->first();

        if (!$client) {
            return response()->json(['message' => 'Invalid API key'], 403);
        }

        $request->merge(['api_client' => $client]);

        return $next($request);
    }
}
