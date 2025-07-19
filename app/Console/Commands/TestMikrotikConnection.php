<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RouterOS\Client;
use RouterOS\Query;
use Illuminate\Support\Facades\Log;

class TestMikrotikConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mikrotik:test-connection 
        {--show-password : Display the password in output for debugging}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test connection to Mikrotik router and verify API access';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $config = config('services.mikrotik');
        
        // Validate configuration
        $requiredConfig = ['host', 'port', 'user', 'pass'];
        foreach ($requiredConfig as $key) {
            if (empty($config[$key])) {
                $this->error("Mikrotik '{$key}' not configured in services.mikrotik!");
                Log::channel('ppp')->error("Mikrotik configuration missing: {$key}");
                return 1;
            }
        }

        $this->line("\n<fg=blue>Testing Mikrotik Connection:</>");
        $this->line("Host: <fg=yellow>{$config['host']}</>");
        $this->line("Port: <fg=yellow>{$config['port']}</>");
        $this->line("User: <fg=yellow>{$config['user']}</>");
        
        if ($this->option('show-password')) {
            $this->line("Pass: <fg=red>{$config['pass']}</>");
        } else {
            $this->line("Pass: <fg=yellow>".str_repeat('*', strlen($config['pass']))."</>");
        }

        // Test TCP connection first
        if (!$this->testPortConnection($config['host'], (int) $config['port'])) {
            $this->error("Aborting API connection attempt due0 to port unreachability.");
            return 1;
        }

        try {
            $this->line("\n<fg=blue>Attempting API connection...</>");
            
            $client = new Client([
                'host'     => $config['host'],
                'port'     => (int) $config['port'],
                'user'     => $config['user'],
                'pass'     => $config['pass'],
                'timeout'  => 5,
                'legacy'   => false, // <--- PERUBAHAN PENTING DI SINI: Ubah ini menjadi false
                'attempts' => 2,
            ]);

            // Test simple query to get identity
            $identity = $client->query(new Query('/system/identity/print'))->read();
            
            $this->info("\n✅ Connection successful!");
            $this->line("Router Identity: <fg=green>".($identity[0]['name'] ?? 'Unknown')."</>");
            
            // Additional verification for API permissions
            $this->verifyApiPermissions($client, $config['user']);
            
            return 0;
            
        } catch (\Exception $e) {
            Log::channel('ppp')->error("Mikrotik API connection test failed", [
                'error' => $e->getMessage(),
                'config' => [
                    'host' => $config['host'],
                    'port' => $config['port'],
                    'user' => $config['user'],
                    // Do NOT log password here
                ]
            ]);
            
            $this->error("\n❌ API Connection failed: ".$e->getMessage());
            
            $this->line("\n<fg=red>Troubleshooting steps:</>");
            $this->line("1. Verify credentials on router: <fg=yellow>/user print</>");
            $this->line("2. Check API service status: <fg=yellow>/ip service print where name=api</>");
            $this->line("3. Ensure API service is enabled and reachable from this server.");
            $this->line("4. Check firewall rules on both server and Mikrotik.");
            $this->line("5. Ensure the Mikrotik user has sufficient API permissions (e.g., 'full' group).");
            $this->line("6. If using a modern RouterOS (v6.x/v7.x), try setting `'legacy' => false` in the Client constructor.");
            $this->line("7. Clear Laravel config cache: <fg=yellow>php artisan config:clear</> then retry.");
            
            return 1;
        }
    }

    /**
     * Tests direct TCP port connectivity to the Mikrotik host.
     *
     * @param string $host The Mikrotik host IP or hostname.
     * @param int $port The Mikrotik API port.
     * @return bool True if the port is reachable, false otherwise.
     */
    protected function testPortConnection(string $host, int $port): bool
    {
        $this->line("\n<fg=blue>Testing port {$port} connectivity...</>");
        
        // Use @ to suppress PHP warnings from fsockopen, we handle errors via $errno and $errstr
        $connection = @fsockopen($host, $port, $errno, $errstr, 5);
        
        if (is_resource($connection)) {
            $this->line("✅ Port <fg=green>{$port}</> is reachable.");
            fclose($connection);
            return true;
        } else {
            $this->line("❌ Port <fg=red>{$port}</> unreachable: {$errstr} ({$errno}).");
            $this->line("Check firewall rules and ensure Mikrotik API service is enabled and listening on this port.");
            Log::channel('ppp')->warning("Mikrotik port {$port} unreachable.", [
                'host' => $host,
                'port' => $port,
                'errno' => $errno,
                'errstr' => $errstr,
            ]);
            return false;
        }
    }

    /**
     * Verifies if the authenticated user has sufficient API permissions by performing various read queries.
     *
     * @param Client $client The RouterOS API client instance.
     * @param string $username The username used for authentication.
     */
    protected function verifyApiPermissions(Client $client, string $username): void
    {
        $this->line("\n<fg=blue>Verifying API permissions...</>");
        
        try {
            // Test read permission for interfaces (requires 'read' policy)
            $this->line("Attempting to read interfaces (`/interface/print`)...");
            $client->query(new Query('/interface/print'))->read();
            $this->line("✅ Can read interfaces.");

            // Test read permission for IP addresses (requires 'read' policy)
            $this->line("Attempting to read IP addresses (`/ip/address/print`)...");
            $client->query(new Query('/ip/address/print'))->read();
            $this->line("✅ Can read IP addresses.");
            
            // Test read permission for system resources (requires 'read' policy)
            $this->line("Attempting to read system resources (`/system/resource/print`)...");
            $client->query(new Query('/system/resource/print'))->read();
            $this->line("✅ Can read system resources.");
            
            $this->info("✅ User has sufficient API read permissions for common paths.");
            
        } catch (\Exception $e) {
            $this->error("⚠️  Potential permission issues: ".$e->getMessage());
            $this->line("Ensure the Mikrotik user '{$username}' has appropriate API permissions (e.g., 'read', 'write', 'policy').");
            $this->line("On Mikrotik, check user groups: <fg=yellow>/user print</>");
            $this->line("To grant full permissions (use with caution): <fg=yellow>/user set [find name=\"{$username}\"] group=full</>");
            Log::channel('ppp')->warning("Mikrotik API permission check failed for user {$username}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
