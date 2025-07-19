<?php

namespace App\Http\Controllers\Api; // Pastikan namespace ini benar

use App\Http\Controllers\Controller; // Import base Controller
use Illuminate\Http\Request;
use App\Models\PppUser; // Pastikan model ini ada
use App\Models\Package; // Pastikan model ini ada
use Illuminate\Support\Facades\Log; // Untuk debugging

class PppUserController extends Controller
{
    // Method untuk menampilkan semua user (jika diperlukan)
    public function index()
    {
        try {
            $users = PppUser::with('package')->get();
            return response()->json(['success' => true, 'data' => $users]);
        } catch (\Exception $e) {
            Log::error("Error in PppUserController@index: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal memuat daftar pengguna.'], 500);
        }
    }

    // Method untuk menyimpan user baru (jika diperlukan)
    public function store(Request $request)
    {
        // Implementasi penyimpanan user baru
        // Contoh:
        // $request->validate([...]);
        // $user = PppUser::create($request->all());
        // return response()->json(['success' => true, 'data' => $user], 201);
        return response()->json(['success' => false, 'message' => 'Endpoint belum diimplementasikan.'], 501);
    }

    // Method untuk mencari user berdasarkan username (sesuai routes/api.php)
    public function showByUsername($username)
    {
        try {
            $user = PppUser::where('username', $username)->with('package')->first();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Pelanggan tidak ditemukan.'], 404);
            }
            return response()->json(['success' => true, 'data' => $user]);
        } catch (\Exception $e) {
            Log::error("Error in PppUserController@showByUsername for username " . $username . ": " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan saat mencari pelanggan.'], 500);
        }
    }

    // Method untuk mencari user berdasarkan ID (sesuai routes/api.php)
    public function showById($id)
    {
        try {
            $user = PppUser::with('package')->find($id);
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Pelanggan tidak ditemukan.'], 404);
            }
            return response()->json(['success' => true, 'data' => $user]);
        } catch (\Exception $e) {
            Log::error("Error in PppUserController@showById for ID " . $id . ": " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan saat mencari pelanggan.'], 500);
        }
    }

    // Method untuk update user (jika diperlukan)
    public function update(Request $request, PppUser $pppUser)
    {
        // Implementasi update user
        return response()->json(['success' => false, 'message' => 'Endpoint belum diimplementasikan.'], 501);
    }

    // Method untuk delete user (jika diperlukan)
    public function destroy(PppUser $pppUser)
    {
        // Implementasi delete user
        return response()->json(['success' => false, 'message' => 'Endpoint belum diimplementasikan.'], 501);
    }

    // Method untuk user overdue (jika diperlukan)
    public function overdueUsers()
    {
        // Implementasi logika untuk user overdue
        return response()->json(['success' => false, 'message' => 'Endpoint belum diimplementasikan.'], 501);
    }
}