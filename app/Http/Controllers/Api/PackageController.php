<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\MikrotikService;

class PackageController extends Controller
{
    protected $mikrotik;

    public function __construct(MikrotikService $mikrotik)
    {
        $this->mikrotik = $mikrotik;
    }

    public function index()
    {
        try {
            $packages = Package::where('is_active', true)->get();
            return response()->json(['data' => $packages]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve packages'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'code' => 'required|string|unique:packages,code|max:20',
            'speed_limit' => 'required|string|max:50',
            'duration_days' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $package = Package::create($request->all());

            // Sinkronisasi ke MikroTik
            $this->mikrotik->createOrUpdateProfile([
                'name' => 'default-profile_' . $package->code,
                'rate-limit' => $package->speed_limit
            ]);

            return response()->json(['data' => $package], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create package: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Package $package)
    {
        return response()->json(['data' => $package]);
    }

    public function update(Request $request, Package $package)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'code' => 'sometimes|string|unique:packages,code,' . $package->id . '|max:20',
            'speed_limit' => 'sometimes|string|max:50',
            'duration_days' => 'sometimes|integer|min:1',
            'price' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $package->update($request->all());

            // Sinkronisasi ke MikroTik
            $code = $request->input('code', $package->code);
            $speed = $request->input('speed_limit', $package->speed_limit);
            $this->mikrotik->createOrUpdateProfile([
                'name' => 'default-profile_' . $code,
                'rate-limit' => $speed
            ]);

            return response()->json(['data' => $package]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update package: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Package $package)
    {
        try {
            $package->delete();
            return response()->json(['message' => 'Package deleted']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete package'
            ], 500);
        }
    }
    public function syncProfiles()
{
    try {
        $packages = Package::where('is_active', true)->get();
        foreach ($packages as $package) {
            $this->mikrotik->createOrUpdateProfile([
                'name' => 'default-profile_' . $package->code,
                'rate-limit' => $package->speed_limit
            ]);
        }

        return response()->json(['message' => 'All packages synced to MikroTik successfully.']);
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Failed to sync packages: ' . $e->getMessage()
        ], 500);
    }
}
}
