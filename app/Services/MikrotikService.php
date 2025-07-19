<?php

namespace App\Services;

use RouterOS\Client;
use RouterOS\Query;
use Illuminate\Support\Facades\Log;

class MikrotikService
{
    protected $client;

    public function __construct()
    {
        $this->connect();
    }

    private function connect()
    {
        try {
            $this->client = new Client([
                'host' => env('MIKROTIK_HOST'),
                'user' => env('MIKROTIK_USER'),
                'pass' => env('MIKROTIK_PASS'),
                'port' => (int) env('MIKROTIK_PORT', 8728),
                'timeout' => 10,
            ]);
        } catch (\Exception $e) {
            Log::error('Mikrotik connection failed: ' . $e->getMessage());
            throw new \Exception("Could not connect to MikroTik");
        }
    }

    // public function addUser(array $data): array
    // {
    //     // Validate required fields FIRST
    //     if (!isset($data['username']) || empty($data['username'])) {
    //         return ['success' => false, 'error' => 'Username is required'];
    //     }
    //     if (!isset($data['password']) || empty($data['password'])) {
    //         return ['success' => false, 'error' => 'Password is required'];
    //     }
    //     if (!isset($data['local_address']) || empty($data['local_address'])) {
    //         return ['success' => false, 'error' => 'Local address is required'];
    //     }
    //     if (!isset($data['remote_address']) || empty($data['remote_address'])) {
    //         return ['success' => false, 'error' => 'Remote address is required'];
    //     }

    //     try {
    //         $query = (new Query('/ppp/secret/add'))
    //             ->equal('name', $data['username'])
    //             ->equal('password', $data['password'])
    //             ->equal('service', $data['service'] ?? 'pppoe')  // Default: pppoe
    //             ->equal('profile', $data['profile'] ?? 'default') // Default: default
    //             ->equal('local-address', $data['local_address'])
    //             ->equal('remote-address', $data['remote_address']);

    //         $response = $this->client->query($query)->read();

    //         return ['success' => true, 'data' => $response];
    //     } catch (\Exception $e) {
    //         return [
    //             'success' => false,
    //             'error' => $e->getMessage(),
    //             'debug_data' => $data  // Helps debugging missing fields
    //         ];
    //     }
    // }
public function createOrUpdateProfile(array $data): array
{
    try {
        // Cek apakah profile sudah ada
        $query = (new \RouterOS\Query('/ppp/profile/print'))
            ->where('name', $data['name']);
        $existing = $this->client->query($query)->read();

        if (!empty($existing)) {
            // Update jika ada
            $id = $existing[0]['.id'];
            $update = (new \RouterOS\Query('/ppp/profile/set'))
                ->equal('.id', $id)
                ->equal('rate-limit', $data['rate-limit']);
            $response = $this->client->query($update)->read();
        } else {
            // Buat baru
            $create = (new \RouterOS\Query('/ppp/profile/add'))
                ->equal('name', $data['name'])
                ->equal('rate-limit', $data['rate-limit']);
            $response = $this->client->query($create)->read();
        }

        return ['success' => true, 'data' => $response];
    } catch (\Exception $e) {
        \Log::error('Failed to sync profile to MikroTik: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
    //
public function addUser(array $data): array
{
    try {
        $query = (new Query('/ppp/secret/add'))
            ->equal('name', $data['username'])
            ->equal('password', $data['password'])
            ->equal('service', $data['service'] ?? 'pppoe')
            ->equal('profile', $data['profile'] ?? 'default')
            ->equal('local-address', $data['local_address'])
            ->equal('remote-address', $data['remote_address']);

        $response = $this->client->query($query)->read();

        Log::info('Mikrotik addUser response', [
            'request_data' => $data,
            'response' => $response
        ]);

        return ['success' => true, 'data' => $response];
    } catch (\Exception $e) {
        Log::error('addUser error', [
            'message' => $e->getMessage(),
            'data' => $data
        ]);
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'debug_data' => $data
        ];
    }
}


    //
    public function suspendUser(string $username): array
    {
        try {
            $query = (new Query('/ppp/secret/disable'))
                ->equal('name', $username);

            $response = $this->client->query($query)->read();

            return ['success' => true, 'data' => $response];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
public function deleteUser(string $username): array
{
    try {
        // Step 1: Cari user berdasarkan name
        $findQuery = (new Query('/ppp/secret/print'))
            ->where('name', $username);
        $result = $this->client->query($findQuery)->read();

        if (empty($result)) {
            return ['success' => false, 'error' => 'User not found in MikroTik'];
        }

        $mikrotikId = $result[0]['.id'];

        // Step 2: Hapus user berdasarkan .id
        $deleteQuery = (new Query('/ppp/secret/remove'))
            ->equal('.id', $mikrotikId);

        $response = $this->client->query($deleteQuery)->read();

        return ['success' => true, 'data' => $response];
    } catch (\Exception $e) {
        \Log::error('deleteUser error: ' . $e->getMessage(), [
            'username' => $username
        ]);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

    public function activateUser(string $username): array
    {
        try {
            $query = (new Query('/ppp/secret/enable'))
                ->equal('.name', $username);

            $response = $this->client->query($query)->read();

            return ['success' => true, 'data' => $response];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function isRemoteAddressUsed(string $remoteAddress): bool
    {
        try {
            $query = (new Query('/ppp/secret/print'))
                ->where('remote-address', $remoteAddress);

            $response = $this->client->query($query)->read();
            return !empty($response);
        } catch (\Exception $e) {
            Log::error('Remote address check failed: ' . $e->getMessage());
            return true; // Assume address is used if error occurs
        }
    }
}