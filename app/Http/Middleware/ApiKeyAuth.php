<?php


namespace App\Http\Middleware;


use Closure;
use App\Models\ApiClient;


class ApiKeyAuth
{
public function handle($request, Closure $next)
{
$key = $request->header('X-API-KEY') ?? $request->get('api_key');
if (!$key) {
return response()->json(['message' => 'API key required'], 401);
}
$client = ApiClient::where('api_key', $key)->first();
if (!$client) {
return response()->json(['message' => 'Invalid API key'], 403);
}
// optionally check allowed domains / rate limit
$request->merge(['api_client' => $client]);
return $next($request);
}
}
