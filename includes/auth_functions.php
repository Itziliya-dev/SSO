<?php
require_once __DIR__.'/database.php'; // اطمینان حاصل کنید این فایل موجود و صحیح است



function updateLastLogin($conn, $id, $id_column_name, $table_name) {
    // تابع برای آپدیت آخرین ورود
    // id_column_name می تواند 'id' یا 'user_id' باشد بسته به جدول
    if ($conn) {
        $stmt = $conn->prepare("UPDATE `$table_name` SET last_login = NOW() WHERE `$id_column_name` = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        } else {
            error_log("Update Last Login Prepare Error ($table_name): " . $conn->error);
        }
    }
}


function authenticateUser($username, $password) {
    try {
        $conn = getDbConnection(); // اطمینان حاصل کنید این تابع اتصال را برمی‌گرداند

        // اضافه کردن status به کوئری
        $stmt = $conn->prepare("SELECT id, username, password, is_owner, status, has_user_panel, is_staff FROM users WHERE username = ?");
        if (!$stmt) {
            throw new Exception("خطا در آماده‌سازی کوئری: " . $conn->error);
        }

        $stmt->bind_param("s", $username);
        if (!$stmt->execute()) {
            throw new Exception("خطا در اجرای کوئری: " . $stmt->error);
        }

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            error_log("کاربر یافت نشد: " . $username);
            return false;
        }

        $user = $result->fetch_assoc();

        if (!password_verify($password, $user['password'])) {
            error_log("رمز عبور نادرست برای کاربر: " . $username);
            return false;
        }

        // بازگرداندن تمام اطلاعات کاربر، شامل status
        return $user;

    } catch (Exception $e) {
        error_log("خطای احراز هویت: " . $e->getMessage());
        return false;
    }
}


function authenticateUserOrStaff($username, $password) {
    $conn = getDbConnection();
    if (!$conn || $conn->connect_error) {
        error_log("DB Connection Error: " . ($conn ? $conn->connect_error : 'Failed to connect'));
        return ['status' => 'error', 'message' => 'خطای داخلی سرور در اتصال.'];
    }

    // 1. بررسی جدول users (برای کاربران عادی و مدیران اصلی)
    try {
        $stmt = $conn->prepare("SELECT id, username, password, is_owner, status, has_user_panel, is_staff FROM users WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user_data = $result->fetch_assoc();
                if (password_verify($password, $user_data['password'])) {
                    $stmt->close();
                    if ($user_data['status'] !== 'active') {
                        $conn->close();
                        return ['status' => 'error', 'message' => 'حساب کاربری شما (user) فعال نیست.'];
                    }
                    $user_data['type'] = 'user';
                    // updateLastLogin($conn, $user_data['id'], 'id', 'users'); // اگر برای users هم last_login دارید
                    $conn->close();
                    return ['status' => 'success', 'data' => $user_data];
                }
            }
            $stmt->close();
        } else {
            error_log("Auth Prepare Error (Users): " . $conn->error);
        }
    } catch (Exception $e) {
        error_log("Auth Exception (Users): " . $e->getMessage());
    }

    // 2. بررسی جدول staff-manage
    try {
        // user_id را از staff-manage می‌خوانیم که به users.id لینک می‌شود
        $stmt = $conn->prepare("SELECT id, user_id, username, password, is_active, is_verify, permissions FROM `staff-manage` WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $staff_data_from_sm = $result->fetch_assoc(); // sm = staff-manage
                if (password_verify($password, $staff_data_from_sm['password'])) {
                    $stmt->close();
                    if ($staff_data_from_sm['is_active'] != 1) {
                        $conn->close();
                        return ['status' => 'error', 'message' => 'حساب کاربری استف شما غیرفعال شده است.'];
                    }

                    // **نکته کلیدی برای حل باگ profile.php**
                    // ما به users.id به عنوان شناسه اصلی در سشن نیاز داریم.
                    // اگر staff_manage.user_id خالی بود، یعنی استف هنوز به users لینک نشده (مشکل داده‌ای)
                    if (empty($staff_data_from_sm['user_id'])) {
                        $conn->close();
                        error_log("Staff login success but staff_manage.user_id is NULL for staff_manage.id: " . $staff_data_from_sm['id']);
                        return ['status' => 'error', 'message' => 'پیکربندی حساب استف ناقص است. با مدیر تماس بگیرید.'];
                    }

                    $response_data = [
                        'id' => $staff_data_from_sm['user_id'], // ** users.id به عنوان شناسه اصلی سشن **
                        'staff_record_id' => $staff_data_from_sm['id'], // شناسه رکورد خود جدول staff-manage
                        'username' => $staff_data_from_sm['username'], // نام کاربری از staff-manage
                        'type' => 'staff',
                        'is_staff' => 1, // مشخص می‌کند که این کاربر استف است
                        'is_owner' => 0, // استف‌ها به طور پیش‌فرض مدیر اصلی نیستند
                        'has_user_panel' => 0, // دسترسی به پنل کاربری عادی ندارند
                        'is_active_staff' => $staff_data_from_sm['is_active'], // وضعیت فعال بودن خود استف
                        'is_verify' => $staff_data_from_sm['is_verify'],
                        'permissions' => $staff_data_from_sm['permissions'],
                        'last_login' => null // بعدا آپدیت می‌شود
                    ];
                    // آپدیت last_login با استفاده از staff_manage.id
                    updateLastLogin($conn, $staff_data_from_sm['id'], 'id', 'staff-manage');
                    // خواندن مجدد last_login برای ارسال در ریسپانس
                    $stmt_last_login = $conn->prepare("SELECT last_login FROM `staff-manage` WHERE id = ?");
                    if($stmt_last_login){
                        $stmt_last_login->bind_param("i", $staff_data_from_sm['id']);
                        $stmt_last_login->execute();
                        $ll_res = $stmt_last_login->get_result()->fetch_assoc();
                        $response_data['last_login'] = $ll_res['last_login'];
                        $stmt_last_login->close();
                    }

                    $conn->close();
                    return ['status' => 'success', 'data' => $response_data];
                }
            }
            $stmt->close();
        } else {
            error_log("Auth Prepare Error (Staff): " . $conn->error);
        }
    } catch (Exception $e) {
        error_log("Auth Exception (Staff): " . $e->getMessage());
    }

    $conn->close();
    return ['status' => 'error', 'message' => 'نام کاربری یا رمز عبور نادرست است'];
}

// اصلاح پارامتر ورودی تابع
function generateSsoToken($tokenData) {
    $tokenDir = defined('TOKEN_DIR') ? TOKEN_DIR : '/tmp/sso_tokens';

    // ایجاد پوشه اگر وجود نداشت
    if (!file_exists($tokenDir)) {
        if (!mkdir($tokenDir, 0700, true)) {
            throw new Exception('Failed to create token directory');
        }
    }

    $token = bin2hex(random_bytes(32));

    $tokenPath = "$tokenDir/$token";
    if (file_put_contents($tokenPath, json_encode($tokenData)) === false) {
         throw new Exception('Failed to write token file');
    }

    return $token;
}
?>