<?php
namespace MySSOApp; // یک namespace برای برنامه خود انتخاب کنید

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\LoopInterface; // برای کارهای دوره‌ای

class PterodactylStatusRelay implements MessageComponentInterface {
    protected $clients;
    protected $loop;
    protected $pterodactylServerId;
    protected $pterodactylApiKey;
    protected $pterodactylUrl;

    public function __construct(LoopInterface $loop) {
        $this->clients = new \SplObjectStorage;
        $this->loop = $loop;

        // خواندن تنظیمات Pterodactyl از فایل config.php یا به صورت مستقیم
        // مطمئن شوید config.php شما شامل این ثابت‌هاست و require شده
        if (!defined('PTERODACTYL_SERVER_ID') || !defined('PTERODACTYL_API_KEY_CLIENT') || !defined('PTERODACTYL_URL')) {
            die("خطا: ثابت‌های Pterodactyl در config.php تعریف نشده‌اند.\n");
        }
        $this->pterodactylServerId = PTERODACTYL_SERVER_ID;
        $this->pterodactylApiKey = PTERODACTYL_API_KEY_CLIENT;
        $this->pterodactylUrl = PTERODACTYL_URL;

        echo "PterodactylStatusRelay started for server: " . $this->pterodactylServerId . "\n";

        // اجرای تابع بررسی وضعیت به صورت دوره‌ای (مثلاً هر 2 ثانیه)
        $this->loop->addPeriodicTimer(2, function () {
            $this->broadcastServerStatus();
        });
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
        // می‌توانید وضعیت اولیه را بلافاصله پس از اتصال ارسال کنید
        $this->sendCurrentStatusToClient($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        // در این سناریو، ما انتظار پیامی از کلاینت نداریم،
        // اما می‌توانید برای درخواست‌های خاص از آن استفاده کنید.
        echo "Received message from {$from->resourceId}: {$msg}\n";
        // مثال: اگر کلاینت پیامی برای درخواست وضعیت اولیه بفرستد
        if (strtolower(trim($msg)) === 'get_status') {
            $this->sendCurrentStatusToClient($from);
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    protected function fetchPterodactylStatus() {
        // این تابع باید وضعیت را از Pterodactyl API بگیرد
        // مشابه تابع callPterodactylAPI که قبلاً داشتیم، اما اینجا برای سادگی خلاصه شده
        // و فقط بخش خواندن منابع و اطلاعات پایه را در نظر می‌گیریم

        $resourcesEndpoint = "/api/client/servers/" . $this->pterodactylServerId . "/resources";
        $fullInfoEndpoint = "/api/client/servers/" . $this->pterodactylServerId;

        $statusData = [
            'success' => false,
            'current_state_raw' => 'unknown',
            'current_state_html' => 'نامشخص',
            'network_ip' => 'نامشخص',
            'network_port' => 'نامشخص',
            'ram_usage_mb' => 0,
            'cpu_usage_percent' => 0,
        ];

        // --- فراخوانی API برای منابع ---
        $ch_resources = curl_init();
        curl_setopt($ch_resources, CURLOPT_URL, $this->pterodactylUrl . $resourcesEndpoint);
        curl_setopt($ch_resources, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch_resources, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $this->pterodactylApiKey,
            "Accept: Application/vnd.pterodactyl.v1+json",
        ]);
        curl_setopt($ch_resources, CURLOPT_CONNECTTIMEOUT, 5); // زمان کمتر برای polling
        curl_setopt($ch_resources, CURLOPT_TIMEOUT, 10);

        $response_resources = curl_exec($ch_resources);
        $http_code_resources = curl_getinfo($ch_resources, CURLINFO_HTTP_CODE);
        if (curl_errno($ch_resources)) {
             error_log("cURL Error (resources) in PterodactylStatusRelay: " . curl_error($ch_resources));
        }
        curl_close($ch_resources);

        if ($http_code_resources == 200 && $response_resources) {
            $decoded_resources = json_decode($response_resources, true);
            if ($decoded_resources && isset($decoded_resources['attributes'])) {
                $attributes = $decoded_resources['attributes'];
                $statusData['success'] = true; // حداقل بخشی از داده‌ها دریافت شد
                $statusData['current_state_raw'] = $attributes['current_state'] ?? 'unknown';
                $statusData['current_state_html'] = match ($statusData['current_state_raw']) {
                    'running' => 'آنلاین',
                    'starting' => 'در حال راه‌اندازی',
                    'stopping' => 'در حال خاموش شدن',
                    'offline' => 'آفلاین',
                    default => htmlspecialchars($statusData['current_state_raw']),
                };
                $statusData['ram_usage_mb'] = round(($attributes['resources']['memory_bytes'] ?? 0) / (1024*1024) , 2);
                $statusData['cpu_usage_percent'] = round($attributes['resources']['cpu_absolute'] ?? 0, 2);
            }
        }

        // --- فراخوانی API برای اطلاعات کامل (IP و Port) ---
        // (این بخش را می‌توانید فقط یکبار در onOpen یا با فرکانس کمتر اجرا کنید اگر IP/Port تغییر نمی‌کنند)
        $ch_full = curl_init();
        curl_setopt($ch_full, CURLOPT_URL, $this->pterodactylUrl . $fullInfoEndpoint);
        curl_setopt($ch_full, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch_full, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $this->pterodactylApiKey,
            "Accept: Application/vnd.pterodactyl.v1+json",
        ]);
        curl_setopt($ch_full, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch_full, CURLOPT_TIMEOUT, 10);
        $response_full = curl_exec($ch_full);
        $http_code_full = curl_getinfo($ch_full, CURLINFO_HTTP_CODE);
         if (curl_errno($ch_full)) {
             error_log("cURL Error (full info) in PterodactylStatusRelay: " . curl_error($ch_full));
        }
        curl_close($ch_full);

        if ($http_code_full == 200 && $response_full) {
            $decoded_full = json_decode($response_full, true);
            if ($decoded_full && isset($decoded_full['attributes']['relationships']['allocations']['data'][0]['attributes'])) {
                $mainAllocation = $decoded_full['attributes']['relationships']['allocations']['data'][0]['attributes'];
                $statusData['network_ip'] = htmlspecialchars($mainAllocation['ip_alias'] ?? $mainAllocation['ip']);
                $statusData['network_port'] = htmlspecialchars($mainAllocation['port']);
            }
        }
        return $statusData;
    }

    public function broadcastServerStatus() {
        $statusData = $this->fetchPterodactylStatus();
        $jsonData = json_encode($statusData);

        // echo "Broadcasting status: " . $jsonData . "\n"; // برای دیباگ سرور
        foreach ($this->clients as $client) {
            $client->send($jsonData);
        }
    }

    public function sendCurrentStatusToClient(ConnectionInterface $conn) {
        $statusData = $this->fetchPterodactylStatus();
        $conn->send(json_encode($statusData));
    }
}