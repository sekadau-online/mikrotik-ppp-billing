<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PppPackage extends Model
{
    //
        protected $fillable = [
        'name', 'code', 'speed_limit', 
        'duration_days', 'price', 'description', 'is_active'
    ];
}
