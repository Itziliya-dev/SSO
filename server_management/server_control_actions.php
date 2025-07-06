<?php

// server_control_actions.php

// مسیر config.php را با ساختار پروژه خود تطبیق دهید
// اگر server_control_actions.php در همان پوشه server_control.php است (مثلا server_management)
// و پوشه includes یک سطح بالاتر است
require_once __DIR__.'/../includes/config.php';
// require_once __DIR__.'/../includes/auth_functions.php'; // اگر لازم است

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// بررسی لاگین استف و دسترسی‌های لازم
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_staff']) || !$_SESSION['is_staff']) {
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز (لاگین استف).']);
    exit();
}
$can_access_server_control = $_SESSION['can_access_server_control'] ?? false;
$staff_is_verify = $_SESSION['is_verify'] ?? 0;

if (!$can_access_server_control) {
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز به کنترل سرور.']);
    exit();
}
if (!$staff_is_verify) {
    echo json_encode(['success' => false, 'message' => 'حساب استف شما هنوز تایید نشده است.']);
    exit();
}

// تابع callPterodactylAPI (همان کدی که در پاسخ قبلی برای server_control.php ارائه شد)
// این تابع باید اینجا هم تعریف شود یا از یک فایل مشترک include شود.
// برای جلوگیری از تکرار، فرض می‌کنیم این تابع در یک فایل جداگانه مثلاً pterodactyl_api_functions.php
// قرار دارد و اینجا include می‌شود. یا می‌توانید کل کد تابع را اینجا کپی کنید.
// ********** شروع تابع callPterodactylAPI **********
function callPterodactylAPI($endpoint, $method = 'GET', $data = null, $apiKeyType = 'client')
{
    $url = PTERODACTYL_URL . $endpoint; // از config.php خوانده می‌شود
    $apiKey = ($apiKeyType === 'application') ? PTERODACTYL_API_KEY_APPLICATION : PTERODACTYL_API_KEY_CLIENT; // از config.php

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $headers = [
        "Authorization: Bearer " . $apiKey,
        "Accept: Application/vnd.pterodactyl.v1+json",
    ];

    if (($method === 'POST' || $method === 'PUT')) {
        if ($data) {
            $jsonData = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            $headers[] = "Content-Type: application/json";
            $headers[] = "Content-Length: " . strlen($jsonData);
        } else {
            $headers[] = "Content-Length: 0";
        }
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // فقط برای تست
    // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // فقط برای تست

    $response_body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("cURL Error to Pterodactyl in actions: " . $curlError);
        return ['success' => false, 'message' => 'خطای ارتباط با سرور Pterodactyl (cURL).', 'details' => $curlError, 'http_code' => $httpCode];
    }

    // برای دستورات پاور، 204 یعنی موفقیت
    if ($httpCode == 204 && ($method === 'POST' || $method === 'PUT')) {
        return ['success' => true, 'http_code' => $httpCode, 'message' => 'دستور با موفقیت به سرور Pterodactyl ارسال شد.'];
    }

    $decodedResponse = json_decode($response_body, true);

    if ($httpCode >= 400) {
        $errorMessage = 'خطای عمومی در ارتباط با Pterodactyl.';
        if (isset($decodedResponse['errors'][0]['detail'])) {
            $errorMessage = $decodedResponse['errors'][0]['detail'];
        }
        error_log("Pterodactyl API Error in actions - HTTP Code: $httpCode, Response: $response_body");
        return ['success' => false, 'message' => $errorMessage, 'http_code' => $httpCode, 'details' => $response_body];
    }

    if (json_last_error() !== JSON_ERROR_NONE && !($httpCode == 204 && empty($response_body))) {
        error_log("Pterodactyl API JSON Decode Error in actions. HTTP Code: $httpCode, Response: $response_body");
        return ['success' => false, 'message' => 'پاسخ دریافتی از Pterodactyl معتبر نیست (JSON).', 'details' => $response_body];
    }

    // اگر تا اینجا رسیده و خطا نبوده، success را true می‌کنیم
    if (is_array($decodedResponse)) { // اطمینان از اینکه پاسخ یک آرایه است
        $decodedResponse['success'] = true;
    } elseif ($httpCode < 300) { // اگر کد موفقیت آمیز بود ولی بدنه خالی یا غیر JSON بود
        return ['success' => true, 'http_code' => $httpCode, 'message' => 'عملیات موفق (بدون محتوای خاص).'];
    } else { // حالت ناشناخته
        return ['success' => false, 'http_code' => $httpCode, 'message' => 'پاسخ ناشناخته از سرور Pterodactyl.', 'details' => $response_body];
    }

    return $decodedResponse;
}
// ********** پایان تابع callPterodactylAPI **********


