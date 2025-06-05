<?php

// این خطوط برای نمایش خطا در محیط تست مفید هستند
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// مسیرهای include را با ساختار پروژه خود تطبیق دهید
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/auth_functions.php';

// session_start() باید قبل از هرگونه دسترسی به $_SESSION فراخوانی شود
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json'); // این هدر مهم است

// 1. بررسی دسترسی ادمین (اولین و مهمترین بررسی)
if (!isset($_SESSION['is_owner']) || $_SESSION['is_owner'] != true) { // بررسی دقیق‌تر مقدار is_owner
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز (شما مدیر نیستید یا سشن منقضی شده).']);
    exit();
}

// 2. بررسی متد درخواست و پارامترها
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'متد درخواست باید POST باشد.']);
    exit();
}

if (!isset($_POST['staff_id'])) {
    echo json_encode(['success' => false, 'message' => 'شناسه استف ارسال نشده است.']);
    exit();
}

$staff_id = filter_var($_POST['staff_id'], FILTER_VALIDATE_INT);
if ($staff_id === false || $staff_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'شناسه استف نامعتبر است.']);
    exit();
}

// اگر تمام بررسی‌های اولیه موفقیت‌آمیز بود، ادامه دهید:
$conn = null;
try {
    $conn = getDbConnection();
    if (!$conn) {
        throw new Exception("عدم موفقیت در اتصال به دیتابیس.");
    }
    // اگر از mysqli استفاده می‌کنید و getDbConnection ممکن است خطا برگرداند:
    if ($conn instanceof mysqli && $conn->connect_error) {
        throw new Exception("خطای اتصال دیتابیس: " . $conn->connect_error);
    }

    // کوئری آپدیت
    $stmt = $conn->prepare("UPDATE `staff-manage` SET is_verify = 1, updated_at = NOW() WHERE id = ?");
    if ($stmt === false) {
        throw new Exception("خطا در آماده‌سازی کوئری آپدیت: " . $conn->error);
    }

    $stmt->bind_param("i", $staff_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'استف با موفقیت تایید شد.']);
        } else {
            // بررسی اینکه آیا استف وجود داشته یا قبلا تایید شده
            $check_stmt = $conn->prepare("SELECT is_verify FROM `staff-manage` WHERE id = ?");
            if ($check_stmt) {
                $check_stmt->bind_param("i", $staff_id);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    if ($row['is_verify'] == 1) {
                        echo json_encode(['success' => true, 'message' => 'این استف قبلاً تایید شده است.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'تغییری در وضعیت استف ایجاد نشد.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'استف با این شناسه یافت نشد.']);
                }
                $check_stmt->close();
            } else {
                echo json_encode(['success' => false, 'message' => 'خطا در بررسی وضعیت فعلی.']);
            }
        }
    } else {
        throw new Exception("خطا در اجرای کوئری آپدیت: " . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    error_log("Verify Staff AJAX Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطای سرور: ' . $e->getMessage() // برای دیباگ، پیام اصلی خطا را هم برگردانید
    ]);
} finally {
    if ($conn && $conn instanceof mysqli && $conn->thread_id) {
        $conn->close();
    }
}
