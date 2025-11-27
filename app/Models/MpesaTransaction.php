<?php
namespace App\Models;


use Illuminate\Database\Eloquent\Model;


class MpesaTransaction extends Model
{
protected $guarded = [];


protected $casts = [
'request_payload' => 'array',
'response_payload' => 'array',
'callback_payload' => 'array',
];
}
