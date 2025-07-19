<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PppUser;
use App\Models\Package;
use App\Services\MikrotikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PppUserController extends Controller
{
    protected $mikrotik;

    public function __construct(MikrotikService $mikrotik)
    {
        $this->mikrotik = $mikrotik;
    }

    public function index()
    {
        try {
            $users = PppUser::with('package')->get();
            return response()->json(['success' => true, 'data' => $users]);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }
// fiture search by username or id
public function showByUsername($username)
    {
        try {
            $user = PppUser::where('username', $username)
                ->with('package')
                ->firstOrFail();
                
            return response()->json([
                'success' => true,
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function showById($id)
    {
        try {
            $user = PppUser::with('package')
                ->findOrFail($id);
                
            return response()->json([
                'success' => true,
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

// end fiture search by username or id
    // Add new PPP user

public function store(Request $request)
{
    DB::beginTransaction();

    try {
        // Validate input first
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|unique:ppp_users,username|max:50',
            'password' => 'required|string|min:6|max:50',
            'package_id' => 'nullable|exists:packages,id',
            'local_address' => 'required|ip',
            'remote_address' => 'required|ip|unique:ppp_users,remote_address',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:100',
            'address' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get validated data
        $data = $validator->validated();

        // Get package if provided
        $package = isset($data['package_id']) 
            ? Package::find($data['package_id'])
            : null;

        // Prepare Mikrotik data
        $mikrotikData = [
            'username' => $data['username'],
            'password' => $data['password'],
            'local_address' => $data['local_address'],
            'remote_address' => $data['remote_address'],
            'service' => 'pppoe', // Default value
            'profile' => $package 
                ? 'default-profile_'.$package->code 
                : 'default-profile'
        ];

        // Call MikrotikService
        $mikrotikResponse = $this->mikrotik->addUser($mikrotikData);

        if (!$mikrotikResponse['success']) {
            throw new \Exception($mikrotikResponse['error']);
        }

        // Create PPP user in database
        $pppUser = PppUser::create([
            'username' => $data['username'],
            'password' => $data['password'],
            'service' => 'pppoe',
            'profile' => $mikrotikData['profile'],
            'package_id' => $package?->id,
            'local_address' => $data['local_address'],
            'remote_address' => $data['remote_address'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'address' => $data['address'],
            'activated_at' => now(),
            'expired_at' => $package ? now()->addDays($package->duration_days) : null,
            'due_date' => $package ? now()->addDays($package->duration_days) : null,
            'balance' => $package?->price ?? 0,
            'status' => 'active'
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'PPP user created successfully',
            'data' => $pppUser->load('package')
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan',
            'error' => $e->getMessage()
        ], 500);
    }
}
// end Add

    public function show(PppUser $pppUser)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $pppUser->load('package')
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function update(Request $request, PppUser $pppUser)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'username' => 'sometimes|string|unique:ppp_users,username,'.$pppUser->id.'|max:50',
                'password' => 'sometimes|string|min:6|max:50',
                'package_id' => 'nullable|exists:packages,id',
                'status' => 'sometimes|in:active,suspended,expired',
                'phone' => 'sometimes|string|max:20',
                'email' => 'sometimes|email|max:100',
                'address' => 'sometimes|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            // Handle package change
            if (isset($data['package_id'])) {
                $package = Package::find($data['package_id']);
                $profile = 'default-profile_'.$package->code;
                $data['profile'] = $profile;
                $data['balance'] = $package->price;
                $data['due_date'] = Carbon::now()->addDays($package->duration_days);
            }

            $pppUser->update($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'PPP user berhasil diupdate',
                'data' => $pppUser->fresh()->load('package')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e);
        }
    }


public function destroy(PppUser $pppUser)
{
    DB::beginTransaction();

    try {
        // Delete user from MikroTik
        $response = $this->mikrotik->deleteUser($pppUser->username);

        if (!$response['success']) {
            throw new \Exception('Gagal menghapus user dari MikroTik: ' . ($response['error'] ?? 'unknown'));
        }

        // Delete user from database
        $pppUser->delete();

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'PPP user berhasil dihapus'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return $this->errorResponse($e);
    }
}
    //

public function processPayment(Request $request, PppUser $pppUser)
{
    $validator = Validator::make($request->all(), [
        'amount' => 'required|numeric|min:0',
        'payment_method' => 'required|string|max:50',
        'reference' => 'required|string|max:100',
        'package_id' => 'required|exists:packages,id,is_active,1',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    $package = Package::findOrFail($request->package_id);
    $wasSuspended = $pppUser->status === 'suspended';

    // Process payment
    $result = $pppUser->processPayment(
        $request->amount,
        $request->payment_method,
        $request->reference,
        $package->duration_days
    );

    if (!$result['success']) {
        return response()->json([
            'success' => false,
            'error' => $result['error']
        ], 500);
    }

    // Jika sebelumnya suspend, aktifkan kembali (ganti profile di MikroTik)
    if ($wasSuspended) {
        $mikrotikResponse = $this->mikrotik->changeUserProfile($pppUser->username, $pppUser->profile);

        if (!$mikrotikResponse['success']) {
            return response()->json([
                'success' => false,
                'error' => 'Payment processed but failed to reactivate user in MikroTik: ' . ($mikrotikResponse['data']['message'] ?? 'Unknown error')
            ], 500);
        }
    }

    return response()->json([
        'success' => true,
        'message' => 'Payment processed successfully',
        'data' => $pppUser->fresh()->load('package', 'payments')
    ]);
}
    //  midtrans    
// public function processPayment(Request $request, PppUser $pppUser)
//     {
//         $request->validate([
//             'amount' => 'required|numeric',
//             'payment_method' => 'sometimes|string'
//         ]);

//         // Setup Midtrans
//         Config::$serverKey = config('midtrans.server_key');
//         Config::$isProduction = config('midtrans.is_production');
//         Config::$isSanitized = config('midtrans.is_sanitized');
//         Config::$is3ds = config('midtrans.is_3ds');

//         // Buat transaksi
//         $transactionDetails = [
//             'order_id' => 'PYMNT-' . $pppUser->id . '-' . time(),
//             'gross_amount' => $request->amount,
//         ];

//         $customerDetails = [
//             'first_name' => $pppUser->name,
//             'email' => $pppUser->email,
//             'phone' => $pppUser->phone,
//         ];

//         $params = [
//             'transaction_details' => $transactionDetails,
//             'customer_details' => $customerDetails,
//             'item_details' => [
//                 [
//                     'id' => $pppUser->package_id,
//                     'price' => $request->amount,
//                     'quantity' => 1,
//                     'name' => 'Pembayaran paket ' . $pppUser->package->name,
//                 ]
//             ]
//         ];

//         try {
//             $snapToken = Snap::getSnapToken($params);
            
//             // Simpan data pembayaran ke database
//             $payment = Payment::create([
//                 'ppp_user_id' => $pppUser->id,
//                 'amount' => $request->amount,
//                 'order_id' => $transactionDetails['order_id'],
//                 'status' => 'pending',
//                 'snap_token' => $snapToken,
//                 'payment_method' => $request->payment_method ?? null,
//             ]);

//             return response()->json([
//                 'status' => 'success',
//                 'message' => 'Payment initiated',
//                 'snap_token' => $snapToken,
//                 'payment' => $payment,
//             ]);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'status' => 'error',
//                 'message' => 'Payment failed to process',
//                 'error' => $e->getMessage(),
//             ], 500);
//         }
//     }
//     // midtrans integrate    callback
//     public function handleMidtransCallback(Request $request)
// {
//     $payload = $request->all();

//     // Verifikasi signature key jika diperlukan
//     $hashed = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].config('midtrans.server_key'));
    
//     if ($hashed != $payload['signature_key']) {
//         return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 403);
//     }

//     // Cari payment berdasarkan order_id
//     $payment = Payment::where('order_id', $payload['order_id'])->first();

//     if (!$payment) {
//         return response()->json(['status' => 'error', 'message' => 'Payment not found'], 404);
//     }

//     // Update status pembayaran
//     switch ($payload['transaction_status']) {
//         case 'capture':
//         case 'settlement':
//             $payment->status = 'paid';
//             $payment->paid_at = now();
//             // Tambahkan logika untuk memperpanjang masa aktif user
//             $payment->pppUser->update([
//                 'expired_at' => now()->addMonth(), // atau sesuai durasi paket
//                 'status' => 'active'
//             ]);
//             break;
//         case 'pending':
//             $payment->status = 'pending';
//             break;
//         case 'deny':
//         case 'expire':
//         case 'cancel':
//             $payment->status = 'failed';
//             break;
//     }

//     $payment->save();

//     return response()->json(['status' => 'success']);
// }
    //end midtrane

    public function overdueUsers()
    {
        try {
            $users = PppUser::where('status', 'active')
                ->where(function($query) {
                    $query->where('due_date', '<', now()->subDays(7))
                          ->orWhere('balance', '>', 0);
                })
                ->with('package')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $users
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    protected function errorResponse(\Exception $e, $code = 500)
    {
        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan',
            'error' => $e->getMessage()
        ], $code);
    }
}