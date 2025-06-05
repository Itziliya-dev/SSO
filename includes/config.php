<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

define('BASE_URL', 'https://sso.itziliya-dev.ir');
define('PANEL_URL', $_ENV['PTERODACTYL_URL']);
define('ADMIN_PANEL_URL', 'https://sso.itziliya-dev.ir/admin');
define('TOKEN_DIR', '/tmp/sso_tokens');

define('PTERODACTYL_URL', $_ENV['PTERODACTYL_URL']);
define('PTERODACTYL_API_KEY_CLIENT', $_ENV['PTERODACTYL_API_KEY_CLIENT']);
define('PTERODACTYL_API_KEY_APPLICATION', $_ENV['PTERODACTYL_API_KEY_APPLICATION']);
define('PTERODACTYL_SERVER_ID', $_ENV['PTERODACTYL_SERVER_ID']);


if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
}
