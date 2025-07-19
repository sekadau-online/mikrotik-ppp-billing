<?php

namespace App\Services;

use RouterOS\Client;
use RouterOS\Query;
use Illuminate\Support\Facades\Log;

class MikrotikService
{
    protected $client;
    protected $timeout = 10; // Timeout default untuk koneksi Mikrotik

    public function __construct()
    {
        try {
            // Mengambil konfigurasi dari config/services.mikrotik atau config/mikrotik.php
            // Pastikan Anda sudah membuat file config/mikrotik.php jika menggunakan 'mikrotik.'
            // Atau jika Anda tetap menggunakan services.php, sesuaikan path config:
            // config('services.mikrotik.host') dst.
            $host = config('mikrotik.host') ?? config('services.mikrotik.host');
            $port = (int) (config('mikrotik.port') ?? config('services.mikrotik.port', 8728));
            $user = config('mikrotik.user') ?? config('services.mikrotik.user');
            $pass = config('mikrotik.pass') ?? config('services.mikrotik.pass');
            $legacy = config('mikrotik.legacy') ?? config('services.mikrotik.legacy', false); // Ambil dari config, default false

            // Validasi dasar konfigurasi sebelum mencoba koneksi
            if (empty($host) || empty($user) || empty($pass) || empty($port)) {
                $errorMessage = "Mikrotik configuration (host, port, user, or pass) is missing.";
                Log::channel('ppp')->error($errorMessage);
                throw new \Exception($errorMessage);
            }

            $this->client = new Client([
                'host'    => $host,
                'port'    => $port,
                'user'    => $user,
                'pass'    => $pass,
                'timeout' => $this->timeout,
                'legacy'  => $legacy, // <--- PERUBAHAN PENTING DI SINI: Menggunakan nilai dari konfigurasi, default false
                'attempts' => 2, // Menambahkan attempts untuk ketahanan
            ]);

            // Pastikan koneksi berfungsi dengan melakukan query sederhana
            // Ini juga akan memicu exception jika kredensial salah atau API tidak merespons
            $this->client->query(new Query('/system/identity/print'))->read();
            
        } catch (\Exception $e) {
            // Catat error ke channel 'ppp' (pastikan channel ini terkonfigurasi di config/logging.php)
            Log::channel('ppp')->error("Failed to connect to Mikrotik: " . $e->getMessage(), [
                'host' => $host ?? 'N/A',
                'port' => $port ?? 'N/A',
                'user' => $user ?? 'N/A',
                // Jangan log password di sini
            ]);
            $this->client = null; // Set client to null if connection fails
            // Lempar exception agar proses inisialisasi service gagal jika koneksi bermasalah
            throw new \Exception("Could not connect to Mikrotik: " . $e->getMessage());
        }
    }

    /**
     * Memeriksa apakah koneksi ke Mikrotik berhasil diinisialisasi.
     *
     * @return bool
     */
    protected function isConnected(): bool
    {
        return $this->client !== null;
    }

