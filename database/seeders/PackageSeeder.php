<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Package;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // Tambahkan ini

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        Log::info('Running PackageSeeder...');
        // Nonaktifkan pemeriksaan foreign key sementara untuk operasi truncate
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Hapus semua data paket yang ada untuk memastikan kondisi bersih
        Package::truncate();

        // Aktifkan kembali pemeriksaan foreign key
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 1. Profil Default Suspend Mikrotik
        Package::create([
            'name' => 'SYSTEM_SUSPENDED_PROFILE', // Nama internal unik
            'code' => 'SUSPEND', // Contoh code
            'speed_limit' => '0/0',
            'download_speed' => 0,
            'upload_speed' => 0,
            'duration_days' => 0,
            'price' => 0,
            'description' => 'Profil Mikrotik untuk pengguna yang ditangguhkan.',
            'features' => json_encode(['no_internet']),
            'is_active' => false, // Tidak aktif untuk dibeli user
            'sort_order' => 999,
            'mikrotik_profile_name' => 'suspend-profile', // NAMA PERSIS PROFIL DI MIKROTIK
        ]);
        Log::info('Created SYSTEM_SUSPENDED_PROFILE package.');

        // 2. Contoh Paket Umum (untuk user aktif)
        Package::create([
            'name' => 'Paket Business 10Mbps',
            'code' => 'BIZ10M',
            'speed_limit' => '10M/5M',
            'download_speed' => 10000, // dalam kbps
            'upload_speed' => 5000,   // dalam kbps
            'duration_days' => 30,
            'price' => 150000,
            'description' => 'Paket internet cepat untuk bisnis.',
            'features' => json_encode(['unlimited_data', 'static_ip']),
            'is_active' => true,
            'sort_order' => 10,
            'mikrotik_profile_name' => 'default-profile_business', // NAMA PERSIS PROFIL DI MIKROTIK
        ]);
        Log::info('Created Paket Business 10Mbps package.');

        Package::create([
            'name' => 'Paket Home 5Mbps',
            'code' => 'HOME5M',
            'speed_limit' => '5M/2M',
            'download_speed' => 5000,
            'upload_speed' => 2000,
            'duration_days' => 30,
            'price' => 100000,
            'description' => 'Paket internet untuk penggunaan rumah tangga.',
            'features' => json_encode(['unlimited_data']),
            'is_active' => true,
            'sort_order' => 20,
            'mikrotik_profile_name' => 'default-profile_home', // NAMA PERSIS PROFIL DI MIKROTIK
        ]);
        Log::info('Created Paket Home 5Mbps package.');

        Log::info('PackageSeeder finished.');
    }
}