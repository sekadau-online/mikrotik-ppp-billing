<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MidtransService;
use App\Models\PppUser; // Import model PppUser
use App\Models\Package; // Import model Package
use App\Models\Payment; // Import model Payment
use Exception;
use Carbon\Carbon; // Untuk manipulasi tanggal
use Illuminate\Support\Facades\Log;

class CustomerPaymentController extends Controller
{
    protected $midtransService;

    public function __construct(MidtransService $midtransService)
    {
        $this->midtransService = $midtransService;
    }

    public function index()
    {
        // Ini untuk menampilkan halaman frontend, pastikan path view benar
        return view('customer.payment');
    }

    // --- API Endpoints for Frontend ---

    public function getUserByUsername($username)
    {
        try {
            $user = PppUser::where('username', $username)->with('package')->first();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Pelanggan tidak ditemukan.'], 404);
            }
            return response()->json(['success' => true, 'data' => $user]);
        } catch (Exception $e) {
            Log::error("Error in getUserByUsername: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan saat mencari pelanggan.'], 500);
        }
    }

    public function getUserById($id)
    {
        try {
            $user = PppUser::with('package')->find($id);
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Pelanggan tidak ditemukan.'], 404);
            }
            return response()->json(['success' => true, 'data' => $user]);
        } catch (Exception $e) {
            Log::error("Error in getUserById: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan saat mencari pelanggan.'], 500);
        }
    }

    public function getActivePackages()
    {
        try {
            $packages = Package::where('is_active', true)->get(); // Asumsi kolom 'is_active'
            return response()->json(['success' => true, 'data' => $packages]);
        } catch (Exception $e) {
            Log::error("Error in getActivePackages: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal memuat daftar paket.'], 500);
        }
    }

    public function getPaymentHistory($userId)
    {
        try {
            $payments = Payment::where('user_id', $userId)
                               ->orderBy('created_at', 'desc')
                               ->get();
            return response()->json(['success' => true, 'data' => $payments]);
        } catch (Exception $e) {
            Log::error("Error in getPaymentHistory: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal memuat riwayat pembayaran.'], 500);
        }
    }

    public function processPayment(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:ppp_users,id',
            'amount' => 'required|numeric|min:10000',
            'description' => 'nullable|string|max:255',
        ]);

        $user = PppUser::find($request->user_id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Pengguna tidak ditemukan.'], 404);
        }

        $orderId = 'TRX-' . $user->id . '-' . time() . '-' . uniqid();

        $transactionDetails = [
            'order_id' => $orderId,
            'gross_amount' => (int) $request->amount,
        ];

        $customerDetails = [
            'first_name' => $user->username,
            // 'email' => $user->email ?? 'no-email@example.com', // Uncomment if user has email
            // 'phone' => $user->phone_number ?? '08123456789', // Uncomment if user has phone number
        ];

        $itemDetails = [
            [
                'id' => 'payment-item-' . time(),
                'price' => (int) $request->amount,
                'quantity' => 1,
                'name' => $request->description ?: 'Pembayaran Transaksi Umum'
            ]
        ];

        try {
            // Callback URLs for Midtrans to redirect user after payment
            $callbacks = [
                'finish' => config('services.midtrans.redirect_finish') . '?order_id=' . $orderId,
                'error' => config('services.midtrans.redirect_error') . '?order_id=' . $orderId,
                'pending' => config('services.midtrans.redirect_pending') . '?order_id=' . $orderId,
            ];

            $snap = $this->midtransService->createTransaction($transactionDetails, $customerDetails, $itemDetails, $callbacks);

            // Save payment record to your database with 'pending' status
            Payment::create([
                'user_id' => $user->id,
                'order_id' => $orderId,
                'amount' => $request->amount,
                'description' => $request->description,
                'status' => 'pending',
                'snap_token' => $snap->token,
            ]);

            return response()->json(['success' => true, 'snap_token' => $snap->token]);

        } catch (Exception $e) {
            Log::error("Error processing payment: " . $e->getMessage() . " - " . $e->getFile() . " on line " . $e->getLine());
            return response()->json(['success' => false, 'message' => 'Gagal membuat transaksi Midtrans: ' . $e->getMessage()], 500);
        }
    }

