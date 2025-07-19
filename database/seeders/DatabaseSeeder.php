<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log; // Tambahkan ini

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Log::info('Running DatabaseSeeder...');
        $this->call([
            PackageSeeder::class, // PENTING: Panggil PackageSeeder terlebih dahulu
            PppUserSeeder::class,   // Kemudian PppUserSeeder
            // Tambahkan seeder lain di sini jika ada, misal:
            // UserSeeder::class, // Jika ada seeder untuk model User (admin, dsb.)
        ]);
        Log::info('DatabaseSeeder finished.');
    }
}