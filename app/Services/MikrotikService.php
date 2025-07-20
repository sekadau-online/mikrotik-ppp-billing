<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RouterOS\Client; // Assuming you use a RouterOS client library, e.g., "routeros/client"
use RouterOS\Query;
use App\Models\Package; // Import Package model

class MikrotikService
{
    protected $client;
    protected $host;
    protected $user;
    protected $pass;
    protected $port;

    // Define the name for the dynamic PPP IP Pool
    const PPP_POOL_NAME = 'ppp-pool-dynamic';
    const PPP_LOCAL_ADDRESS = '172.16.88.1';
    const PPP_POOL_RANGE_START = '172.16.88.2';
    const PPP_POOL_RANGE_END = '172.16.88.254';


    /**
     * Constructor for MikrotikService.
     * Configures the connection details from environment or config file.
     */
    public function __construct()
    {
        $this->host = config('services.mikrotik.host');
        $this->user = config('services.mikrotik.user');
        $this->pass = config('services.mikrotik.pass');
        $this->port = config('services.mikrotik.port', 8728); // Default API port

        try {
            $this->client = new Client([
                'host' => $this->host,
                'user' => $this->user,
                'pass' => $this->pass,
                'port' => $this->port,
            ]);
            Log::info("Connected to Mikrotik at {$this->host}:{$this->port}");
        } catch (\Exception $e) {
            Log::error("Failed to connect to Mikrotik: " . $e->getMessage());
            // It's critical to throw an exception here if Mikrotik connection is essential
            // as subsequent operations will fail without it.
            throw new \Exception("Failed to connect to Mikrotik: " . $e->getMessage());
        }
    }

    /**
     * Get all PPP profiles from Mikrotik.
     *
     * @return array
     * @throws \Exception
     */
    public function getPppProfiles(): array
    {
        try {
            $query = new Query('/ppp/profile/print');
            $response = $this->client->query($query)->read();
            Log::info("Successfully retrieved PPP profiles from Mikrotik.");
            return $response;
        } catch (\Exception $e) {
            Log::error("Failed to get PPP profiles from Mikrotik: " . $e->getMessage());
            throw new \Exception("Failed to get PPP profiles: " . $e->getMessage());
        }
    }

    /**
     * Synchronize Mikrotik IP Pools based on predefined constants.
     * Ensures the dynamic PPP pool exists and has the correct range.
     *
     * @return array A report of synchronization results.
     * @throws \Exception
     */
    public function syncIpPools(): array
    {
        $results = [];
        $poolName = self::PPP_POOL_NAME;
        $poolRange = self::PPP_POOL_RANGE_START . '-' . self::PPP_POOL_RANGE_END;

        try {
            // Check if the pool already exists
            $existingPools = $this->client->query(
                (new Query('/ip/pool/print'))
                    ->where('name', $poolName)
            )->read();

            if (!empty($existingPools)) {
                // Pool exists, update it if necessary
                $poolId = $existingPools[0]['.id'];
                $currentRanges = $existingPools[0]['ranges'] ?? '';

                if ($currentRanges !== $poolRange) {
                    $query = (new Query('/ip/pool/set'))
                        ->equal('.id', $poolId)
                        ->equal('ranges', $poolRange);
                    $this->client->query($query)->read();
                    $results[] = "Updated Mikrotik IP Pool '{$poolName}' (ID: {$poolId}) to range: {$poolRange}";
                    Log::info("Updated Mikrotik IP Pool '{$poolName}' to range: {$poolRange}");
                } else {
                    $results[] = "Mikrotik IP Pool '{$poolName}' already up-to-date.";
                    Log::info("Mikrotik IP Pool '{$poolName}' already up-to-date.");
                }
            } else {
                // Pool does not exist, create it
                $query = (new Query('/ip/pool/add'))
                    ->equal('name', $poolName)
                    ->equal('ranges', $poolRange);
                $this->client->query($query)->read();
                $results[] = "Created new Mikrotik IP Pool: '{$poolName}' with range: {$poolRange}";
                Log::info("Created new Mikrotik IP Pool: '{$poolName}'");
            }
        } catch (\Exception $e) {
            $errorMessage = "Failed to synchronize Mikrotik IP Pool '{$poolName}': " . $e->getMessage();
            Log::error($errorMessage);
            throw new \Exception($errorMessage);
        }

        return $results;
    }


