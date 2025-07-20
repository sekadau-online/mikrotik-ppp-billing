<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Notification; // Assuming you have Midtrans PHP library installed
use Exception; // Import Exception class

class MidtransService
{
    public function __construct()
    {
        // Set your Midtrans server key
        Config::$serverKey = config('services.midtrans.server_key');
        // Set to true for production, false for sandbox
        Config::$isProduction = config('services.midtrans.is_production');
        // Set sanitization on (default)
        Config::$isSanitized = true;
        // Set 3DS authentication for credit card (default)
        Config::$is3ds = true;
    }

    /**
     * Create a Midtrans Snap transaction.
     *
     * @param array $transactionDetails Array containing 'order_id' and 'gross_amount'
     * @param array $customerDetails Array of customer information
     * @param array $itemDetails Array of items being purchased
     * @param array $callbacks Array of callback URLs (finish, error, pending)
     * @return object Midtrans Snap object (containing token)
     * @throws \Exception
     */
    public function createTransaction(
        array $transactionDetails,
        array $customerDetails,
        array $itemDetails,
        array $callbacks
    ): object {
        $params = [
            'transaction_details' => $transactionDetails,
            'item_details' => $itemDetails,
            'customer_details' => $customerDetails,
            'callbacks' => $callbacks,
            // 'enabled_payments' => ['gopay', 'permata_va', 'bca_va'], // Optional: specific payment methods
        ];

        try {
            // Use Snap::createTransaction which returns a SnapToken object
            $snap = Snap::createTransaction($params);
            Log::info("Midtrans Snap token generated for Order ID: {$transactionDetails['order_id']}");
            return $snap;
        } catch (Exception $e) {
            Log::error("Failed to create Midtrans Snap Token for Order ID {$transactionDetails['order_id']}: " . $e->getMessage());
            throw new Exception("Failed to create Midtrans transaction: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get and verify Midtrans notification.
     * This method automatically reads from php://input and verifies the signature.
     *
     * @return Notification Midtrans Notification object
     * @throws \Exception If notification verification fails
     */
    public function getNotification(): Notification
    {
        try {
            $notif = new Notification(); // This will automatically read the input and verify the signature

            Log::info("Midtrans notification received and verified for Order ID: {$notif->order_id}");
            return $notif;
        } catch (Exception $e) {
            Log::error("Failed to get or verify Midtrans notification: " . $e->getMessage());
            throw new Exception("Failed to get or verify Midtrans notification: " . $e->getMessage(), 0, $e);
        }
    }
}
