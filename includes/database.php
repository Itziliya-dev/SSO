<?php

// فایل: includes/database.php
// کد کامل و اصلاح شده تابع getDbConnection
function getDbConnection()
{
    static $conn = null;
    if ($conn === null) {
        try {
            $host = $_ENV['DB_HOST'];
            $username = $_ENV['DB_USERNAME'] ?? null;
            $password = $_ENV['DB_PASSWORD'] ?? null;
            $dbname = $_ENV['DB_DATABASE'] ?? null;
            $port = (int)($_ENV['DB_PORT'] ?? 3306);

            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $conn = new mysqli($host, $username, $password, $dbname, $port);
            $conn->set_charset("utf8mb4");

        } catch (mysqli_sql_exception $e) {
            // لاگ کردن خطای اصلی برای خودت
            error_log('Database Connection Error: ' . $e->getMessage());
            // ✅ هدایت کاربر به صفحه خطای جدید
            header('Location: /database-error');
            exit();
        }
    }
    return $conn;
}