$action = $_POST['action'] ?? $_GET['action'] ?? null;
$response_output = ['success' => false, 'message' => 'اقدام نامشخص.'];

switch ($action) {
    case 'get_status':
        $serverResources = callPterodactylAPI("/api/client/servers/" . PTERODACTYL_SERVER_ID . "/resources");
        $fullServerInfo = callPterodactylAPI("/api/client/servers/" . PTERODACTYL_SERVER_ID);

        $networkInfo = ['ip' => 'نامشخص', 'port' => 'نامشخص'];
        $serverStatusText = 'نامشخص';
        $currentStateRaw = 'unknown';
        $ramUsage = 0;
        $cpuUsage = 0;

        if ($serverResources && isset($serverResources['success']) && $serverResources['success'] && isset($serverResources['attributes'])) {
            $attributes = $serverResources['attributes'];
            $currentStateRaw = $attributes['current_state'] ?? 'unknown';
            $serverStatusText = match ($currentStateRaw) {
                'running' => 'آنلاین',
                'starting' => 'در حال راه‌اندازی',
                'stopping' => 'در حال خاموش شدن',
                'offline' => 'آفلاین',
                default => htmlspecialchars($currentStateRaw),
            };
            $ramUsage = round(($attributes['resources']['memory_bytes'] ?? 0) / (1024 * 1024), 2);
            $cpuUsage = round($attributes['resources']['cpu_absolute'] ?? 0, 2);
        } elseif (isset($serverResources['message'])) {
            $serverStatusText = "خطا در منابع: " . htmlspecialchars($serverResources['message']);
        }


        if ($fullServerInfo && isset($fullServerInfo['success']) && $fullServerInfo['success'] && isset($fullServerInfo['attributes']['relationships']['allocations']['data'][0]['attributes'])) {
            $mainAllocation = $fullServerInfo['attributes']['relationships']['allocations']['data'][0]['attributes'];
            $networkInfo['ip'] = htmlspecialchars($mainAllocation['ip_alias'] ?? $mainAllocation['ip']);
            $networkInfo['port'] = htmlspecialchars($mainAllocation['port']);
        } elseif (isset($fullServerInfo['message'])) {
            error_log("Pterodactyl Full Info Fetch Error: " . $fullServerInfo['message']);
        }


        $response_output = [
            'success' => true, // حتی اگر بخشی از اطلاعات ناقص باشد، خود درخواست get_status موفق بوده
            'current_state_raw' => $currentStateRaw,
            'current_state_html' => $serverStatusText, // متن ساده برای نمایش در JS
            'network_ip' => $networkInfo['ip'],
            'network_port' => $networkInfo['port'],
            'ram_usage_mb' => $ramUsage,
            'cpu_usage_percent' => $cpuUsage,
        ];
        break;

    case 'start':
    case 'stop':
    case 'restart':
        $signal = $action;
        $powerActionResponse = callPterodactylAPI("/api/client/servers/" . PTERODACTYL_SERVER_ID . "/power", 'POST', ['signal' => $signal]);
        if ($powerActionResponse && $powerActionResponse['success']) {
            $response_output = ['success' => true, 'message' => "دستور " . htmlspecialchars($action) . " با موفقیت ارسال شد."];
        } else {
            $response_output = ['success' => false, 'message' => $powerActionResponse['message'] ?? "خطا در ارسال دستور " . htmlspecialchars($action) . "."];
        }
        break;

    default:
        $response_output = ['success' => false, 'message' => 'اقدام درخواستی معتبر نیست.'];
        break;
}

echo json_encode($response_output);
