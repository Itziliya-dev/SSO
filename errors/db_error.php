<?php
function can_connect_to_db() {
    $env_path = __DIR__ . '/../.env';
    if (!file_exists($env_path)) return false;

    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env_vars = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($name, $value) = array_map('trim', explode('=', $line, 2));
            $env_vars[$name] = trim($value, '"');
        }
    }

    $host = $env_vars['DB_HOST'] ?? 'localhost';
    $user = $env_vars['DB_USERNAME'] ?? '';
    $pass = $env_vars['DB_PASSWORD'] ?? '';
    $db   = $env_vars['DB_DATABASE'] ?? '';
    $port = (int)($env_vars['DB_PORT'] ?? 3306);
    

    try {
        mysqli_report(MYSQLI_REPORT_OFF); 
        @$mysqli = new mysqli($host, $user, $pass, $db, $port);
        if ($mysqli->connect_error) {
            return false;
        }
        $mysqli->close();
        return true;
    } catch (Exception $e) {
        return false;
    }
}


if (can_connect_to_db()) {
    header('Location: /');
    exit();
}

http_response_code(503); // Service Unavailable
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خطا در اتصال به دیتابیس</title>
    <link rel="stylesheet" href="/assets/css/error_page.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/fonts/Vazirmatn-font-face.css">
</head>
<body>
    <div class="error-container">
        <i class="fas fa-database error-icon"></i>
        <h1 class="error-title">خطا در اتصال به دیتابیس</h1>
        <p class="error-message">
            در حال حاضر امکان برقراری ارتباط با سرویس دیتابیس وجود ندارد. لطفاً پس از اطمینان از حل مشکل، صفحه را رفرش کنید.
        </p>
    </div>
</body>
</html>