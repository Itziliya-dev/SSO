<?php
function getDbConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $conn = new mysqli('localhost', 'SSO', '1195451root!', 'sso_db');
        
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            throw new Exception('Database connection error');
        }
        
        $conn->set_charset("utf8mb4");
    }
    
    return $conn;
}
?>