<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'packages';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'speed_limit',
        'download_speed',
        'upload_speed',
        'duration_days',
        'price',
        'description',
        'features',
        'is_active',
        'sort_order',
        'mikrotik_profile_name',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'download_speed' => 'integer',
        'upload_speed' => 'integer',
        'duration_days' => 'integer',
        'price' => 'decimal:2',
        'features' => 'array', // Cast JSON column to array
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the PPP users for the package.
     */
    public function pppUsers()
    {
        return $this->hasMany(PppUser::class, 'package_id');
    }
}
