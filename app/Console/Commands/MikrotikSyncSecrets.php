<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MikrotikService;
use App\Models\PppUser;
use Illuminate\Support\Facades\Log;

class MikrotikSyncSecrets extends Command
{
    protected $signature = 'ppp:sync-secrets';
    protected $description = 'Synchronize PPP secrets between database and Mikrotik router.';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(MikrotikService $mikrotik)
    {
        $this->info("Starting Mikrotik PPP secret synchronization...");
        Log::channel('ppp')->info("Mikrotik secret sync command started via scheduler.");

        try {
            // 1. Ambil semua PPP secrets dari Mikrotik
            $mikrotikSecrets = collect($mikrotik->getAllSecrets())->keyBy('name');
            Log::channel('ppp')->info("Fetched " . $mikrotikSecrets->count() . " secrets from Mikrotik.");

            // 2. Ambil semua PPP users dari database
            // Eager load package untuk menghindari N+1 query
            $dbUsers = PppUser::with('package')->get()->keyBy('username');
            Log::channel('ppp')->info("Fetched " . $dbUsers->count() . " users from database.");

            // 3. Iterasi Mikrotik secrets untuk identifikasi yang tidak terkelola (harus dihapus)
            foreach ($mikrotikSecrets as $secretName => $mikrotikSecret) {
                if (!$dbUsers->has($secretName)) {
                    $this->warn("Secret '{$secretName}' found on Mikrotik but not in database. Deleting from Mikrotik.");
                    $deleteResponse = $mikrotik->deleteUser($mikrotikSecret['.id']);
                    if ($deleteResponse['success'] ?? false) {
                        Log::channel('ppp')->info("Unmanaged secret deleted from Mikrotik.", ['username' => $secretName]);
                    } else {
                        Log::channel('ppp')->error("Failed to delete unmanaged secret '{$secretName}' from Mikrotik: " . ($deleteResponse['error'] ?? 'Unknown error'));
                    }
                }
            }

            // 4. Iterasi Database users untuk identifikasi yang hilang di Mikrotik atau tidak sinkron
            foreach ($dbUsers as $dbUser) {
                // Tentukan profil yang diharapkan dari database atau status user
                // Jika package_id null atau package tidak ditemukan, fallback ke default_profile
                $expectedProfile = $dbUser->package->mikrotik_profile_name ?? config('mikrotik.default_profile', 'default');
                
                if ($dbUser->status === 'suspended') {
                    $expectedProfile = 'suspend-profile'; // Override jika status suspended
                } elseif ($dbUser->status === 'pending') {
                    // Opsional: Untuk user pending, bisa beri profil khusus atau biarkan default/suspend
                    $expectedProfile = config('mikrotik.pending_profile', 'default');
                }

                if (!$mikrotikSecrets->has($dbUser->username)) {
                    // User ada di DB tapi tidak di Mikrotik (hilang/baru) -> buat ulang
                    $this->info("User '{$dbUser->username}' found in DB but not on Mikrotik. Recreating with profile '{$expectedProfile}'.");
                    Log::channel('ppp')->info("User missing on Mikrotik, attempting to recreate.", ['username' => $dbUser->username, 'expected_profile' => $expectedProfile]);

                    $createResponse = $mikrotik->createUser(
                        $dbUser->username,
                        $dbUser->password,
                        $expectedProfile,
                        $dbUser->local_address,
                        $dbUser->remote_address
                    );

                    if ($createResponse['success'] ?? false) {
                        $this->info("Recreated missing user '{$dbUser->username}' on Mikrotik.");
                        Log::channel('ppp')->info("Missing user recreated on Mikrotik.", ['username' => $dbUser->username]);
                    } else {
                        $this->error("Failed to recreate missing user '{$dbUser->username}' on Mikrotik: " . ($createResponse['error'] ?? 'Unknown error'));
                        Log::channel('ppp')->error("Failed to recreate missing user on Mikrotik.", ['username' => $dbUser->username, 'error' => $createResponse['error']]);
                    }
                } else {
                    // User ada di DB dan Mikrotik -> periksa apakah ada ketidaksesuaian
                    $mikrotikSecret = $mikrotikSecrets[$dbUser->username];

                    $isMismatch = false;
                    $changes = [];

                    // Cek profil
                    if (($mikrotikSecret['profile'] ?? null) !== $expectedProfile) {
                        $isMismatch = true;
                        $changes['profile'] = ['expected' => $expectedProfile, 'actual' => ($mikrotikSecret['profile'] ?? 'none')];
                        $this->warn("Profile mismatch for '{$dbUser->username}'. Expected: '{$expectedProfile}', Actual: '{$mikrotikSecret['profile']}'.");
                    }
                    // Cek local-address
                    if (!empty($dbUser->local_address) && ($mikrotikSecret['local-address'] ?? null) !== $dbUser->local_address) {
                        $isMismatch = true;
                        $changes['local-address'] = ['expected' => $dbUser->local_address, 'actual' => ($mikrotikSecret['local-address'] ?? 'none')];
                    }
                    // Cek remote-address
                    if (!empty($dbUser->remote_address) && ($mikrotikSecret['remote-address'] ?? null) !== $dbUser->remote_address) {
                        $isMismatch = true;
                        $changes['remote-address'] = ['expected' => $dbUser->remote_address, 'actual' => ($mikrotikSecret['remote-address'] ?? 'none')];
                    }
                    // Catatan: Membandingkan password Mikrotik langsung dengan database password Hash::make() tidak mungkin.
                    // Jika password berubah di DB, Anda harus mengirimkannya ke Mikrotik.
                    // Mikrotik API biasanya tidak mengembalikan password plain.
                    // Jadi, Anda mungkin perlu logika update password terpisah atau mempercayai password yang dikirim dari Mikrotik.
                    // Untuk sekarang, kita tidak membandingkan password secara langsung.

                    if ($isMismatch) {
                        $this->info("Correcting user '{$dbUser->username}' on Mikrotik due to mismatches: " . json_encode($changes));
                        Log::channel('ppp')->info("User mismatch on Mikrotik, attempting to update.", ['username' => $dbUser->username, 'changes' => $changes]);

                        $updateResponse = $mikrotik->updateUser(
                            $mikrotikSecret['.id'], // Gunakan .id Mikrotik untuk update
                            $dbUser->username,
                            $dbUser->password, // Kirim password dari DB
                            $expectedProfile,
                            $dbUser->local_address,
                            $dbUser->remote_address
                        );

                        if ($updateResponse['success'] ?? false) {
                            $this->info("Updated user '{$dbUser->username}' on Mikrotik to match database.");
                            Log::channel('ppp')->info("User updated on Mikrotik.", ['username' => $dbUser->username]);
                        } else {
                            $this->error("Failed to update user '{$dbUser->username}' on Mikrotik: " . ($updateResponse['error'] ?? 'Unknown error'));
                            Log::channel('ppp')->error("Failed to update user on Mikrotik.", ['username' => $dbUser->username, 'error' => $updateResponse['error']]);
                        }
                    }
                }
            }

            $this->info("Mikrotik PPP secret synchronization finished.");
            Log::channel('ppp')->info("Mikrotik secret sync command finished.");

        } catch (\Exception $e) {
            $this->error("An unexpected error occurred during sync: " . $e->getMessage());
            Log::channel('ppp')->critical("Mikrotik secret sync failed with unexpected error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }
    }
}