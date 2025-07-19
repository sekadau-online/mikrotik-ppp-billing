<?php

namespace App\Jobs;

use App\Models\PppUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
// use App\Notifications\DueDateReminder; // Jika Anda menggunakan Laravel Notifications
// use Illuminate\Support\Facades\Notification; // Jika Anda menggunakan Laravel Notifications

class SendDueDateNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $notificationType; // 'due_date_reminder', 'payment_success', etc.

    public function __construct(int $userId, string $notificationType)
    {
        $this->userId = $userId;
        $this->notificationType = $notificationType;
    }

    public function handle()
    {
        $user = PppUser::find($this->userId);

        if (!$user) {
            Log::channel('ppp')->warning("SendDueDateNotification Job: User ID {$this->userId} not found.");
            return;
        }

        try {
            // Logika pengiriman notifikasi
            if ($this->notificationType === 'due_date_reminder') {
                // Contoh: Kirim email/SMS
                // Notification::send($user, new DueDateReminder($user)); // Jika pakai Laravel Notifications
                Log::channel('ppp')->info("Sending due date reminder to user {$user->username}.");
                // Implementasi kirim SMS/email langsung di sini atau panggil service lain
                // Contoh sederhana:
                // sendSms($user->phone, "Halo {$user->username}, tagihan Anda akan jatuh tempo pada {$user->expired_at->format('d M Y')}. Segera lakukan pembayaran.");

            } elseif ($this->notificationType === 'payment_success') {
                Log::channel('ppp')->info("Sending payment success notification to user {$user->username}.");
                // sendSms($user->phone, "Pembayaran Anda berhasil. Layanan aktif kembali.");
            }
            // Tambahkan tipe notifikasi lain jika diperlukan

        } catch (\Exception $e) {
            Log::channel('ppp')->error("Failed to send notification to user {$user->username}: " . $e->getMessage(), [
                'user_id' => $user->id,
                'notification_type' => $this->notificationType,
                'error' => $e->getMessage()
            ]);
            throw $e; // Re-throw agar Job bisa di-retry
        }
    }
}