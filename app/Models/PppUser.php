<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable; // Import Notifiable trait

class PppUser extends Authenticatable
{
    use HasFactory, SoftDeletes, Notifiable; // Add Notifiable trait

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ppp_users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'password',
        'service',
        'local_address',
        'remote_address',
        'phone',
        'email',
        'address',
        'activated_at',
        'expired_at',
        'due_date',
        'grace_period_days',
        'balance',
        'status',
        'mikrotik_id',
        'package_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'activated_at' => 'datetime',
        'expired_at' => 'datetime',
        'due_date' => 'date',
        'balance' => 'decimal:2',
        'grace_period_days' => 'integer',
        'suspended_at' => 'datetime',
        'restored_at' => 'datetime',
    ];

    /**
     * Get the package that the PPP user belongs to.
     */
    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Get the payments for the PPP user.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
