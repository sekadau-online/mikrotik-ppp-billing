<?php

return [
    'host' => env('MIKROTIK_HOST', '192.168.88.1'),
    'port' => (int) env('MIKROTIK_PORT', 8728),
    'user' => env('MIKROTIK_USER', 'laravel-test'),
    'pass' => env('MIKROTIK_PASS', '12345'),
    'timeout' => (int) env('MIKROTIK_TIMEOUT', 10),
    'legacy' => false, // Add this for older RouterOS versions
];