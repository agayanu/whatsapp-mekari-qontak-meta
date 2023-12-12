<?php

require 'vendor/autoload.php';

use Carbon\Carbon;
use Dotenv\Dotenv;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\ClientException;

// Load .env file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

/**
 * Generate authentication headers based on method and path
 */
function generate_headers($method, $pathWithQueryParam) {
    $datetime       = Carbon::now()->toRfc7231String();
    $request_line   = "{$method} {$pathWithQueryParam} HTTP/1.1";
    $payload        = implode("\n", ["date: {$datetime}", $request_line]);
    $digest         = hash_hmac('sha256', $payload, $_ENV['MEKARI_API_CLIENT_SECRET'], true);
    $signature      = base64_encode($digest);
    
    return [
        'Content-Type'  => 'application/json',
        'Date'          => $datetime,
        'Authorization' => "hmac username=\"{$_ENV['MEKARI_API_CLIENT_ID']}\", algorithm=\"hmac-sha256\", headers=\"date request-line\", signature=\"{$signature}\""
    ];
}

// Set http client
$client = new GuzzleHttp\Client([
    'base_uri' => $_ENV['MEKARI_API_BASE_URL']
]);

// Set method and path for the request
$method     = 'POST';
$path       = '/v2/klikpajak/v1/efaktur/out/';
$queryParam = '?auto_approval=false';
$headers    = [
    'X-Idempotency-Key' => '1234'
];
$body       = [/* request body */];

// Initiate request
try {
    $response = $client->request($method, $path, [
        'headers'   => array_merge(generate_headers($method, $path . $queryParam), $headers),
        'body'      => json_encode($body)
    ]);

    echo $response->getBody();
} catch (ClientException $e) {
    echo Psr7\Message::toString($e->getRequest());
    echo Psr7\Message::toString($e->getResponse());
    echo PHP_EOL;
}
