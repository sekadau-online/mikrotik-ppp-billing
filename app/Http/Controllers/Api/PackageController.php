<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Jobs\MikrotikSyncJob; // Import the new Job
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class PackageController extends Controller
{
    /**
     * Display a listing of the packages.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Get all packages, ordered by sort_order
        $packages = Package::orderBy('sort_order', 'asc')
                           ->paginate($request->get('per_page', 15)); // Paginate results

        return response()->json($packages);
    }

    /**
     * Display a listing of active packages.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function activePackages(Request $request)
    {
        // Get all active packages, ordered by sort_order
        $packages = Package::where('is_active', true)
                           ->orderBy('sort_order', 'asc')
                           ->paginate($request->get('per_page', 15)); // Paginate results

        return response()->json($packages);
    }

    /**
     * Store a newly created package in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            // Validate the incoming request data
            $validatedData = $request->validate([
                'name' => 'required|string|max:255|unique:packages,name',
                'code' => 'required|string|max:255|unique:packages,code',
                'speed_limit' => 'nullable|string|max:255',
                'download_speed' => 'required|integer|min:0',
                'upload_speed' => 'required|integer|min:0',
                'duration_days' => 'required|integer|min:1',
                'price' => 'required|numeric|min:0',
                'description' => 'nullable|string',
                'features' => 'nullable|array', // Changed from 'json' to 'array'
                'is_active' => 'boolean',
                'sort_order' => 'nullable|integer|min:0',
                'mikrotik_profile_name' => 'nullable|string|max:255',
            ]);

            // Create a new Package instance
            $package = Package::create($validatedData);

            // Dispatch the MikrotikSyncJob after a package is stored
            // This ensures profiles are updated/created on Mikrotik
            MikrotikSyncJob::dispatch();
            Log::info('MikrotikSyncJob dispatched after package creation.');


            // Return the created package with a 201 Created status
            return response()->json($package, 201);

        } catch (ValidationException $e) {
            // Return validation errors
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Error creating package: ' . $e->getMessage());
            // Return a generic error response
            return response()->json(['message' => 'Failed to create package.'], 500);
        }
    }

    /**
     * Display the specified package.
     *
     * @param  \App\Models\Package  $package
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Package $package)
    {
        // Return the found package
        return response()->json($package);
    }

    /**
     * Update the specified package in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Package  $package
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Package $package)
    {
        try {
            // Validate the incoming request data
            $validatedData = $request->validate([
                'name' => 'sometimes|required|string|max:255|unique:packages,name,' . $package->id,
                'code' => 'sometimes|required|string|max:255|unique:packages,code,' . $package->id,
                'speed_limit' => 'nullable|string|max:255',
                'download_speed' => 'sometimes|required|integer|min:0',
                'upload_speed' => 'sometimes|required|integer|min:0',
                'duration_days' => 'sometimes|required|integer|min:1',
                'price' => 'sometimes|required|numeric|min:0',
                'description' => 'nullable|string',
                'features' => 'nullable|array', // Changed from 'json' to 'array'
                'is_active' => 'boolean',
                'sort_order' => 'nullable|integer|min:0',
                'mikrotik_profile_name' => 'nullable|string|max:255',
            ]);

            // Update the Package instance
            $package->update($validatedData);

            // Dispatch the MikrotikSyncJob after a package is updated
            MikrotikSyncJob::dispatch();
            Log::info('MikrotikSyncJob dispatched after package update.');

            // Return the updated package
            return response()->json($package);

        } catch (ValidationException $e) {
            // Return validation errors
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Error updating package: ' . $e->getMessage());
            // Return a generic error response
            return response()->json(['message' => 'Failed to update package.'], 500);
        }
    }

    /**
     * Remove the specified package from storage.
     *
     * @param  \App\Models\Package  $package
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Package $package)
    {
        try {
            // Delete the package
            $package->delete();

            // Dispatch the MikrotikSyncJob after a package is deleted
            // This might be useful if you implement the "remove profiles not in DB" logic
            MikrotikSyncJob::dispatch();
            Log::info('MikrotikSyncJob dispatched after package deletion.');

            // Return a success message with 204 No Content status
            return response()->json(null, 204);

        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Error deleting package: ' . $e->getMessage());
            // Return a generic error response
            return response()->json(['message' => 'Failed to delete package.'], 500);
        }
    }

    /**
     * Synchronize Mikrotik profiles.
     * This method now dispatches a job to perform the synchronization in the background.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncProfiles(Request $request)
    {
        // Dispatch the job to synchronize Mikrotik profiles
        MikrotikSyncJob::dispatch();

        Log::info('MikrotikSyncJob dispatched from syncProfiles endpoint.');

        return response()->json(['message' => 'Mikrotik profile synchronization initiated. It will run in the background.'], 202);
    }
}
