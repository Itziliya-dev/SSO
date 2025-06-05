<?php

require_once __DIR__.'/database.php'; // اطمینان حاصل کنید این فایل موجود و صحیح است




function updateLastLogin($conn, $id, $id_column_name, $table_name)
{
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


function authenticateUser($username, $password)
{
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


function authenticateUserOrStaff($conn, $username, $password)
{
    // 1. بررسی جدول users
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
                        return ['status' => 'error', 'message' => 'حساب کاربری شما (user) فعال نیست.'];
                    }
                    $user_data['type'] = 'user';
                    // updateLastLogin($conn, $user_data['id'], 'id', 'users');
                    return ['status' => 'success', 'data' => $user_data];
                }
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Auth Exception (Users): " . $e->getMessage());
    }

    // 2. بررسی جدول staff-manage
    try {
        $stmt = $conn->prepare("SELECT id, user_id, username, password, is_active, is_verify, permissions FROM `staff-manage` WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $staff_data_from_sm = $result->fetch_assoc();
                if (password_verify($password, $staff_data_from_sm['password'])) {
                    $stmt->close();
                    if ($staff_data_from_sm['is_active'] != 1) {
                        return ['status' => 'error', 'message' => 'حساب کاربری استف شما غیرفعال شده است.'];
                    }
                    if (empty($staff_data_from_sm['user_id'])) {
                        error_log("Staff login success but staff_manage.user_id is NULL for staff_manage.id: " . $staff_data_from_sm['id']);
                        return ['status' => 'error', 'message' => 'پیکربندی حساب استف ناقص است. با مدیر تماس بگیرید.'];
                    }

                    $response_data = [
                        'id' => $staff_data_from_sm['user_id'],
                        'staff_record_id' => $staff_data_from_sm['id'],
                        'username' => $staff_data_from_sm['username'],
                        'type' => 'staff',
                        'is_staff' => 1,
                        'is_owner' => 0,
                        'has_user_panel' => 0,
                        'is_verify' => $staff_data_from_sm['is_verify'],
                        'permissions' => $staff_data_from_sm['permissions'],
                    ];
                    updateLastLogin($conn, $staff_data_from_sm['id'], 'id', 'staff-manage');
                    return ['status' => 'success', 'data' => $response_data];
                }
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Auth Exception (Staff): " . $e->getMessage());
    }

    // اگر هیچ کاربری پیدا نشد
    return ['status' => 'error', 'message' => 'نام کاربری یا رمز عبور نادرست است'];
}

// اصلاح پارامتر ورودی تابع
function generateSsoToken($tokenData)
{
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
