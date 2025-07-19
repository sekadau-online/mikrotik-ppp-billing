<?php

namespace App\Http\Controllers\Api; // Pastikan namespace ini benar

use App\Http\Controllers\Controller; // Import base Controller
use Illuminate\Http\Request;
use App\Models\Package; // Pastikan model ini ada
use Illuminate\Support\Facades\Log;

class PackageController extends Controller
{
    // Method untuk menampilkan semua paket (sesuai routes/api.php)
    public function index()
    {
        try {
            $packages = Package::all();
            return response()->json(['success' => true, 'data' => $packages]);
        } catch (\Exception $e) {
            Log::error("Error in PackageController@index: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal memuat daftar paket.'], 500);
        }
    }

    // Method untuk menampilkan paket aktif (sesuai routes/api.php)
    public function activePackages()
    {
        try {
            $packages = Package::where('is_active', true)->get(); // Asumsi kolom 'is_active'
            return response()->json(['success' => true, 'data' => $packages]);
        } catch (\Exception $e) {
            Log::error("Error in PackageController@activePackages: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal memuat daftar paket aktif.'], 500);
        }
    }

    // Method untuk menyimpan paket baru (jika diperlukan)
    public function store(Request $request)
    {
        // Implementasi penyimpanan paket baru
        return response()->json(['success' => false, 'message' => 'Endpoint belum diimplementasikan.'], 501);
    }

    // Method untuk menampilkan detail paket (jika diperlukan)
    public function show(Package $package)
    {
        return response()->json(['success' => true, 'data' => $package]);
    }

    // Method untuk update paket (jika diperlukan)
    public function update(Request $request, Package $package)
    {
        // Implementasi update paket
        return response()->json(['success' => false, 'message' => 'Endpoint belum diimplementasikan.'], 501);
    }

    // Method untuk delete paket (jika diperlukan)
    public function destroy(Package $package)
    {
        // Implementasi delete paket
        return response()->json(['success' => false, 'message' => 'Endpoint belum diimplementasikan.'], 501);
    }

    // Method untuk sync profiles Mikrotik (jika diperlukan)
    public function syncProfiles()
    {
        // Implementasi sinkronisasi profil Mikrotik
        return response()->json(['success' => false, 'message' => 'Endpoint belum diimplementasikan.'], 501);
    }
}