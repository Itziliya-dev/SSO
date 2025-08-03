<?php

require_once __DIR__.'/../../includes/config.php';
require_once __DIR__.'/../../includes/database.php';

header('Content-Type: application/json');

session_start();

// امن‌تر است که برای دسترسی به این بخش، یک دسترسی مشخص‌تر را چک کنیم.
// فعلاً بر اساس کد شما، همان is_owner باقی می‌ماند.
if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'متد درخواست نامعتبر']);
    exit();
}

try {
    $action = $_POST['action'] ?? '';
    $request_id = $_POST['id'] ?? 0;

    if (!in_array($action, ['approve', 'reject', 'staff'], true)) {
        throw new Exception('عملیات نامعتبر');
    }

    $conn = getDbConnection();
    // شروع تراکنش برای اطمینان از انجام کامل عملیات
    $conn->begin_transaction();

    // دریافت اطلاعات درخواست
    $stmt = $conn->prepare("SELECT * FROM registration_requests WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();

    if (!$request) {
        throw new Exception('درخواست یافت نشد');
    }

    if ($action === 'approve') {
        // مرحله ۱: ایجاد کاربر جدید در جدول users
        $stmt = $conn->prepare("INSERT INTO users 
            (username, password, email, phone, created_at, created_by, fullname, status) 
            VALUES (?, ?, ?, ?, NOW(), 'manual', ?, 'active')");
        $stmt->bind_param(
            "sssss",
            $request['username'],
            $request['password'],
            $request['email'],
            $request['phone'],
            $request['fullname']
        );
        $stmt->execute();

        // مرحله ۲: دریافت ID کاربر جدید
        $new_user_id = $conn->insert_id;

        // مرحله ۳: ایجاد رکورد دسترسی برای کاربر جدید با دسترسی‌های غیرفعال
        if ($new_user_id) {
            $stmt_perm = $conn->prepare("INSERT INTO user_permissions (user_id, has_user_panel, is_owner, has_developer_access) VALUES (?, FALSE, FALSE, FALSE)");
            $stmt_perm->bind_param("i", $new_user_id);
            $stmt_perm->execute();
        } else {
            throw new Exception('خطا در ایجاد کاربر جدید.');
        }

        // به‌روزرسانی وضعیت درخواست
        $stmt_update = $conn->prepare("UPDATE registration_requests SET status = 'approved', processed_at = NOW(), processed_by = ? WHERE id = ?");
        $stmt_update->bind_param("si", $_SESSION['username'], $request_id);
        $stmt_update->execute();

    } elseif ($action === 'staff') {
        // مرحله ۱: انتقال اطلاعات به جدول staff-manage
        $stmt = $conn->prepare("INSERT INTO `staff-manage` 
            (username, password, email, phone, fullname, age, discord_id, steam_id, tracking_code, created_at, is_active, is_verify) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1, 0)");
        $stmt->bind_param(
            "sssssisss",
            $request['username'],
            $request['password'],
            $request['email'],
            $request['phone'],
            $request['fullname'],
            $request['age'],
            $request['discord_id'],
            $request['steam_id'],
            $request['tracking_code']
        );
        $stmt->execute();

        // مرحله ۲: دریافت ID کارمند جدید
        $new_staff_id = $conn->insert_id;

        // مرحله ۳: ایجاد رکورد دسترسی برای کارمند جدید با دسترسی‌های غیرفعال
        if ($new_staff_id) {
            $stmt_perm = $conn->prepare("INSERT INTO staff_permissions (staff_id, has_user_panel, is_owner, has_developer_access) VALUES (?, FALSE, FALSE, FALSE)");
            $stmt_perm->bind_param("i", $new_staff_id);
            $stmt_perm->execute();
        } else {
            throw new Exception('خطا در ایجاد کارمند جدید.');
        }
    if ($new_staff_id) {
        $update_stmt = $conn->prepare("UPDATE `staff-manage` SET user_id = ? WHERE id = ?");
        $update_stmt->bind_param("ii", $new_staff_id, $new_staff_id);
        $update_stmt->execute();
    } else {
        throw new Exception('خطا در ایجاد کارمند جدید.');
    }
        // به‌روزرسانی وضعیت درخواست
        $stmt_update = $conn->prepare("UPDATE registration_requests SET status = 'staff', processed_at = NOW(), processed_by = ? WHERE id = ?");
        $stmt_update->bind_param("si", $_SESSION['username'], $request_id);
        $stmt_update->execute();

    } else { // 'reject'
        // رد درخواست
        $stmt = $conn->prepare("UPDATE registration_requests SET status = 'rejected', processed_at = NOW(), processed_by = ? WHERE id = ?");
        $stmt->bind_param("si", $_SESSION['username'], $request_id);
        $stmt->execute();
    }
    
    // اگر همه چیز موفق بود، تراکنش را تایید کن
    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // در صورت بروز خطا، تمام تغییرات را لغو کن
    if (isset($conn) && $conn->errno) {
        $conn->rollback();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    // بستن اتصال
    if (isset($conn)) {
        $conn->close();
    }
}