    /**
     * Mengubah profil PPP user di Mikrotik.
     * Digunakan untuk suspend, restore, atau perubahan paket.
     *
     * @param string $username
     * @param string $profileName
     * @return array ['success' => bool, 'message' => string, 'error' => string]
     */
    public function changeProfile(string $username, string $profileName): array
    {
        if (!$this->isConnected()) {
            return ['success' => false, 'error' => 'Not connected to Mikrotik.'];
        }

        try {
            // Temukan PPP secret berdasarkan username
            $query = (new Query('/ppp/secret/print'))
                ->where('name', $username);
            $response = $this->client->query($query)->read();

            if (empty($response)) {
                return ['success' => false, 'error' => "User '{$username}' not found in Mikrotik secrets."];
            }

            $id = $response[0]['.id'];

            // Update profil
            $updateQuery = (new Query('/ppp/secret/set'))
                ->equal('.id', $id)
                ->equal('profile', $profileName);
            $this->client->query($updateQuery)->read();

            Log::channel('ppp')->info("Profile for {$username} changed to {$profileName} successfully.");
            return ['success' => true, 'message' => "Profile for {$username} changed to {$profileName}."];
        } catch (\Exception $e) {
            Log::channel('ppp')->error("Error changing profile for {$username}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Membuat PPP user baru di Mikrotik.
     *
     * @param string $username
     * @param string $password
     * @param string $profileName
     * @param string|null $localAddress
     * @param string|null $remoteAddress
     * @return array ['success' => bool, 'message' => string, 'error' => string]
     */
    public function createUser(string $username, string $password, string $profileName, ?string $localAddress = null, ?string $remoteAddress = null): array
    {
        if (!$this->isConnected()) {
            return ['success' => false, 'error' => 'Not connected to Mikrotik.'];
        }

        try {
            $query = (new Query('/ppp/secret/add'))
                ->equal('name', $username)
                ->equal('password', $password)
                ->equal('profile', $profileName)
                ->equal('service', 'pppoe'); // Asumsi service selalu pppoe, sesuaikan jika berbeda

            if ($localAddress) {
                $query->equal('local-address', $localAddress);
            }
            if ($remoteAddress) {
                $query->equal('remote-address', $remoteAddress);
            }

            $this->client->query($query)->read();
            Log::channel('ppp')->info("User {$username} created on Mikrotik successfully.");
            return ['success' => true, 'message' => "User {$username} created on Mikrotik."];
        } catch (\Exception $e) {
            Log::channel('ppp')->error("Error creating user {$username} on Mikrotik: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Menghapus PPP user dari Mikrotik berdasarkan ID Mikrotik-nya.
     * Metode ini dipanggil oleh command sync.
     *
     * @param string $id The .id of the secret on Mikrotik
     * @return array ['success' => bool, 'message' => string, 'error' => string]
     */
    public function deleteUser(string $id): array
    {
        if (!$this->isConnected()) {
            return ['success' => false, 'error' => 'Not connected to Mikrotik.'];
        }

        try {
            $query = (new Query('/ppp/secret/remove'))
                ->equal('.id', $id);
            $this->client->query($query)->read();
            Log::channel('ppp')->info("User with Mikrotik ID '{$id}' deleted from Mikrotik.");
            return ['success' => true, 'message' => "User with Mikrotik ID '{$id}' deleted from Mikrotik."];
        } catch (\Exception $e) {
            Log::channel('ppp')->error("Error deleting user with Mikrotik ID '{$id}' from Mikrotik: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Mendapatkan semua PPP secrets dari Mikrotik.
     * Metode ini dipanggil oleh command sync.
     *
     * @return array Data secrets atau array kosong jika gagal/tidak ada
     */
    public function getAllSecrets(): array
    {
        if (!$this->isConnected()) {
            Log::channel('ppp')->error("MikrotikService: Not connected when trying to get all secrets.");
            return [];
        }

        try {
            $query = new Query('/ppp/secret/print');
            $response = $this->client->query($query)->read();
            return $response; // Langsung kembalikan data, tanpa 'success' key
        } catch (\Exception $e) {
            Log::channel('ppp')->error("Error getting all PPP secrets from Mikrotik: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Mendapatkan satu PPP secret berdasarkan username.
     * Metode ini dipanggil oleh command sync.
     *
     * @param string $username
     * @return array Data secret atau array kosong jika tidak ditemukan
     */
    public function getSecret(string $username): array
    {
        if (!$this->isConnected()) {
            Log::channel('ppp')->error("MikrotikService: Not connected when trying to get secret for {$username}.");
            return [];
        }

        try {
            $query = (new Query('/ppp/secret/print'))
                ->where('name', $username);
            $response = $this->client->query($query)->read();
            return $response; // Akan mengembalikan array, kosong jika tidak ditemukan
        } catch (\Exception $e) {
            Log::channel('ppp')->error("Failed to get Mikrotik secret for {$username}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Mengupdate detail PPP secret di Mikrotik (password, profile, local/remote address, dll).
     * Metode ini dipanggil oleh command sync.
     *
     * @param string $id The .id of the secret on Mikrotik
     * @param string $username
     * @param string $password
     * @param string $profile
     * @param string|null $localAddress
     * @param string|null $remoteAddress
     * @return array ['success' => bool, 'message' => string, 'error' => string]
     */
    public function updateUser(string $id, string $username, string $password, string $profile, ?string $localAddress = null, ?string $remoteAddress = null): array
    {
        if (!$this->isConnected()) {
            return ['success' => false, 'error' => 'Not connected to Mikrotik.'];
        }

        try {
            $updateQuery = (new Query('/ppp/secret/set'))
                ->equal('.id', $id)
                ->equal('name', $username)
                ->equal('password', $password)
                ->equal('profile', $profile);

            // Perhatikan bagaimana Anda ingin menangani nilai null untuk alamat
            // Jika null, Mikrotik akan mempertahankan nilai sebelumnya atau menghapusnya jika diatur ke ''
            if ($localAddress !== null) {
                $updateQuery->equal('local-address', $localAddress);
            } else {
                // Opsional: Hapus local-address di Mikrotik jika null di aplikasi Laravel
                // $updateQuery->equal('local-address', '');
            }
            if ($remoteAddress !== null) {
                $updateQuery->equal('remote-address', $remoteAddress);
            } else {
                // Opsional: Hapus remote-address di Mikrotik jika null di aplikasi Laravel
                // $updateQuery->equal('remote-address', '');
            }
            
            $this->client->query($updateQuery)->read();
            Log::channel('ppp')->info("User {$username} (ID: {$id}) updated on Mikrotik.");
            return ['success' => true, 'message' => "User {$username} updated on Mikrotik."];
        } catch (\Exception $e) {
            Log::channel('ppp')->error("Error updating user {$username} (ID: {$id}) on Mikrotik: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Mengaktifkan PPP user secret di Mikrotik.
     *
     * @param string $id The .id of the secret on Mikrotik
     * @return array ['success' => bool, 'message' => string, 'error' => string]
     */
    public function enableUser(string $id): array
    {
        if (!$this->isConnected()) {
            return ['success' => false, 'error' => 'Not connected to Mikrotik.'];
        }

        try {
            $query = (new Query('/ppp/secret/enable'))
                ->equal('.id', $id);
            $this->client->query($query)->read();
            Log::channel('ppp')->info("Mikrotik user with ID '{$id}' enabled successfully.");
            return ['success' => true, 'message' => "User with Mikrotik ID '{$id}' enabled."];
        } catch (\Exception $e) {
            Log::channel('ppp')->error("Failed to enable Mikrotik user with ID '{$id}': " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Menonaktifkan PPP user secret di Mikrotik.
     *
     * @param string $id The .id of the secret on Mikrotik
     * @return array ['success' => bool, 'message' => string, 'error' => string]
     */
    public function disableUser(string $id): array
    {
        if (!$this->isConnected()) {
            return ['success' => false, 'error' => 'Not connected to Mikrotik.'];
        }

        try {
            $query = (new Query('/ppp/secret/disable'))
                ->equal('.id', $id);
            $this->client->query($query)->read();
            Log::channel('ppp')->info("Mikrotik user with ID '{$id}' disabled successfully.");
            return ['success' => true, 'message' => "User with Mikrotik ID '{$id}' disabled."];
        } catch (\Exception $e) {
            Log::channel('ppp')->error("Failed to disable Mikrotik user with ID '{$id}': " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}