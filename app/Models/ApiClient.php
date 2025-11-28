<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiClient extends Model
{
    protected $fillable = [
        'name',
        'api_key',
        'allowed_domains',
        'allowed_ips',
        'is_active',
    ];

    protected $casts = [
        'allowed_domains' => 'array',
        'allowed_ips' => 'array',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->api_key)) {
                $model->api_key = 'mpesa_' . Str::random(40);
            }
        });
    }

    public function isAllowedDomain(string $domain): bool
    {
        if (empty($this->allowed_domains)) {
            return true;
        }

        foreach ($this->allowed_domains as $allowedDomain) {
            if (Str::is($allowedDomain, $domain)) {
                return true;
            }
        }

        return false;
    }

    public function isAllowedIp(string $ip): bool
    {
        if (empty($this->allowed_ips)) {
            return true;
        }

        return in_array($ip, $this->allowed_ips);
    }
}
