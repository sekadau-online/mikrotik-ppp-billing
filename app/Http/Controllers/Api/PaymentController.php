<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Customer;
use App\Models\Payment;
use Carbon\Carbon;

class PaymentController extends Controller
{
    // Proses awal pembayaran
    public function processPayment(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:1000',
        ]);

        $customer = Customer::find($request->customer_id);

        $orderId = 'INV-' . time() . '-' . rand(100, 999);

        // Simpan dulu ke DB
        $payment = Payment::create([
            'customer_id' => $customer->id,
            'order_id' => $orderId,
            'amount' => $request->amount,
            'status' => 'pending',
        ]);

        // Kirim ke Midtrans
        $midtrans = Http::withBasicAuth(env('MIDTRANS_SERVER_KEY'), '')
            ->post('https://app.sandbox.midtrans.com/snap/v1/transactions', [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => $request->amount,
                ],
                'customer_details' => [
                    'first_name' => $customer->username,
                    'email' => $customer->email,
                ]
            ]);

        if (!$midtrans->successful()) {
            Log::error('Midtrans Error', ['response' => $midtrans->json()]);
            return response()->json(['success' => false, 'message' => 'Gagal membuat transaksi'], 500);
        }

        $snap = $midtrans->json();

        return response()->json([
            'success' => true,
            'message' => 'Transaksi berhasil dibuat',
            'payment_url' => $snap['redirect_url'],
            'token' => $snap['token'],
            'order_id' => $orderId,
        ]);
    }

    // Callback Midtrans
    public function handleMidtransCallback(Request $request)
    {
        $serverKey = env('MIDTRANS_SERVER_KEY');
        $signature = hash('sha512',
            $request->order_id .
            $request->status_code .
            $request->gross_amount .
            $serverKey
        );

        if ($signature !== $request->signature_key) {
            Log::warning('Midtrans callback signature mismatch');
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $payment = Payment::where('order_id', $request->order_id)->first();
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $payment->status = $request->transaction_status;
        $payment->paid_at = in_array($request->transaction_status, ['settlement', 'capture']) ? now() : null;
        $payment->save();

        Log::info('Midtrans payment updated', $payment->toArray());

        return response()->json(['message' => 'Callback received']);
    }

    // Riwayat pembayaran
    public function paymentHistory($userId)
    {
        $payments = Payment::where('customer_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }
}
