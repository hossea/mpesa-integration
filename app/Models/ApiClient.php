<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class ApiClient extends Model
{
protected $guarded = [];

use HasFactory;
    protected $fillable = [
        'name',
        'api_key',
        'allowed_ips',
        'is_active',
    ];
    protected $casts = [
        'allowed_ips' => 'array',
        'is_active' => 'boolean',
    ];
    public static function generateApiKey(): string
    {
        return 'mpesa_' . bin2hex(random_bytes(32));
    }
}
