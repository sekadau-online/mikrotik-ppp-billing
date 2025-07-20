<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\CustomerPayment;
use Illuminate\Support\Facades\Validator;

class CustomerPaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $customer = Customer::find($request->customer_id);

        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'amount' => $request->amount,
            'paid_at' => now(),
        ]);

        $customer->balance += $request->amount;
        $customer->save();

        return response()->json([
            'success' => true,
            'message' => 'Payment processed successfully',
            'data' => [
                'payment_id' => $payment->id,
                'balance' => $customer->balance
            ]
        ]);
    }

    public function paymentHistory($customer_id)
    {
        $payments = CustomerPayment::where('customer_id', $customer_id)
            ->orderBy('paid_at', 'desc')
            ->get(['id', 'amount', 'paid_at']);

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }
}
