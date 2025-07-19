<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code', // Dari migrasi packages Anda
        'speed_limit', // Dari migrasi packages Anda
        'download_speed',
        'upload_speed',
        'duration_days',
        'price',
        'description',
        'features',
        'is_active',
        'sort_order',
        'mikrotik_profile_name', // <<< PASTIKAN INI ADA DI KOLOM TABLE PACKAGES
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array', // Jika Anda ingin fitur di-cast sebagai array/JSON
        'is_active' => 'boolean',
    ];

    // Jika ada user yang terhubung ke paket ini (opsional, untuk relasi HasMany)
    public function pppUsers()
    {
        return $this->hasMany(PppUser::class);
    }
}