    /**
     * Add a new PPP user (secret) to Mikrotik.
     * The remote address will be assigned from the profile's pool.
     *
     * @param string $username
     * @param string $password
     * @param string $profileName The name of the PPP profile on Mikrotik
     * @param string|null $localAddress Optional: specific local address for this user. If null, profile's local-address will be used.
     * @param string $service
     * @return string|null The Mikrotik .id of the newly created user, or null if not found.
     * @throws \Exception
     */
    public function addPppUser(
        string $username,
        string $password,
        string $profileName,
        ?string $localAddress = null,
        string $service = 'pppoe'
    ): ?string {
        // MANDATORY: Verify if the profile exists on Mikrotik before adding the user.
        try {
            $existingProfiles = $this->client->query(
                (new Query('/ppp/profile/print'))
                    ->where('name', $profileName)
            )->read();

            if (empty($existingProfiles)) {
                $errorMessage = "Mikrotik profile '{$profileName}' not found. Cannot add user '{$username}' without a valid profile.";
                Log::error($errorMessage);
                throw new \Exception($errorMessage);
            }
        } catch (\Exception $e) {
            $errorMessage = "Error checking Mikrotik profile '{$profileName}' for user '{$username}': " . $e->getMessage();
            Log::error($errorMessage);
            throw new \Exception($errorMessage);
        }

        // Log the service value being sent for debugging
        Log::debug("Attempting to add PPP user '{$username}' with service: '{$service}' and profile: '{$profileName}'.");

        $query = (new Query('/ppp/secret/add'))
            ->equal('name', $username)
            ->equal('password', $password)
            ->equal('profile', $profileName)
            ->equal('service', $service);

        // Only add local-address if it's explicitly provided by the user, otherwise profile's will be used
        if ($localAddress) {
            $query->equal('local-address', $localAddress);
        }
        // Remote-address is now handled by the profile's IP pool, so it's not set here.

        try {
            $response = $this->client->query($query)->read();
            // Log the raw response for debugging purposes
            Log::info("Raw Mikrotik response for adding user '{$username}': " . json_encode($response));
            Log::debug("Type of Mikrotik addPppUser response: " . gettype($response));

            // --- START: Enhanced Error Checking and ID Extraction ---
            $mikrotikId = null;
            $errorMessage = null;

            // Normalize response to an array for easier processing
            $processedResponse = $response;
            if (is_object($processedResponse)) {
                $processedResponse = (array) $processedResponse;
            }

            // Check for error messages first
            if (is_array($processedResponse)) {
                if (isset($processedResponse['after']['message'])) {
                    $errorMessage = $processedResponse['after']['message'];
                } elseif (isset($processedResponse['message'])) { // Some errors might be at root level
                    $errorMessage = $processedResponse['message'];
                }
            }
            // If the original response was a string, try to decode it for an error message
            if (!$errorMessage && is_string($response)) {
                $decodedStringResponse = json_decode($response, true);
                if (is_array($decodedStringResponse)) {
                    if (isset($decodedStringResponse['after']['message'])) {
                        $errorMessage = $decodedStringResponse['after']['message'];
                    } elseif (isset($decodedStringResponse['message'])) {
                        $errorMessage = $decodedStringResponse['message'];
                    }
                }
            }

            if ($errorMessage) {
                Log::error("Mikrotik API returned an error for user '{$username}': " . $errorMessage);
                throw new \Exception("Mikrotik API Error: " . $errorMessage);
            }

            // Proceed with ID extraction only if no error message was found
            if (is_array($processedResponse)) {
                if (isset($processedResponse['ret'])) {
                    $mikrotikId = $processedResponse['ret'];
                } elseif (isset($processedResponse['.id'])) {
                    $mikrotikId = $processedResponse['.id'];
                } elseif (isset($processedResponse['after']) && is_array($processedResponse['after']) && isset($processedResponse['after']['ret'])) {
                    $mikrotikId = $processedResponse['after']['ret'];
                } elseif (count($processedResponse) === 1 && is_string(reset($processedResponse)) && str_starts_with(reset($processedResponse), '*')) {
                    $mikrotikId = reset($processedResponse);
                }
            }
            // Fallback for string response (e.g., direct ID or JSON string)
            if (!$mikrotikId && is_string($response)) {
                $decodedResponse = json_decode($response, true);
                if (is_array($decodedResponse)) {
                    if (isset($decodedResponse['ret'])) {
                        $mikrotikId = $decodedResponse['ret'];
                    } elseif (isset($decodedResponse['.id'])) {
                        $mikrotikId = $decodedResponse['.id'];
                    } elseif (isset($decodedResponse['after']['ret'])) {
                        $mikrotikId = $decodedResponse['after']['ret'];
                    }
                }
                if (!$mikrotikId && str_starts_with($response, '*')) {
                    $mikrotikId = $response;
                }
            }
            // --- END: Enhanced Error Checking and ID Extraction ---


            if ($mikrotikId) {
                Log::info("MikrotikService::addPppUser successfully extracted ID: {$mikrotikId} for user: {$username}.");
                return $mikrotikId;
            }

            Log::warning("MikrotikService::addPppUser response did not contain a valid .id or ret key for user '{$username}'. Final response state: " . json_encode($response));
            return null;

        } catch (\Exception $e) {
            Log::error("Failed to add PPP user '{$username}' to Mikrotik secret: " . $e->getMessage());
            throw new \Exception("Failed to add PPP user to Mikrotik: " . $e->getMessage());
        }
    }

