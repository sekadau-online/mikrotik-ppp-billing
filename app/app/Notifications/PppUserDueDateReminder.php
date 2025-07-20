<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\PppUser; // Import PppUser model

class PppUserDueDateReminder extends Notification implements ShouldQueue
{
    use Queueable;

    protected $pppUser;
    protected $daysRemaining;

    /**
     * Create a new notification instance.
     */
    public function __construct(PppUser $pppUser, int $daysRemaining)
    {
        $this->pppUser = $pppUser;
        $this->daysRemaining = $daysRemaining;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail']; // Anda bisa menambahkan 'database' atau 'sms' jika dikonfigurasi
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = "Peringatan: Langganan Internet Anda Akan Segera Berakhir!";
        $greeting = "Halo {$this->pppUser->username},";
        $line1 = "Langganan internet Anda akan berakhir dalam {$this->daysRemaining} hari pada tanggal " . $this->pppUser->expired_at->format('d M Y') . ".";
        $actionText = "Lakukan Pembayaran Sekarang";
        $actionUrl = url('/payment/' . $this->pppUser->id); // Ganti dengan URL pembayaran aktual Anda
        $line2 = "Mohon segera lakukan pembayaran untuk menghindari gangguan layanan.";
        $salutation = "Terima kasih,\nTim Layanan Kami";

        return (new MailMessage)
                    ->subject($subject)
                    ->greeting($greeting)
                    ->line($line1)
                    ->action($actionText, $actionUrl)
                    ->line($line2)
                    ->salutation($salutation);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ppp_user_id' => $this->pppUser->id,
            'username' => $this->pppUser->username,
            'expired_at' => $this->pppUser->expired_at->format('Y-m-d H:i:s'),
            'days_remaining' => $this->daysRemaining,
            'message' => "Langganan Anda akan berakhir dalam {$this->daysRemaining} hari."
        ];
    }
}
