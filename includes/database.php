<?php
// فایل: includes/database.php

function getDbConnection() {
    static $conn = null;
    
    if ($conn === null) {
        // خواندن اطلاعات اتصال از متغیرهای محیطی
        $host = $_ENV['DB_HOST'];
        $username = $_ENV['DB_USERNAME'];
        $password = $_ENV['DB_PASSWORD'];
        $dbname = $_ENV['DB_DATABASE'];
        
        $conn = new mysqli($host, $username, $password, $dbname);
        
        if ($conn->connect_error) {
            // لاگ کردن خطا بدون نمایش به کاربر
            error_log("Database connection failed: " . $conn->connect_error);
            // پرتاب یک استثناء عمومی
            throw new Exception('Database service is currently unavailable.');
        }
        
        $conn->set_charset("utf8mb4");
    }
    
    return $conn;
}