    /**
     * Update an existing PPP user (secret) in Mikrotik.
     *
     * @param string $mikrotikId The .id of the user in Mikrotik
     * @param array $data Data to update (e.g., ['password' => 'newpass', 'profile' => 'new_profile'])
     * @return array
     * @throws \Exception
     */
    public function updatePppUser(string $mikrotikId, array $data): array
    {
        $query = (new Query('/ppp/secret/set'))
            ->equal('.id', $mikrotikId);

        foreach ($data as $key => $value) {
            // Ensure profile exists if it's being updated
            if ($key === 'profile') {
                try {
                    $existingProfiles = $this->client->query(
                        (new Query('/ppp/profile/print'))
                            ->where('name', $value)
                    )->read();

                    if (empty($existingProfiles)) {
                        $errorMessage = "Mikrotik profile '{$value}' not found for update. User '{$mikrotikId}' profile will not be updated.";
                        Log::warning($errorMessage);
                        continue; // Skip this key if profile doesn't exist
                    }
                } catch (\Exception $e) {
                    $errorMessage = "Error checking Mikrotik profile '{$value}' during update: " . $e->getMessage();
                    Log::error($errorMessage);
                    continue;
                }
            }
            // Do not update remote-address here, it's handled by the profile's pool
            if ($key === 'remote-address') {
                Log::info("Skipping remote-address update for user {$mikrotikId} as it's handled by profile pool.");
                continue;
            }
            $query->equal($key, $value);
        }

        try {
            $response = $this->client->query($query)->read();
            Log::info("Successfully updated PPP user with Mikrotik ID '{$mikrotikId}'.");
            return $response;
        } catch (\Exception $e) {
            Log::error("Failed to update PPP user with Mikrotik ID '{$mikrotikId}': " . $e->getMessage());
            throw new \Exception("Failed to update PPP user in Mikrotik: " . $e->getMessage());
        }
    }

