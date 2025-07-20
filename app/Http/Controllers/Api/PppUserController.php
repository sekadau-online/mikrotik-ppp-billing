<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PppUser;
use App\Models\Package;
use App\Services\MikrotikService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PppUserController extends Controller
{
    protected $mikrotikService;

    public function __construct(MikrotikService $mikrotikService)
    {
        $this->mikrotikService = $mikrotikService;
    }

    /**
     * Display a listing of PPP users with pagination
     */
    public function index(Request $request)
    {
        $query = PppUser::with(['package' => function($query) {
            $query->select('id', 'name', 'price', 'duration_days');
        }]);

        // Search filter
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('username', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%");
            });
        }

        // Status filter
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Order by
        $orderBy = $request->get('order_by', 'created_at');
        $orderDirection = $request->get('order_dir', 'desc');
        $query->orderBy($orderBy, $orderDirection);

        $users = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'total' => $users->total(),
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'last_page' => $users->lastPage(),
            ]
        ]);
    }

    /**
     * Store a new PPP user
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validatedData = $this->validateUserData($request);
            $validatedData['password'] = Hash::make($validatedData['password']);

            // Set initial status and dates
            $validatedData = $this->setInitialUserStatus($validatedData);

            $pppUser = PppUser::create($validatedData);
            
            // Handle Mikrotik integration if package is assigned
            $this->handleMikrotikCreation($pppUser, $request->input('password'));

            DB::commit();

            return response()->json([
                'message' => 'PPP user created successfully',
                'data' => $pppUser->load('package')
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("PPP User Creation Error: " . $e->getMessage());
            return $this->serverErrorResponse('Failed to create PPP user', $e);
        }
    }

    /**
     * Get PPP user by username
     */
    public function showByUsername(string $username)
    {
        try {
            $user = PppUser::with(['package' => function($query) {
                $query->select('id', 'name', 'price', 'speed_limit');
            }])->where('username', $username)->firstOrFail();

            return response()->json([
                'data' => $user,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'PPP User not found',
                'success' => false
            ], 404);
        }
    }

    /**
     * Get PPP user by ID
     */
    public function showById(string $id)
    {
        try {
            $user = PppUser::with(['package' => function($query) {
                $query->select('id', 'name', 'price', 'speed_limit');
            }])->findOrFail($id);

            return response()->json([
                'data' => $user,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'PPP User not found',
                'success' => false
            ], 404);
        }
    }

    /**
     * Update PPP user
     */
    public function update(Request $request, PppUser $pppUser)
    {
        DB::beginTransaction();
        try {
            $validatedData = $this->validateUserData($request, $pppUser->id);
            
            // Handle password update
            if (isset($validatedData['password'])) {
                $plainPassword = $validatedData['password'];
                $validatedData['password'] = Hash::make($plainPassword);
            }

            $pppUser->update($validatedData);

            // Handle Mikrotik integration
            $this->handleMikrotikUpdate($pppUser, $plainPassword ?? null);

            DB::commit();

            return response()->json([
                'message' => 'PPP user updated successfully',
                'data' => $pppUser->fresh('package')
            ]);

        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("PPP User Update Error: " . $e->getMessage());
            return $this->serverErrorResponse('Failed to update PPP user', $e);
        }
    }

    /**
     * Delete PPP user
     */
    public function destroy(PppUser $pppUser)
    {
        DB::beginTransaction();
        try {
            // Remove from Mikrotik if exists
            if ($pppUser->mikrotik_id) {
                $this->mikrotikService->removePppUser($pppUser->mikrotik_id);
            }

            $pppUser->delete();

            DB::commit();

            return response()->json([
                'message' => 'PPP user deleted successfully',
                'success' => true
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("PPP User Deletion Error: " . $e->getMessage());
            return $this->serverErrorResponse('Failed to delete PPP user', $e);
        }
    }

    /**
     * Get overdue users
     */
    public function overdueUsers(Request $request)
    {
        $users = PppUser::with(['package' => function($query) {
                $query->select('id', 'name', 'price');
            }])
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['suspended', 'expired'])
            ->orderBy('due_date')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'total' => $users->total(),
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'last_page' => $users->lastPage(),
            ]
        ]);
    }

    /**
     * Validate user data
     */
    protected function validateUserData(Request $request, $userId = null)
    {
        $rules = [
            'username' => 'required|string|max:32|unique:ppp_users,username,' . $userId,
            'password' => ($userId ? 'nullable' : 'required') . '|string|min:6|max:32',
            'service' => 'nullable|string|in:pppoe,any,pptp,l2tp,ovpn,sstp',
            'local_address' => 'nullable|ip',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'grace_period_days' => 'nullable|integer|min:0|max:30',
            'balance' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:active,suspended,expired,pending',
            'package_id' => 'nullable|exists:packages,id',
        ];

        return $request->validate($rules);
    }

    /**
     * Set initial user status and dates
     */
    protected function setInitialUserStatus($data)
    {
        $data['status'] = 'pending';
        $data['activated_at'] = now();
        $data['expired_at'] = now()->addDays(1);
        $data['due_date'] = $data['expired_at']->subDays($data['grace_period_days'] ?? 1);

        return $data;
    }

    /**
     * Handle Mikrotik user creation
     */
    protected function handleMikrotikCreation(PppUser $user, $plainPassword)
    {
        if (!$user->package_id) {
            Log::info("Skipping Mikrotik creation for {$user->username} - no package assigned");
            return;
        }

        $package = Package::find($user->package_id);
        if (!$package) {
            Log::warning("Package not found for user {$user->username}");
            return;
        }

        $profileName = $package->mikrotik_profile_name ?? $package->name;

        try {
            // Ensure profiles and pools are synced
            $this->mikrotikService->syncProfiles();

            // Create user in Mikrotik
            $mikrotikId = $this->mikrotikService->addPppUser(
                $user->username,
                $plainPassword,
                $profileName,
                $user->local_address,
                $user->service ?? 'pppoe'
            );

            if ($mikrotikId) {
                $user->update(['mikrotik_id' => $mikrotikId]);
                Log::info("Created Mikrotik user {$user->username} with ID {$mikrotikId}");
            } else {
                throw new \Exception("Failed to get Mikrotik ID after creation");
            }

        } catch (\Exception $e) {
            Log::error("Mikrotik creation error for {$user->username}: " . $e->getMessage());
            throw new \Exception("Mikrotik user creation failed: " . $e->getMessage());
        }
    }

    /**
     * Handle Mikrotik user update
     */
    protected function handleMikrotikUpdate(PppUser $user, $plainPassword = null)
    {
        if (!$user->mikrotik_id) {
            Log::info("Skipping Mikrotik update for {$user->username} - no Mikrotik ID");
            return;
        }

        $updateData = [];

        // Password update
        if ($plainPassword) {
            $updateData['password'] = $plainPassword;
        }

        // Status change to suspended
        if ($user->status === 'suspended') {
            $updateData['profile'] = 'suspend-profile';
        } 
        // Package change
        elseif ($user->package_id) {
            $package = Package::find($user->package_id);
            if ($package) {
                $updateData['profile'] = $package->mikrotik_profile_name ?? $package->name;
            }
        }

        // Service update
        if ($user->service) {
            $updateData['service'] = $user->service;
        }

        // Local address update
        if ($user->local_address) {
            $updateData['local-address'] = $user->local_address;
        }

        if (!empty($updateData)) {
            try {
                $this->mikrotikService->syncProfiles();
                $this->mikrotikService->updatePppUser($user->mikrotik_id, $updateData);
                Log::info("Updated Mikrotik user {$user->username} with data: " . json_encode($updateData));
            } catch (\Exception $e) {
                Log::error("Mikrotik update error for {$user->username}: " . $e->getMessage());
                throw new \Exception("Mikrotik user update failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Return validation error response
     */
    protected function validationErrorResponse(ValidationException $e)
    {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors(),
            'success' => false
        ], 422);
    }

    /**
     * Return server error response
     */
    protected function serverErrorResponse($message, \Exception $e)
    {
        return response()->json([
            'message' => $message,
            'error' => config('app.debug') ? $e->getMessage() : null,
            'success' => false
        ], 500);
    }
}