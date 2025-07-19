<?php

namespace App\Services;

use App\Models\PppUser;
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
public function changeUserProfile(string $username, string $profile): array
{
    if (!$this->client) {
        return [
            'success' => false,
            'data' => [
                'message' => 'RouterOS client not initialized',
            ],
        ];
    }

    try {
        // Cari user berdasarkan name (username PPP)
        $query = (new Query('/ppp/secret/print'))
            ->where('name', $username);

        $existing = $this->client->query($query)->read();

        if (empty($existing)) {
            return [
                'success' => false,
                'data' => [
                    'message' => "PPP user {$username} not found in Mikrotik.",
                ],
            ];
        }

        // Ambil .id user untuk update
        $id = $existing[0]['.id'];

        $update = new Query('/ppp/secret/set');
        $update->equal('.id', $id);
        $update->equal('profile', $profile);

        $this->client->query($update)->read();

        return ['success' => true];
    } catch (\Throwable $e) {
        return [
            'success' => false,
            'data' => [
                'message' => $e->getMessage(),
            ],
        ];
    }
}
//
    //already fixed 02:05 PM Jul 19, 2025
public function suspendUser(string $username): array
{
    $username = trim($username);
    if (empty($username)) {
        return ['success' => false, 'error' => 'Username cannot be empty'];
    }

    try {
        // Cari user sekaligus cek profil saat ini dalam 1 query
        $findQuery = (new Query('/ppp/secret/print'))
            ->where('name', $username);
        $userData = $this->client->query($findQuery)->read();

        if (empty($userData)) {
            return [
                'success' => false, 
                'error' => 'User not found',
                'username' => $username
            ];
        }

        $currentProfile = $userData[0]['profile'] ?? null;
        
        // Jika sudah suspended, langsung return
        if ($currentProfile === 'suspend-profile') {
            return [
                'success' => true,
                'message' => 'User already suspended',
                'username' => $username
            ];
        }

        // Update profile
        $setQuery = (new Query('/ppp/secret/set'))
            ->equal('.id', $userData[0]['.id'])
            ->equal('profile', 'suspend-profile');

        $response = $this->client->query($setQuery)->read();

        return [
            'success' => true,
            'message' => 'User suspended successfully',
            'username' => $username,
            'previous_profile' => $currentProfile
        ];

    } catch (\Exception $e) {
        Log::error("Suspend failed for {$username}", ['error' => $e->getMessage()]);
        return [
            'success' => false,
            'error' => 'Suspension failed: ' . $e->getMessage(),
            'username' => $username
        ];
    }
}


//
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
//
    // public function activateUser(string $username): array
    // {
    //     try {
    //         $query = (new Query('/ppp/secret/enable'))
    //             ->equal('.name', $username);

    //         $response = $this->client->query($query)->read();

    //         return ['success' => true, 'data' => $response];
    //     } catch (\Exception $e) {
    //         return ['success' => false, 'error' => $e->getMessage()];
    //     }
    // }


    // already fixed 02:05 PM Jul 19, 2025
public function restoreUser(string $username, string $profile): array
{
    try {
        // Check if client exists or reconnect
        if (!$this->client) {
            $this->connect();
        }

        // Find user
        $findQuery = (new Query('/ppp/secret/print'))
            ->where('name', $username);
        $response = $this->client->query($findQuery)->read();

        if (empty($response)) {
            return [
                'success' => false,
                'error' => "User $username not found in Mikrotik."
            ];
        }

        $userData = $response[0];

        // Update user
        $updateQuery = (new Query('/ppp/secret/set'))
            ->equal('.id', $userData['.id'])
            ->equal('disabled', 'no')
            ->equal('profile', $profile);

        $this->client->query($updateQuery)->read();

        return ['success' => true];
    } catch (\Exception $e) {
        Log::error("Failed to restore user $username", ['error' => $e->getMessage()]);
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
    //end already fixed 02:05 PM Jul 19, 2025
    //
   public function activateUser(string $username): array
{
    $username = trim($username);
    if (empty($username)) {
        return ['success' => false, 'error' => 'Username cannot be empty'];
    }

    try {
        // Find user and get current profile
        $findQuery = (new Query('/ppp/secret/print'))
            ->where('name', $username);
        $userData = $this->client->query($findQuery)->read();

        if (empty($userData)) {
            return [
                'success' => false,
                'error' => 'User not found',
                'username' => $username
            ];
        }

        $currentProfile = $userData[0]['profile'] ?? null;

        if ($currentProfile !== 'suspend-profile') {
            return [
                'success' => true,
                'message' => 'User was already active',
                'username' => $username,
                'current_profile' => $currentProfile
            ];
        }

        // Get the user's package from database to determine correct profile
        $pppUser = PppUser::where('username', $username)->with('package')->first();

        if (!$pppUser) {
            return [
                'success' => false,
                'error' => 'User not found in database',
                'username' => $username
            ];
        }

        $newProfile = $pppUser->package
            ? 'default-profile_' . $pppUser->package->code
            : 'default-profile';

        $setQuery = (new Query('/ppp/secret/set'))
            ->add('=.id', $userData[0]['.id']) // ✅ perbaikan di sini
            ->equal('profile', $newProfile);

        $response = $this->client->query($setQuery)->read();

        Log::info('Mikrotik user activated', [
            'username' => $username,
            'previous_profile' => $currentProfile,
            'new_profile' => $newProfile
        ]);

        return [
            'success' => true,
            'message' => 'User activated successfully',
            'username' => $username,
            'previous_profile' => $currentProfile,
            'new_profile' => $newProfile
        ];

    } catch (\Exception $e) {
        Log::error("Activation failed for {$username}", ['error' => $e->getMessage()]);
        return [
            'success' => false,
            'error' => 'Activation failed: ' . $e->getMessage(),
            'username' => $username
        ];
    }
}
//
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