<?php
// app/Models/Merchant.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchant extends Model
{
    protected $fillable = [
        'name',
        'shortcode',
        'consumer_key',
        'consumer_secret',
        'passkey',
        'initiator_name',
        'security_credential',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    protected $hidden = [
        'consumer_secret',
        'passkey',
        'security_credential',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(MpesaTransaction::class);
    }

    public function isActive(): bool
    {
        return !empty($this->consumer_key) && !empty($this->consumer_secret);
    }
}
