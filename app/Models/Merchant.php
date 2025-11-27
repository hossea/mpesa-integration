<?php
namespace App\Models;


use Illuminate\Database\Eloquent\Model;


class Merchant extends Model
{
protected $guarded = [];
protected $casts = [
'meta' => 'array'
];
}
