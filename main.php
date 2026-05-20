<?php

require 'vendor/autoload.php';

use Carbon\Carbon;
use Dotenv\Dotenv;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\ClientException;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Load .env file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

/// Log channel
$waError = new Logger('whatsapp_error');
$waSuccess = new Logger('whatsapp_success');
$waError->pushHandler(new StreamHandler('logs/errors.log', Level::Error));
$waSuccess->pushHandler(new StreamHandler('logs/success.log', Level::Info));

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
function mekari_request($method,$path,$waError,$payload = NULL) {
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
        error_function('Error Send Request to Mekari!',$detail,$waError);
    }

    return [
        'response' => json_decode($response->getBody()->getContents()),
        'http_code' => $response->getStatusCode()
    ];
}

function error_function($msg,$waError,$detail = NULL) {
    $resJson = [
        'status' => 'error',
        'msg' => $msg,
        'detail' => $detail,
    ];
    $rJson = json_encode($resJson);
    $waError->error($rJson);
    echo $rJson;
    echo PHP_EOL;
}

// Set request
$category    = null;
$phone       = null;
$studentName = null;
$school      = 'SMA Plus PGRI Cibinong';
$schoolYear  = 'Tahun Ajaran 2026/2027';
if (isset($_POST['category']))     { $category    = $_POST['category']; }     // Example : absence
if (isset($_POST['phone']))        { $phone       = $_POST['phone']; }        // Example : 6281284420481
if (isset($_POST['student_name'])) { $studentName = $_POST['student_name']; } // Example : FERNANDES SIREGAR
If (!in_array($category,['absence','payment','bill','ppdb_regist','ppdb_accepted','ppdb_pay'])) {error_function('Category cannot NULL!',$waError);}
If (!$phone)       {error_function('Phone cannot NULL!',$waError);}
If (!$studentName) {error_function('Student Name cannot NULL!',$waError);}

