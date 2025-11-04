<?php

require 'vendor/autoload.php';

use Carbon\Carbon;
use Dotenv\Dotenv;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\ClientException;

// Load .env file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

/**
 * Generate authentication headers based on method and path
 */
function generate_headers($method, $path) {
    $datetime     = Carbon::now()->toRfc7231String();
    $request_line = "{$method} {$path} HTTP/1.1";
    $payload      = implode("\n", ["date: {$datetime}", $request_line]);
    $digest       = hash_hmac('sha256', $payload, $_ENV['MEKARI_API_CLIENT_SECRET'], true);
    $signature    = base64_encode($digest);
    
    return [
        'Content-Type'  => 'application/json',
        'Date'          => $datetime,
        'Authorization' => "hmac username=\"{$_ENV['MEKARI_API_CLIENT_ID']}\", algorithm=\"hmac-sha256\", headers=\"date request-line\", signature=\"{$signature}\""
    ];
}

/**
 * A universal function to send requests to the Mekari API.
 */
function mekari_request($method,$path,$payload = NULL) {
    // Set http client
    $client = new GuzzleHttp\Client([
        'base_uri' => $_ENV['MEKARI_API_BASE_URL']
    ]);

    // Send request
    try {
        if ($method === 'POST') {
            $response = $client->request($method, $path, [
                'headers' => generate_headers($method, $path),
                'body'    => json_encode($payload)
            ]);
        }
        if ($method === 'GET') {
            $response = $client->request($method, $path, [
                'headers' => generate_headers($method, $path)
            ]);
        }
    } catch (ClientException $e) {
        $errorRequest = Psr7\Message::toString($e->getRequest());
        $errorResponse = Psr7\Message::toString($e->getResponse());
        $detail = [
            'error_request' => $errorRequest,
            'error_response' => $errorResponse,
        ];
        error_function('Error Send Request to Mekari!',$detail);
    }

    return [
        'response' => json_decode($response->getBody()->getContents()),
        'http_code' => $response->getStatusCode()
    ];
}

function error_function($msg,$detail = NULL) {
    $resJson = [
        'status' => 'error',
        'msg' => $msg,
        'detail' => $detail,
    ];
    echo json_encode($resJson);
    echo PHP_EOL;
}

// Set request
$category    = null;
$phone       = null;
$parentName  = null;
$studentName = null;
if (isset($_POST['category']))     { $category    = $_POST['category']; }     // Example : absence
if (isset($_POST['phone']))        { $phone       = $_POST['phone']; }        // Example : 6281284420481
if (isset($_POST['parent_name']))  { $parentName  = $_POST['parent_name']; }  // Example : ANDRI SIREGAR/NANA SIREGAR
if (isset($_POST['student_name'])) { $studentName = $_POST['student_name']; } // Example : FERNANDES SIREGAR
If (!in_array($category,['absence','payment','bill'])) {error_function('Category cannot NULL!');}
If (!$phone)       {error_function('Phone cannot NULL!');}
If (!$parentName)  {error_function('Parent Name cannot NULL!');}
If (!$studentName) {error_function('Student Name cannot NULL!');}

if ($category === 'absence') {
    $absenceDate   = null;
    $absenceStatus = null;
    $absenceRemark = null;
    if (isset($_POST['trans_date']))     { $absenceDate   = $_POST['trans_date']; }   // Example : 18-Okt-2025
    if (isset($_POST['absence_status'])) { $absenceStatus = $_POST['absence_status']; } // Example : SAKIT
    if (isset($_POST['absence_remark'])) { $absenceRemark = $_POST['absence_remark']; } // Example : Info dari guru kelas
    If (!$absenceDate)   {error_function('Absence Date cannot NULL!');}
    If (!$absenceStatus) {error_function('Absence Status cannot NULL!');}
    If (!$absenceRemark) {error_function('Absence Remark cannot NULL!');}
}
if ($category === 'payment') {
    $studentClass = null;
    $paymentInfo  = null;
    $paymentDate = null;
    if (isset($_POST['student_class'])) { $studentClass = $_POST['student_class']; } // Example : XI.R-3
    if (isset($_POST['message']))       { $paymentInfo  = $_POST['message']; }  // Example : SPP Nov-2025: 600,000
    if (isset($_POST['trans_date']))    { $paymentDate  = $_POST['trans_date']; }  // Example : 29-Okt-2025
    If (!$studentClass) {error_function('Student Class cannot NULL!');}
    If (!$paymentInfo)  {error_function('Payment Info cannot NULL!');}
    If (!$paymentDate)  {error_function('Payment Date cannot NULL!');}
}
if ($category === 'bill') {
    $studentClass = null;
    $billList     = null;
    if (isset($_POST['student_class'])) { $studentClass = $_POST['student_class']; } // Example : X.P-3
    if (isset($_POST['message']))       { $billList     = $_POST['message']; }     // Example : SPP AGU-2023=500,000; SPP SEP-2023=500,000; DAFTAR ULANG JUN-2024=2,315,000; PESAT FESTIVAL OKT-2024=100,000
    If (!$studentClass) {error_function('Student Class cannot NULL!');}
    If (!$billList)     {error_function('Bill List cannot NULL!');}
}

