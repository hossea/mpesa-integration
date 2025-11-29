<?php
// app/Models/UserPaymentConfig.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPaymentConfig extends Model
{
    protected $fillable = [
        'user_id',
        'config_name',
        'type',
        'shortcode',
        'account_number',
        'description',
        'is_active',
        'is_default',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'meta' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        // When setting a config as default, unset others
        static::saving(function ($config) {
            if ($config->is_default && $config->user_id) {
                static::where('user_id', $config->user_id)
                    ->where('type', $config->type)
                    ->where('id', '!=', $config->id)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isTill(): bool
    {
        return $this->type === 'till';
    }

    public function isPaybill(): bool
    {
        return $this->type === 'paybill';
    }

    public function getFullIdentifier(): string
    {
        if ($this->isPaybill() && $this->account_number) {
            return "{$this->shortcode} (Acc: {$this->account_number})";
        }
        return $this->shortcode;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeTill($query)
    {
        return $query->where('type', 'till');
    }

    public function scopePaybill($query)
    {
        return $query->where('type', 'paybill');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
