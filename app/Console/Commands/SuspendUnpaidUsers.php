<?php

namespace App\Console\Commands;

use App\Models\PppUser;
use App\Models\Package; // Pastikan ini di-import
use App\Jobs\ProcessUserSuspension; // Import Job suspensi
use App\Jobs\SendDueDateNotification; // Import Job notifikasi
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon; // Pastikan ini di-import

class SuspendUnpaidUsers extends Command
{
    protected $signature = 'ppp:process-dues'; // Nama yang lebih umum
    protected $description = 'Checks for overdue users, sends notifications, and suspends expired users.';

    public function handle()
    {
        $this->info("Memulai proses cek jatuh tempo dan suspensi pengguna PPP...");

        // --- Bagian 1: Kirim Notifikasi Jatuh Tempo (Grace Period) ---
        // Cari user yang statusnya 'active' atau 'grace_period'
        // dan due_date-nya adalah hari ini
        $usersForDueDateNotification = PppUser::with('package')
            ->whereIn('status', ['active', 'grace_period'])
            ->whereDate('due_date', now()) // due_date adalah hari ini
            ->get();

        foreach ($usersForDueDateNotification as $user) {
            // Pastikan user belum berstatus grace_period (untuk menghindari notifikasi berulang di hari yang sama)
            if ($user->status === 'active') {
                // Opsional: Ubah status user menjadi 'grace_period' di database
                $user->update(['status' => 'grace_period']);
                Log::channel('ppp')->info("User {$user->username} entered grace period.");
            }
            // Dispatch Job untuk mengirim notifikasi
            SendDueDateNotification::dispatch($user->id, 'due_date_reminder');
            $this->info("Notifikasi jatuh tempo dikirim untuk: {$user->username}");
        }

        // --- Bagian 2: Suspend User yang Sudah Expired ---
        // Cari user yang statusnya 'active' atau 'grace_period'
        // dan expired_at-nya adalah hari ini atau sudah lewat
        // dan saldo tidak cukup untuk perpanjangan otomatis (jika ada fitur itu)
        $usersToSuspend = PppUser::with('package')
            ->whereIn('status', ['active', 'grace_period'])
            ->whereDate('expired_at', '<=', now()) // expired_at adalah hari ini atau sudah lewat
            ->where(function ($query) {
                // Jika ada fitur auto-renewal dari saldo, cek di sini
                // Contoh: User tidak punya paket atau saldo kurang dari harga paket
                $query->whereNull('package_id') // User tanpa paket
                      ->orWhereDoesntHave('package') // User tanpa relasi paket
                      ->orWhereRaw('ppp_users.balance < packages.price'); // Saldo kurang dari harga paket
            })
            ->get();

        foreach ($usersToSuspend as $user) {
            // Dispatch Job untuk proses suspensi
            ProcessUserSuspension::dispatch($user->id);
            $this->info("Job suspensi dikirim untuk: {$user->username}");
        }

        $this->info("Proses cek jatuh tempo dan suspensi selesai.");
    }
}