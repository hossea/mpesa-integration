<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MpesaTokenService
{
public function __construct(protected $baseUrl, protected $consumerKey, protected $consumerSecret)
{
}
public function getToken(): string
{
$cacheKey = 'mpesa_token_' . md5($this->consumerKey);
return Cache::remember($cacheKey, 55 * 60, function () {
$url = $this->baseUrl . '/oauth/v1/generate?grant_type=client_credentials';
$credentials = base64_encode($this->consumerKey . ':' . $this->consumerSecret);
$res = Http::withHeaders(['Authorization' => 'Basic ' . $credentials])->get($url);
if ($res->ok() && isset($res['access_token'])) {
return $res['access_token'];
}
throw new \Exception('Unable to fetch access token: ' . $res->body());
});
}
}
