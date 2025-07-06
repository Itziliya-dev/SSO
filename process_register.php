<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit();
}

try {
    // Receive and validate data
    $fullname   = trim($_POST['fullname']);
    $username   = trim($_POST['username']);
    $password   = $_POST['password'];
    $email      = trim($_POST['email']);
    $phone      = trim($_POST['phone']);
    $age        = (int)$_POST['age'];
    $discord_id = trim($_POST['discord_id']);
    $steam_id   = trim($_POST['steam_id']);

    // Initial validation
    if (empty($fullname)) {
        throw new Exception('Full name is required');
    }
    if (empty($username)) {
        throw new Exception('Username is required');
    }
    if (strlen($password) < 6) {
        throw new Exception('Password must be at least 6 characters');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email');
    }
    if (!preg_match('/^09\d{9}$/', $phone)) {
        throw new Exception('Invalid phone number');
    }
    if ($age < 13 || $age > 100) {
        throw new Exception('Age must be between 13 and 100 years old');
    }
    if (empty($discord_id)) {
        throw new Exception('Discord ID is required');
    }
    if (empty($steam_id)) {
        throw new Exception('Steam ID is required');
    }

    // Check if username or email already exists in the users table
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT id FROM registration_requests WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        throw new Exception('این مشخصات قبلا ثبت شده است');
    }

    // Generate tracking code
    $tracking_code = 'SSO-' . strtoupper(substr(md5(uniqid()), 0, 8));

    // Save request to the database
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO registration_requests
        (fullname, username, password, email, phone, age, discord_id, steam_id, tracking_code, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
    // Bind the 9 parameters: fullname, username, password, email, phone, age, discord_id, steam_id, tracking_code
    $stmt->bind_param("sssssisss", $fullname, $username, $hashed_password, $email, $phone, $age, $discord_id, $steam_id, $tracking_code);
    $stmt->execute();

    // Redirect to the registration page with a success message and tracking code
    header('Location: register.php?success=' . urlencode('درخواست شما با موفقیت ثبت شد') . '&tracking_code=' . urlencode($tracking_code));
    exit();

} catch (Exception $e) {
    header('Location: register.php?error=' . urlencode($e->getMessage()));
    exit();
}
