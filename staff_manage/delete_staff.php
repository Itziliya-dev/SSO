<?php
// ۱. تنظیم هدر برای پاسخ JSON
header('Content-Type: application/json');

// ۲. فراخوانی فایل‌های اصلی
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/auth_functions.php';

// ۳. شروع نشست
session_start();

// ۴. آماده‌سازی آرایه پاسخ
$response = [
    'success' => false,
    'message' => 'یک خطای ناشناخته رخ داده است.'
];

// ۵. بررسی‌های امنیتی
if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    $response['message'] = 'دسترسی غیرمجاز! شما باید ادمین باشید.';
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'متد درخواست نامعتبر است.';
    echo json_encode($response);
    exit();
}

if (!isset($_POST['staff_id']) || !is_numeric($_POST['staff_id'])) {
    $response['message'] = 'شناسه استف نامعتبر یا ارسال نشده است.';
    echo json_encode($response);
    exit();
}

// ۶. دریافت اطلاعات از فرم و سشن
$staff_id = (int)$_POST['staff_id'];
$reason = !empty($_POST['reason']) ? $_POST['reason'] : 'دلیلی ذکر نشده است.';
$admin_username = $_SESSION['username'] ?? 'system';

// ۷. عملیات اصلی پایگاه داده
try {
    $conn = getDbConnection();
    $conn->begin_transaction();

    // قدم اول: خواندن اطلاعات استف
    $select_stmt = $conn->prepare("SELECT * FROM `staff-manage` WHERE id = ?");
    $select_stmt->bind_param("i", $staff_id);
    $select_stmt->execute();
    $staff_to_delete = $select_stmt->get_result()->fetch_assoc();

    if ($staff_to_delete) {
        // قدم دوم: درج در جدول آرشیو
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
            $staff_to_delete['discord_id2'],
            $staff_to_delete['steam_id'],
            $staff_to_delete['permissions'],
            $staff_to_delete['created_at'],
            $reason,
            $admin_username
        );
        $archive_stmt->execute();

        // قدم سوم: حذف از جدول اصلی
        $delete_stmt = $conn->prepare("DELETE FROM `staff-manage` WHERE id = ?");
        $delete_stmt->bind_param("i", $staff_id);
        $delete_stmt->execute();

        // نهایی کردن تراکنش دیتابیس
        $conn->commit();
        
        // ===================================================================
        // شروع بخش جدید: ارسال درخواست به بات دیسکورد
        // این کد فقط زمانی اجرا می‌شود که عملیات دیتابیس موفقیت‌آمیز باشد
        // ===================================================================

        // **لطفاً مقادیر زیر را ویرایش کنید**
        $webhook_url = 'http://83.149.95.39:1030/demote'; // آدرس کامل وب‌هوک بات
        $secret_token = '4a97dd86-4388-4cc0-a54f-65ebbf51649d'; // کلید مخفی مشترک بین پنل و بات

        // داده‌ای که به بات ارسال می‌شود
        $data = [
            'discord_id' => $staff_to_delete['discord_id2'] // آیدی دیسکورد از اطلاعاتی که از دیتابیس خواندیم
        ];

        // اطمینان از اینکه آیدی دیسکورد خالی نیست
        if (!empty($data['discord_id'])) {
            // تنظیمات درخواست cURL
            $ch = curl_init($webhook_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $secret_token
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); // پنل را معطل پاسخ بات نمی‌کنیم

            // اجرای درخواست
            curl_exec($ch);
            curl_close($ch);
        }

        // ===================================================================
        // پایان بخش ارسال درخواست به بات دیسکورد
        // ===================================================================

        // تنظیم پیام موفقیت برای ارسال به کاربر
        $response['success'] = true;
        $response['message'] = 'استف با موفقیت حذف و در آرشیو ذخیره شد. دستور دیموت به دیسکورد نیز ارسال گردید.';

    } else {
        $conn->rollback();
        $response['message'] = 'خطا: استف با این شناسه یافت نشد.';
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    // error_log($e->getMessage()); // برای دیباگ
    $response['message'] = 'خطای داخلی سرور هنگام پردازش درخواست.';
}

// ۸. ارسال پاسخ نهایی
echo json_encode($response);
exit();
?>
