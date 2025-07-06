<?php

// websocket_server.php

// این مسیر صحیح است اگر websocket_server.php در /var/www/sso-system/ باشد
// و پوشه vendor هم در /var/www/sso-system/vendor/ باشد.
require __DIR__ . '/vendor/autoload.php';

// مسیر فایل config.php (اگر includes در ریشه است)
require __DIR__ . '/includes/config.php';

// مسیر کلاس Message Handler شما
// اگر PterodactylStatusRelay.php در پوشه includes قرار دارد:
require __DIR__ . '/includes/PterodactylStatusRelay.php';


use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use MySSOApp\PterodactylStatusRelay; // مطمئن شوید namespace در PterodactylStatusRelay.php تعریف شده

// ایجاد یک event loop
$loop = \React\EventLoop\Factory::create();

// ایجاد نمونه از Message Handler و پاس دادن loop به آن
$statusRelay = new PterodactylStatusRelay($loop);

// تنظیم سرور WebSocket
$webSock = new WsServer($statusRelay);
$http = new HttpServer($webSock);
$server = IoServer::factory($http, 8080, '0.0.0.0', $loop);

echo "WebSocket server started on port 8080\n";

$server->run();
