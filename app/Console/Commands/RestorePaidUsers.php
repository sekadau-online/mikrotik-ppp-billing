<?php

namespace App\Console\Commands;

use App\Models\PppUser;
use App\Jobs\ProcessUserRestoration; // Import Job restorasi
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RestorePaidUsers extends Command
{
    protected $signature = 'ppp:check-restore'; // Nama yang lebih jelas
    protected $description = 'Checks for suspended users who can be restored due to sufficient balance.';

    public function handle()
    {
        $this->info("Memulai proses cek dan restorasi pengguna PPP...");

        // Ambil semua user suspended yang memiliki paket
        $usersToProcess = PppUser::with('package')
            ->where('status', 'suspended')
            ->whereHas('package') // Pastikan user punya paket yang terhubung
            ->get();

        if ($usersToProcess->isEmpty()) {
            $this->info("Tidak ada pengguna suspended yang perlu diproses.");
            return;
        }

        $dispatchedCount = 0;

        foreach ($usersToProcess as $user) {
            // Pastikan user memiliki paket dan harga paket valid
            if (!$user->package || !isset($user->package->price)) {
                Log::channel('ppp')->warning("User {$user->username} suspended but has no valid package or package price. Skipping restoration check.");
                continue;
            }

            $packagePrice = (float) $user->package->price;
            $balance = (float) $user->balance;

            if ($balance >= $packagePrice) {
                // Dispatch Job untuk proses restorasi
                ProcessUserRestoration::dispatch($user->id);
                $this->info("Job restorasi dikirim untuk: {$user->username}");
                $dispatchedCount++;
            } else {
                Log::channel('ppp')->info("User {$user->username} suspended but balance ({$balance}) is not yet sufficient for package price ({$packagePrice}).");
            }
        }

        $this->info("Selesai. Total job restorasi yang dikirim: {$dispatchedCount}.");
    }
}