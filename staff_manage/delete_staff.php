<?php
// ۱. تنظیم هدر برای پاسخ JSON (ضروری برای ارتباط با جاوا اسکریپت)
header('Content-Type: application/json');

// ۲. فراخوانی فایل‌های اصلی
// نکته: مسیر ممکن است بسته به ساختار پوشه‌های شما نیاز به تغییر داشته باشد
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/auth_functions.php';

// ۳. شروع نشست (Session) برای دسترسی به اطلاعات کاربر لاگین کرده
session_start();

// ۴. آماده‌سازی آرایه پاسخ برای ارسال به جاوا اسکریپت
$response = [
    'success' => false,
    'message' => 'یک خطای ناشناخته رخ داده است.'
];

// ۵. بررسی‌های امنیتی
// آیا کاربر لاگین کرده و ادمین است؟
if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    $response['message'] = 'دسترسی غیرمجاز! شما باید ادمین باشید.';
    echo json_encode($response);
    exit();
}

// آیا متد درخواست POST است؟
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'متد درخواست نامعتبر است.';
    echo json_encode($response);
    exit();
}

// آیا شناسه استف ارسال شده و معتبر است؟
if (!isset($_POST['staff_id']) || !is_numeric($_POST['staff_id'])) {
    $response['message'] = 'شناسه استف نامعتبر یا ارسال نشده است.';
    echo json_encode($response);
    exit();
}


// ۶. دریافت اطلاعات از فرم و سشن
$staff_id = (int)$_POST['staff_id'];
$reason = !empty($_POST['reason']) ? $_POST['reason'] : 'دلیلی ذکر نشده است.';

// دریافت نام کاربری ادمین از سشن برای ثبت در دیتابیس
$admin_username = $_SESSION['username'] ?? 'system'; // اگر سشن نام کاربری نداشت، 'system' ثبت می‌شود


// ۷. عملیات اصلی پایگاه داده (حذف و آرشیو)
try {
    $conn = getDbConnection();
    
    // شروع یک تراکنش (Transaction) برای اطمینان از سلامت داده‌ها
    // با این کار، یا تمام عملیات با هم انجام می‌شوند یا هیچکدام
    $conn->begin_transaction();

    // قدم اول: اطلاعات کامل استف را از جدول اصلی می‌خوانیم
    $select_stmt = $conn->prepare("SELECT * FROM `staff-manage` WHERE id = ?");
    $select_stmt->bind_param("i", $staff_id);
    $select_stmt->execute();
    $staff_to_delete = $select_stmt->get_result()->fetch_assoc();

    if ($staff_to_delete) {
        // قدم دوم: اطلاعات خوانده شده را در جدول آرشیو (deleted_staff) درج می‌کنیم
        $archive_stmt = $conn->prepare("
            INSERT INTO deleted_staff (
                original_id, fullname, username, email, phone, age, 
                discord_id, steam_id, permissions, joined_at, 
                delete_reason, deleted_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $archive_stmt->bind_param("issssissssss",
            $staff_to_delete['id'],
            $staff_to_delete['fullname'],
            $staff_to_delete['username'],
            $staff_to_delete['email'],
            $staff_to_delete['phone'],
            $staff_to_delete['age'],
            $staff_to_delete['discord_id'],
            $staff_to_delete['steam_id'],
            $staff_to_delete['permissions'],
            $staff_to_delete['created_at'], // `created_at` از جدول اصلی
            $reason,
            $admin_username // نام کاربری ادمین که از سشن گرفته شد
        );
        $archive_stmt->execute();

        // قدم سوم: حالا استف را از جدول اصلی حذف می‌کنیم
        $delete_stmt = $conn->prepare("DELETE FROM `staff-manage` WHERE id = ?");
        $delete_stmt->bind_param("i", $staff_id);
        $delete_stmt->execute();

        // اگر هر سه قدم موفقیت‌آمیز بود، تراکنش را نهایی (commit) می‌کنیم
        $conn->commit();
        
        $response['success'] = true;
        $response['message'] = 'استف با موفقیت حذف و در آرشیو ذخیره شد.';

    } else {
        // اگر استف پیدا نشد، تراکنش را لغو (rollback) می‌کنیم
        $conn->rollback();
        $response['message'] = 'خطا: استف با این شناسه یافت نشد.';
    }

} catch (Exception $e) {
    // اگر در هر مرحله‌ای از try خطایی رخ دهد، تمام تغییرات لغو می‌شود
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    // می‌توانید برای دیباگ کردن، خطای اصلی را لاگ کنید
    // error_log($e->getMessage());
    $response['message'] = 'خطای داخلی سرور هنگام پردازش درخواست.';
}

// ۸. ارسال پاسخ نهایی به جاوا اسکریپت
echo json_encode($response);
exit();
?>