    /**
     * Remove a PPP user (secret) from Mikrotik.
     *
     * @param string $mikrotikId The .id of the user in Mikrotik
     * @return array
     * @throws \Exception
     */
    public function removePppUser(string $mikrotikId): array
    {
        $query = (new Query('/ppp/secret/remove'))
            ->equal('.id', $mikrotikId);

        try {
            $response = $this->client->query($query)->read();
            Log::info("Successfully removed PPP user with Mikrotik ID '{$mikrotikId}'.");
            return $response;
        } catch (\Exception $e) {
            Log::error("Failed to remove PPP user with Mikrotik ID '{$mikrotikId}': " . $e->getMessage());
            throw new \Exception("Failed to remove PPP user from Mikrotik: " . $e->getMessage());
        }
    }

    /**
     * Set rate limit for a PPP user.
     * This might be done directly on the user secret or via queues, depending on your Mikrotik setup.
     * This example assumes updating the profile or directly setting rate-limit on the secret.
     * For more complex queue management, you'd interact with /queue/simple.
     *
     * @param string $mikrotikId The .id of the user in Mikrotik
     * @param string $rateLimit e.g., "1M/2M"
     * @return array
     * @throws \Exception
     */
    public function setPppUserRateLimit(string $mikrotikId, string $rateLimit): array
    {
        $query = (new Query('/ppp/secret/set'))
            ->equal('.id', $mikrotikId)
            ->equal('rate-limit', $rateLimit);

        try {
            $response = $this->client->query($query)->read();
            Log::info("Successfully set rate limit for PPP user with Mikrotik ID '{$mikrotikId}' to '{$rateLimit}'.");
            return $response;
        } catch (\Exception $e) {
            Log::error("Failed to set rate limit for PPP user with Mikrotik ID '{$mikrotikId}': " . $e->getMessage());
            throw new \Exception("Failed to set rate limit for PPP user: " . $e->getMessage());
        }
    }