// Set path and payload for the request
$postPath = '/qontak/chat/v1/broadcasts/whatsapp/direct';
if ($category === 'absence') {
    $postPayload = [
        "to_name" => $studentName,
        "to_number" => $phone, // "62812xxx" -> Must use international number 62,63,65, etc
        "message_template_id" => "d7efdea9-a2bd-4cc6-b8eb-9991dd5cad27",
        "channel_integration_id" => "b9ac65e9-02cb-4ae8-bbbf-392d2801267f",
        "language" => ["code" => "id"],
        "parameters" => [
            "body" => [
                ["key" => "1", "value" => "parent_name", "value_text" => $parentName],
                ["key" => "2", "value" => "student_name", "value_text" => $studentName],
                ["key" => "3", "value" => "absence_date", "value_text" => $absenceDate],
                ["key" => "4", "value" => "absence_status", "value_text" => $absenceStatus],
                ["key" => "5", "value" => "absence_remark", "value_text" => $absenceRemark]
            ]
        ]
    ];
}
if ($category === 'payment') {
    $postPayload = [
        "to_name" => $studentName,
        "to_number" => $phone, // "62812xxx" -> Must use international number 62,63,65, etc
        "message_template_id" => "e0b54d80-a4e9-4ab8-9aa2-b37182c2fd6b",
        "channel_integration_id" => "b9ac65e9-02cb-4ae8-bbbf-392d2801267f",
        "language" => ["code" => "id"],
        "parameters" => [
            "body" => [
                ["key" => "1", "value" => "parent_name", "value_text" => $parentName],
                ["key" => "2", "value" => "student_name", "value_text" => $studentName],
                ["key" => "3", "value" => "student_class", "value_text" => $studentClass],
                ["key" => "4", "value" => "payment_info", "value_text" => $paymentInfo],
                ["key" => "5", "value" => "payment_date", "value_text" => $paymentDate]
            ]
        ]
    ];
}
if ($category === 'bill') {
    $postPayload = [
        "to_name" => $studentName,
        "to_number" => $phone, // Replace with the recipient's actual phone number
        "message_template_id" => "f2fc73ff-fa3c-4c91-9667-bd7b918c12b6",
        "channel_integration_id" => "b9ac65e9-02cb-4ae8-bbbf-392d2801267f",
        "language" => ["code" => "id"],
        "parameters" => [
            "body" => [
                ["key" => "1", "value" => "parent_name", "value_text" => $parentName],
                ["key" => "2", "value" => "student_name", "value_text" => $studentName],
                ["key" => "3", "value" => "student_class", "value_text" => $studentClass],
                ["key" => "4", "value" => "bill_list", "value_text" => $billList]
            ]
        ]
    ];
}

$postResult = mekari_request('POST', $postPath, $postPayload);
if ($postResult['http_code'] != 201) {
    error_function('Code not 201!');
}

$broadcastId = $postResult['response']->data->id ?? null;
if (!$broadcastId) {
    error_function('Broadcast ID not found in response!');
}

sleep(10);

$logPath = "/qontak/chat/v1/broadcasts/{$broadcastId}/whatsapp/log";
$logResult = mekari_request('GET', $logPath);

$resJson = [
    'status' => 'success',
    'msg' => 'Success to send broadcast!',
    'detail' => $logResult['response']->data[0],
];
echo json_encode($resJson);
echo PHP_EOL;