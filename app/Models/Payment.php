<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'ppp_user_id',
        'amount',
        'method',
        'reference',
        'date'
    ];

    public function pppUser()
    {
        return $this->belongsTo(PppUser::class);
    }
}