    /**
     * Synchronize Mikrotik PPP profiles based on packages from the database.
     * This method ensures that Mikrotik profiles match your Laravel `packages` table.
     * It will create new profiles if they don't exist, and update existing ones.
     * It also ensures a 'suspend-profile' exists.
     *
     * @return array A report of synchronization results.
     * @throws \Exception
     */
    public function syncProfiles(): array
    {
        $results = [];

        // First, ensure the IP Pool exists and is configured correctly
        try {
            $poolSyncResults = $this->syncIpPools();
            $results = array_merge($results, $poolSyncResults);
        } catch (\Exception $e) {
            Log::error("Failed to synchronize IP Pools before syncing profiles: " . $e->getMessage());
            $results[] = "Error: Failed to synchronize IP Pools. " . $e->getMessage();
            return $results; // Stop if pool sync fails
        }


        $packages = Package::all(); // Get all packages from your Laravel database

        // Define the suspend profile details
        $suspendProfileData = [
            'name' => 'suspend-profile',
            'rate-limit' => '0/0', // No bandwidth for suspended users
            'local-address' => self::PPP_LOCAL_ADDRESS, // Use the defined local address
            'remote-address' => self::PPP_POOL_NAME, // Assign from the dynamic pool
            'comment' => 'Profile for suspended PPP users',
        ];

        // Prepare all profiles to be synced: database packages + suspend profile
        $profilesToSync = $packages->map(function($package) {
            return [
                'name' => $package->mikrotik_profile_name ?: $package->name,
                'rate-limit' => "{$package->upload_speed}M/{$package->download_speed}M",
                'local-address' => self::PPP_LOCAL_ADDRESS, // Assign profile's local address
                'remote-address' => self::PPP_POOL_NAME, // Assign from the dynamic pool
                'comment' => $package->description ?? $package->name,
            ];
        })->toArray();

        // Add the suspend profile to the list to be synced
        $profilesToSync[] = $suspendProfileData;

        // Get existing profiles from Mikrotik to compare
        $mikrotikProfiles = [];
        try {
            $mikrotikProfileList = $this->getPppProfiles();
            foreach ($mikrotikProfileList as $profile) {
                $mikrotikProfiles[$profile['name']] = $profile;
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve existing Mikrotik profiles for sync: " . $e->getMessage());
            $results[] = "Error: Failed to retrieve existing Mikrotik profiles. " . $e->getMessage();
            return $results; // Cannot proceed with sync if we can't get existing profiles
        }

        foreach ($profilesToSync as $profileData) {
            $profileName = $profileData['name'];
            $rateLimit = $profileData['rate-limit'];
            $localAddress = $profileData['local-address'];
            $remoteAddress = $profileData['remote-address']; // This will be the pool name
            $comment = $profileData['comment'] ?? '';

            if (isset($mikrotikProfiles[$profileName])) {
                // Profile exists, update it
                $mikrotikProfileId = $mikrotikProfiles[$profileName]['.id'];
                try {
                    $query = (new Query('/ppp/profile/set'))
                        ->equal('.id', $mikrotikProfileId)
                        ->equal('rate-limit', $rateLimit)
                        ->equal('local-address', $localAddress)
                        ->equal('remote-address', $remoteAddress); // Set remote-address to the pool name
                    if (!empty($comment)) {
                        $query->equal('comment', $comment);
                    }
                    $this->client->query($query)->read();
                    $results[] = "Updated Mikrotik profile: '{$profileName}' (ID: {$mikrotikProfileId}) with rate-limit: {$rateLimit}, local: {$localAddress}, remote: {$remoteAddress}";
                    Log::info("Updated Mikrotik profile: '{$profileName}'");
                } catch (\Exception $e) {
                    $results[] = "Failed to update Mikrotik profile '{$profileName}': " . $e->getMessage();
                    Log::error("Failed to update Mikrotik profile '{$profileName}': " . $e->getMessage());
                }
            } else {
                // Profile does not exist, create it
                try {
                    $query = (new Query('/ppp/profile/add'))
                        ->equal('name', $profileName)
                        ->equal('rate-limit', $rateLimit)
                        ->equal('local-address', $localAddress)
                        ->equal('remote-address', $remoteAddress); // Set remote-address to the pool name
                    if (!empty($comment)) {
                        $query->equal('comment', $comment);
                    }
                    $this->client->query($query)->read();
                    $results[] = "Created new Mikrotik profile: '{$profileName}' with rate-limit: {$rateLimit}, local: {$localAddress}, remote: {$remoteAddress}";
                    Log::info("Created new Mikrotik profile: '{$profileName}'");
                } catch (\Exception $e) {
                    $results[] = "Failed to create Mikrotik profile '{$profileName}': " . $e->getMessage();
                    Log::error("Failed to create Mikrotik profile '{$profileName}': " . $e->getMessage());
                }
            }
        }

        // Optional: Implement logic to remove profiles from Mikrotik that no longer exist in Laravel.
        // This requires careful consideration to avoid accidental deletions.
        // For example:
        // $dbProfileNames = $packages->map(function($p) { return $p->mikrotik_profile_name ?: $p->name; })->all();
        // $dbProfileNames[] = 'suspend-profile'; // Include suspend-profile in the list of profiles that should exist
        // foreach ($mikrotikProfiles as $name => $profileData) {
        //     if (!in_array($name, $dbProfileNames)) {
        //         try {
        //             $this->client->query((new Query('/ppp/profile/remove'))->equal('.id', $profileData['.id']))->read();
        //             $results[] = "Removed Mikrotik profile: '{$name}' (no longer in database or not a system profile)";
        //             Log::info("Removed Mikrotik profile: '{$name}'");
        //         } catch (\Exception $e) {
        //             $results[] = "Failed to remove Mikrotik profile '{$name}': " . $e->getMessage();
        //             Log::error("Failed to remove Mikrotik profile '{$name}': " . $e->getMessage());
        //         }
        //     }
        // }

        Log::info("Mikrotik profile synchronization process finished.");
        return $results;
    }
}
