<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Merchant extends Model
{
protected $guarded = [];

use HasFactory;
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
'meta' => 'array'
];

 public function transactions()
    {
        return $this->hasMany(MpesaTransaction::class);
    }
}
