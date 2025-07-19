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

    // public function destroy(PppUser $pppUser)
    // {
    //     DB::beginTransaction();

    //     try {
    //         // Delete from MikroTik
    //         $response = $this->mikrotik->suspendUser($pppUser->username);
    //         if (!$response['success']) {
    //             throw new \Exception('Gagal menghapus user dari MikroTik');
    //         }

    //         // Delete from database
    //         $pppUser->delete();

    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'PPP user berhasil dihapus'
    //         ]);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return $this->errorResponse($e);
    //     }
    // }
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

    // Activate in Mikrotik if previously suspended
    if ($wasSuspended) {
        $response = $this->mikrotik->activateUser($pppUser->username);
        if (!$response['success']) {
            return response()->json([
                'success' => false,
                'error' => 'Payment processed but failed to activate in MikroTik'
            ], 500);
        }
    }

    return response()->json([
        'success' => true,
        'message' => 'Payment processed successfully',
        'data' => $pppUser->fresh()->load('package', 'payments')
    ]);
}
    //

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