if ($category === 'absence') {
    $parentName    = null;
    $absenceDate   = null;
    $absenceStatus = null;
    $absenceRemark = null;
    if (isset($_POST['parent_name']))    { $parentName  = $_POST['parent_name']; }  // Example : ANDRI SIREGAR/NANA SIREGAR
    if (isset($_POST['trans_date']))     { $absenceDate   = $_POST['trans_date']; }   // Example : 18-Okt-2025
    if (isset($_POST['absence_status'])) { $absenceStatus = $_POST['absence_status']; } // Example : SAKIT
    if (isset($_POST['absence_remark'])) { $absenceRemark = $_POST['absence_remark']; } // Example : Info dari guru kelas
    If (!$parentName)    {error_function('Parent Name cannot NULL!',$waError);}
    If (!$absenceDate)   {error_function('Absence Date cannot NULL!',$waError);}
    If (!$absenceStatus) {error_function('Absence Status cannot NULL!',$waError);}
    If (!$absenceRemark) {error_function('Absence Remark cannot NULL!',$waError);}
    $parentName = str_replace('Yth.', '', $parentName);
    $parentName = str_replace('Yth ', '', $parentName);
    $parentName = str_replace('Yth. ', '', $parentName);
}
if ($category === 'payment') {
    $parentName   = null;
    $studentClass = null;
    $paymentInfo  = null;
    $paymentDate = null;
    if (isset($_POST['parent_name']))   { $parentName   = $_POST['parent_name']; }   // Example : ANDRI SIREGAR/NANA SIREGAR
    if (isset($_POST['student_class'])) { $studentClass = $_POST['student_class']; } // Example : XI.R-3
    if (isset($_POST['message']))       { $paymentInfo  = $_POST['message']; }  // Example : SPP Nov-2025: 600,000
    if (isset($_POST['trans_date']))    { $paymentDate  = $_POST['trans_date']; }  // Example : 29-Okt-2025
    if (!$parentName)   {error_function('Parent Name cannot NULL!',$waError);}
    If (!$studentClass) {error_function('Student Class cannot NULL!',$waError);}
    If (!$paymentInfo)  {error_function('Payment Info cannot NULL!',$waError);}
    If (!$paymentDate)  {error_function('Payment Date cannot NULL!',$waError);}
    $parentName = str_replace('Yth.', '', $parentName);
    $parentName = str_replace('Yth ', '', $parentName);
    $parentName = str_replace('Yth. ', '', $parentName);
}
if ($category === 'bill') {
    $parentName   = null;
    $studentClass = null;
    $billList     = null;
    if (isset($_POST['parent_name']))   { $parentName   = $_POST['parent_name']; }   // Example : ANDRI SIREGAR/NANA SIREGAR
    if (isset($_POST['student_class'])) { $studentClass = $_POST['student_class']; } // Example : X.P-3
    if (isset($_POST['message']))       { $billList     = $_POST['message']; }     // Example : SPP AGU-2023=500,000; SPP SEP-2023=500,000; DAFTAR ULANG JUN-2024=2,315,000; PESAT FESTIVAL OKT-2024=100,000
    If (!$parentName)   {error_function('Parent Name cannot NULL!',$waError);}
    If (!$studentClass) {error_function('Student Class cannot NULL!',$waError);}
    If (!$billList)     {error_function('Bill List cannot NULL!',$waError);}
    $parentName = str_replace('Yth.', '', $parentName);
    $parentName = str_replace('Yth ', '', $parentName);
    $parentName = str_replace('Yth. ', '', $parentName);
}
if ($category === 'ppdb_regist') {
    $noRegist = null;
    if (isset($_POST['no_regist'])) { $noRegist = $_POST['no_regist']; }
    If (!$noRegist) {error_function('No Regist cannot NULL!',$waError);}
}
if ($category === 'ppdb_accepted') {
    $noRegist          = null;
    $group             = null;
    $bankAccountNumber = null;
    $maxPayDate        = null;
    if (isset($_POST['no_regist']))           { $noRegist = $_POST['no_regist']; }
    if (isset($_POST['group']))               { $group = $_POST['group']; }
    if (isset($_POST['bank_account_number'])) { $bankAccountNumber = $_POST['bank_account_number']; }
    if (isset($_POST['max_pay_date']))        { $maxPayDate = $_POST['max_pay_date']; }
    If (!$noRegist)          {error_function('No Regist cannot NULL!',$waError);}
    If (!$group)             {error_function('Group cannot NULL!',$waError);}
    If (!$bankAccountNumber) {error_function('Bank Account Number cannot NULL!',$waError);}
    If (!$maxPayDate)        {error_function('Max Pay Date cannot NULL!',$waError);}
}
if ($category === 'ppdb_pay') {
    $noRegist = null;
    $nominal  = null;
    if (isset($_POST['no_regist'])) { $noRegist = $_POST['no_regist']; }
    if (isset($_POST['nominal']))   { $nominal = $_POST['nominal']; }
    If (!$noRegist) {error_function('No Regist cannot NULL!',$waError);}
    If (!$nominal)  {error_function('Nominal cannot NULL!',$waError);}
}
// Set path and payload for the request
$postPath = '/qontak/chat/v1/broadcasts/whatsapp/direct';
if ($category === 'absence') {
    $postPayload = [
        "to_name" => $studentName,
        "to_number" => $phone, // "62812xxx" -> Must use international number 62,63,65, etc
        "message_template_id" => "1a386ce8-7583-485d-b737-7a0be154aa86",
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
        "message_template_id" => "e1f675b5-63ed-4b41-a5d4-9b781289b183",
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
        "message_template_id" => "b09e7185-1093-41e0-a3e6-872aac0dc4d6",
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
if ($category === 'ppdb_regist') {
    $postPayload = [
        "to_name" => $studentName,
        "to_number" => $phone, // "62812xxx" -> Must use international number 62,63,65, etc
        "message_template_id" => "008eba6b-6acc-4cab-aec4-7c19a183c2d4",
        "channel_integration_id" => "b9ac65e9-02cb-4ae8-bbbf-392d2801267f",
        "language" => ["code" => "id"],
        "parameters" => [
            "body" => [
                ["key" => "1", "value" => "school", "value_text" => $school],
                ["key" => "2", "value" => "no_regist", "value_text" => $noRegist],
                ["key" => "3", "value" => "student_name", "value_text" => $studentName]
            ]
        ]
    ];
}
if ($category === 'ppdb_accepted') {
    $postPayload = [
        "to_name" => $studentName,
        "to_number" => $phone, // "62812xxx" -> Must use international number 62,63,65, etc
        "message_template_id" => "fd01cc07-d878-4582-a4f7-cf9b9d42e3a1",
        "channel_integration_id" => "b9ac65e9-02cb-4ae8-bbbf-392d2801267f",
        "language" => ["code" => "id"],
        "parameters" => [
            "body" => [
                ["key" => "1", "value" => "no_regist", "value_text" => $noRegist],
                ["key" => "2", "value" => "student_name", "value_text" => $studentName],
                ["key" => "3", "value" => "group", "value_text" => $group],
                ["key" => "4", "value" => "school", "value_text" => $school],
                ["key" => "5", "value" => "school_year", "value_text" => $schoolYear],
                ["key" => "6", "value" => "bank_account_number", "value_text" => $bankAccountNumber],
                ["key" => "7", "value" => "max_pay_date", "value_text" => $maxPayDate]
            ]
        ]
    ];
}
if ($category === 'ppdb_pay') {
    $postPayload = [
        "to_name" => $studentName,
        "to_number" => $phone, // "62812xxx" -> Must use international number 62,63,65, etc
        "message_template_id" => "004f79dd-ded2-4ddc-bad5-de564c3acc37",
        "channel_integration_id" => "b9ac65e9-02cb-4ae8-bbbf-392d2801267f",
        "language" => ["code" => "id"],
        "parameters" => [
            "body" => [
                ["key" => "1", "value" => "no_regist", "value_text" => $noRegist],
                ["key" => "2", "value" => "student_name", "value_text" => $studentName],
                ["key" => "3", "value" => "nominal", "value_text" => $nominal]
            ]
        ]
    ];
}

$postResult = mekari_request('POST', $postPath, $waError,$postPayload);
if ($postResult['http_code'] != 201) {
    error_function('Code not 201!',$waError);
}

$broadcastId = $postResult['response']->data->id ?? null;
if (!$broadcastId) {
    error_function('Broadcast ID not found in response!',$waError);
}

sleep(10);

$logPath = "/qontak/chat/v1/broadcasts/{$broadcastId}/whatsapp/log";
$logResult = mekari_request('GET', $logPath,$waError);

$resJson = [
    'status' => 'success',
    'msg' => 'Success to send broadcast!',
    'detail' => $logResult['response']->data[0],
];
$rJsonSuccess = json_encode($resJson);
$waSuccess->info($rJsonSuccess);
echo $rJsonSuccess;
echo PHP_EOL;