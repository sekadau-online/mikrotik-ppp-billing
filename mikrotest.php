<?php

require 'vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Query;

$client = new Client([
    'host' => '103.97.199.10',
    'user' => 'laravel-test',
    'pass' => '12345',
]);

$query = new Query('/ppp/secret/print');

$result = $client->query($query)->read();

print_r($result);
