<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PppUser;
use App\Models\Package;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PppUserSeeder extends Seeder
{
    public function run(): void
    {
        Log::info('Running PppUserSeeder...');
        
        // Nonaktifkan pemeriksaan foreign key sementara
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        PppUser::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Ambil paket yang diperlukan
        $packageBusiness = Package::where('mikrotik_profile_name', 'default-profile_business')->first();
        $packageHome = Package::where('mikrotik_profile_name', 'default-profile_home')->first();
        $suspendPackage = Package::where('mikrotik_profile_name', 'suspend-profile')->first();

        if (!$packageBusiness || !$packageHome || !$suspendPackage) {
            Log::error('Required packages not found!');
            $this->command->error('Pastikan PackageSeeder sudah dijalankan terlebih dahulu!');
            return;
        }

        // Data contoh user
        $users = [
            [
                'username' => 'zeus',
                'password' => 'secretpassword', // Password plaintext
                'service' => 'pppoe',
                'local_address' => '192.168.1.1',
                'remote_address' => '192.168.1.2',
                'phone' => '08123456789',
                'email' => 'zeus@example.com',
                'address' => 'Jl. Dewa Langit No. 1',
                'activated_at' => Carbon::now(),
                'expired_at' => Carbon::now()->addDays($packageBusiness->duration_days),
                'due_date' => Carbon::now()->addDays($packageBusiness->duration_days),
                'grace_period_days' => 1,
                'status' => 'active',
                'package_id' => $packageBusiness->id,
                'balance' => $packageBusiness->price
            ],
            [
                'username' => 'hera',
                'password' => 'secretpassword',
                'service' => 'pppoe',
                'local_address' => '192.168.1.1',
                'remote_address' => '192.168.1.3',
                'phone' => '08987654321',
                'email' => 'hera@example.com',
                'address' => 'Jl. Ratu Olympus No. 2',
                'activated_at' => Carbon::now()->subDays(60),
                'expired_at' => Carbon::now()->subDays(30),
                'due_date' => Carbon::now()->subDays(30),
                'grace_period_days' => 1,
                'suspended_at' => Carbon::now()->subDays(29),
                'status' => 'suspended',
                'package_id' => $packageHome->id,
                'balance' => 0
            ],
            [
                'username' => 'apollo',
                'password' => 'secretpassword',
                'service' => 'pppoe',
                'local_address' => '192.168.1.1',
                'remote_address' => '192.168.1.4',
                'phone' => '08777777777',
                'email' => 'apollo@example.com',
                'address' => 'Jl. Dewa Cahaya No. 3',
                'status' => 'pending',
                'package_id' => $packageHome->id,
                'balance' => 0
            ]
        ];

        foreach ($users as $user) {
            PppUser::create($user);
            Log::info("Created user {$user['username']}");
        }

        Log::info('PppUserSeeder finished successfully.');
        $this->command->info('Seeder PPP User berhasil dijalankan!');
    }
}