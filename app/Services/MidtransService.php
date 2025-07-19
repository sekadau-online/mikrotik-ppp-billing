<?php

namespace App\Services;

use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Notification; // Pastikan ini di-import
use Exception;
use Illuminate\Support\Facades\Log;

class MidtransService
{
    public function __construct()
    {
        // Set your Merchant Server Key
        Config::$serverKey = config('services.midtrans.server_key');
        // Set to Development/Sandbox Environment (default is false)
        Config::$isProduction = config('services.midtrans.is_production');
        // Set sanitization on (default is true)
        Config::$isSanitized = true;
        // Set 3DS authentication on (default is false)
        Config::$is3ds = true;
    }

    /**
     * Create a Midtrans Snap transaction.
     *
     * @param array $transactionDetails Array containing 'order_id' and 'gross_amount'.
     * @param array $customerDetails Array containing customer information.
     * @param array $itemDetails Optional array of items.
     * @param array $callbacks Optional array of custom callback URLs.
     * @return object An object with a 'token' property (Snap Token).
     * @throws Exception If Snap token generation fails.
     */
    public function createTransaction(array $transactionDetails, array $customerDetails, array $itemDetails = [], array $callbacks = [])
    {
        $params = [
            'transaction_details' => $transactionDetails,
            'customer_details' => $customerDetails,
            'item_details' => $itemDetails,
            'enabled_payments' => [
                'credit_card', 'gopay', 'shopeepay', 'bank_transfer',
                // Add other payment methods you want to enable
            ],
            // Merge custom callbacks if provided, otherwise use defaults from config
            'callbacks' => array_merge([
                'finish' => config('services.midtrans.redirect_finish'),
                'error' => config('services.midtrans.redirect_error'),
                'pending' => config('services.midtrans.redirect_pending'),
            ], $callbacks)
        ];

        try {
            $snapToken = Snap::getSnapToken($params);
            return (object)['token' => $snapToken]; // Return an object with token property
        } catch (Exception $e) {
            Log::error("Midtrans Snap Token Generation Error: " . $e->getMessage() . " - " . $e->getFile() . " on line " . $e->getLine());
            throw new Exception("Failed to create Midtrans transaction: " . $e->getMessage());
        }
    }

    /**
     * Get Midtrans notification object.
     *
     * @return Notification Midtrans Notification object.
     * @throws Exception If notification initialization fails.
     */
    public function getNotification()
    {
        try {
            $notif = new Notification();
            return $notif;
        } catch (Exception $e) {
            Log::error("Midtrans Notification Init Error: " . $e->getMessage());
            throw new Exception("Failed to process Midtrans notification: " . $e->getMessage());
        }
    }
}