    public function handleMidtransNotification(Request $request)
    {
        try {
            $notif = $this->midtransService->getNotification();

            $transactionStatus = $notif->transaction_status;
            $orderId = $notif->order_id;
            $fraudStatus = $notif->fraud_status;

            Log::info("Midtrans Notification received for Order ID: " . $orderId . ", Status: " . $transactionStatus . ", Fraud: " . $fraudStatus);

            $payment = Payment::where('order_id', $orderId)->first();

            if (!$payment) {
                Log::warning("Midtrans Notification: Order ID " . $orderId . " not found in payments table.");
                return response()->json(['message' => 'Payment not found'], 404);
            }

            // Avoid double processing
            if ($payment->status === 'success' || $payment->status === 'settlement') {
                Log::info("Midtrans Notification: Order ID " . $orderId . " already processed with status " . $payment->status);
                return response()->json(['message' => 'Already processed'], 200);
            }

            // Determine new status
            $newStatus = 'pending';
            if ($transactionStatus == 'capture') {
                $newStatus = ($fraudStatus == 'challenge') ? 'challenge' : 'success';
            } elseif ($transactionStatus == 'settlement') {
                $newStatus = 'success';
            } elseif ($transactionStatus == 'pending') {
                $newStatus = 'pending';
            } elseif ($transactionStatus == 'deny') {
                $newStatus = 'failed';
            } elseif ($transactionStatus == 'expire') {
                $newStatus = 'expired';
            } elseif ($transactionStatus == 'cancel') {
                $newStatus = 'cancelled';
            }

            $payment->status = $newStatus;
            $payment->payment_gateway_response = json_encode($notif->getResponse()); // Save full response
            $payment->save();

            // Apply business logic only if payment is now successful
            if ($payment->status === 'success') {
                $user = PppUser::find($payment->user_id);
                if ($user) {
                    if (str_contains(strtolower($payment->description), 'paket')) {
                        // Logic for package renewal/upgrade
                        // This part needs to be robust. Best to store package_id in payment.
                        $currentPackage = $user->package; // Load user's current package
                        if ($currentPackage) {
                            if ($payment->amount == $currentPackage->price) {
                                // Renew current package
                                $newExpiredDate = $user->expired_at ?
                                    Carbon::parse($user->expired_at)->addDays($currentPackage->duration_days) :
                                    Carbon::now()->addDays($currentPackage->duration_days);
                                
                                $user->expired_at = $newExpiredDate;
                                $user->status = 'active';
                                $user->save();
                                Log::info("User " . $user->username . " package " . $currentPackage->name . " extended until " . $user->expired_at->format('Y-m-d'));
                            } else {
                                Log::warning("Payment amount mismatch for package renewal for user " . $user->username . ". Further action needed.");
                                // Handle cases where amount is different (e.g., package upgrade)
                                // You might need to parse description or store a package_id in the payment record.
                            }
                        } else {
                            Log::info("User " . $user->username . " purchased package but had no prior package. Manual package assignment might be needed.");
                            // User bought a package but had no package, assign the package (requires identifying which package was bought)
                        }
                    } else {
                        // Logic for balance deposit
                        $user->balance += $payment->amount;
                        $user->save();
                        Log::info("User " . $user->username . " deposited Rp" . $payment->amount . ". New balance: Rp" . $user->balance);
                    }
                } else {
                    Log::error("User with ID " . $payment->user_id . " not found for payment ID " . $payment->id . " after successful payment.");
                }
            }
            return response()->json(['message' => 'Notification handled successfully'], 200);

        } catch (Exception $e) {
            Log::error("Error handling Midtrans notification: " . $e->getMessage() . " - " . $e->getFile() . " on line " . $e->getLine());
            return response()->json(['message' => 'Error processing notification'], 500);
        }
    }
}