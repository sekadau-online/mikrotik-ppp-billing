<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PppUser;
use App\Models\Package;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB; // PENTING: Tambahkan ini
use Illuminate\Support\Facades\Log; // Tambahkan ini

class PppUserSeeder extends Seeder
{
    public function run(): void
    {
        Log::info('Running PppUserSeeder...');
        // Nonaktifkan pemeriksaan foreign key sementara
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Hapus semua data user yang ada
        PppUser::truncate();

        // Aktifkan kembali pemeriksaan foreign key
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Ambil ID paket yang sudah dibuat di PackageSeeder
        $packageBusiness = Package::where('mikrotik_profile_name', 'default-profile_business')->first();
        $packageHome = Package::where('mikrotik_profile_name', 'default-profile_home')->first();
        $suspendPackage = Package::where('mikrotik_profile_name', 'suspend-profile')->first();


        // Pastikan paket-paket tersebut ditemukan
        if (!$packageBusiness || !$packageHome || !$suspendPackage) {
            Log::error('PppUserSeeder failed: PackageSeeder has not run or required Mikrotik profiles not found.');
            $this->command->error('Pastikan PackageSeeder sudah berjalan atau paket Mikrotik tidak ditemukan!');
            return;
        }

        // Contoh user 'zeus' - Aktif
        PppUser::create([
            'username' => 'zeus',
            'password' => Hash::make('secretpassword'),
            'service' => 'pppoe',
            'local_address' => '192.168.1.2',
            'remote_address' => '192.168.1.1',
            'phone' => '08123456789',
            'email' => 'zeus@example.com',
            'address' => 'Jl. Dewa Langit No. 1',
            'activated_at' => Carbon::now(),
            'expired_at' => Carbon::now()->addDays($packageBusiness->duration_days),
            'due_date' => Carbon::now()->addDays($packageBusiness->duration_days),
            'grace_period_days' => 1,
            'suspended_at' => null,
            'restored_at' => null,
            'balance' => $packageBusiness->price,
            'status' => 'active',
            'package_id' => $packageBusiness->id,
        ]);
        Log::info('Created user zeus.');

        // Contoh user 'hera' - Sudah expired dan di-suspend
        PppUser::create([
            'username' => 'hera',
            'password' => Hash::make('secretpassword'),
            'service' => 'pppoe',
            'local_address' => '192.168.1.3',
            'remote_address' => '192.168.1.1',
            'phone' => '08987654321',
            'email' => 'hera@example.com',
            'address' => 'Jl. Ratu Olympus No. 2',
            'activated_at' => Carbon::now()->subDays(60),
            'expired_at' => Carbon::now()->subDays(30),
            'due_date' => Carbon::now()->subDays(30),
            'grace_period_days' => 1,
            'suspended_at' => Carbon::now()->subDays(29),
            'restored_at' => null,
            'balance' => 0,
            'status' => 'suspended',
            'package_id' => $packageHome->id,
        ]);
        Log::info('Created user hera.');

        // Contoh user 'apollo' - Pending (belum aktif/bayar)
        PppUser::create([
            'username' => 'apollo',
            'password' => Hash::make('secretpassword'),
            'service' => 'pppoe',
            'local_address' => '192.168.1.4',
            'remote_address' => '192.168.1.1',
            'phone' => '08777777777',
            'email' => 'apollo@example.com',
            'address' => 'Jl. Dewa Cahaya No. 3',
            'activated_at' => null,
            'expired_at' => null,
            'due_date' => null,
            'grace_period_days' => 1,
            'suspended_at' => null,
            'restored_at' => null,
            'balance' => 0,
            'status' => 'pending',
            'package_id' => $packageHome->id,
        ]);
        Log::info('Created user apollo.');

        Log::info('PppUserSeeder finished.');
    }
}