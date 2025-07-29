<?php

require_once __DIR__.'/database.php';

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

/**
 * تابع اصلی احراز هویت که با ساختار جدید دسترسی‌ها هماهنگ شده است.
 */
function authenticateUserOrStaff($conn, $username, $password)
{
    $perm_columns = "is_owner, has_user_panel, has_developer_access, can_view_dashboard, can_manage_users, can_manage_staff, can_manage_permissions, can_create_user, can_manage_requests, can_view_archive, can_view_chart, can_view_alerts, can_manage_settings, can_manage_finance";
    // ۱. ابتدا در جدول staff-manage جستجو می‌کنیم (معمولاً تعداد استف کمتر است)
    try {
        $stmt = $conn->prepare("SELECT id, password, is_active, is_verify FROM `staff-manage` WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $staff_data = $result->fetch_assoc();
                $stmt->close();
                if (password_verify($password, $staff_data['password'])) {
                    if ($staff_data['is_active'] != 1) { return ['status' => 'error', 'message' => 'حساب استف شما غیرفعال است.']; }

                    $perm_stmt = $conn->prepare("SELECT {$perm_columns} FROM staff_permissions WHERE staff_id = ?");
                    $perm_stmt->bind_param("i", $staff_data['id']);
                    $perm_stmt->execute();
                    $permissions = $perm_stmt->get_result()->fetch_assoc();
                    $perm_stmt->close();

                    return ['status' => 'success', 'data' => [
                        'id' => $staff_data['id'],
                        'username' => $username,
                        'type' => 'staff',
                        'is_verify' => $staff_data['is_verify'],
                        'permissions' => $permissions ?: [] // برگرداندن آرایه دسترسی‌ها
                    ]];
                }
            }
            if($stmt) $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Auth Exception (Staff): " . $e->getMessage());
    }

    // ۲. اگر استف نبود، در جدول users جستجو می‌کنیم
    try {
        $stmt = $conn->prepare("SELECT id, username, password, status FROM users WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $user_data = $result->fetch_assoc();
                $stmt->close();
                if (password_verify($password, $user_data['password'])) {
                    if ($user_data['status'] !== 'active') { return ['status' => 'error', 'message' => 'حساب کاربری شما فعال نیست.']; }
                    
                    $perm_stmt = $conn->prepare("SELECT {$perm_columns} FROM user_permissions WHERE user_id = ?");
                    $perm_stmt->bind_param("i", $user_data['id']);
                    $perm_stmt->execute();
                    $permissions = $perm_stmt->get_result()->fetch_assoc();
                    $perm_stmt->close();
                    
                    return ['status' => 'success', 'data' => [
                        'id' => $user_data['id'],
                        'username' => $user_data['username'],
                        'type' => 'user',
                        'permissions' => $permissions ?: [] // برگرداندن آرایه دسترسی‌ها
                    ]];
                }
            }
            if($stmt) $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Auth Exception (Users): " . $e->getMessage());
    }

    // اگر هیچ کاربری پیدا نشد
    return ['status' => 'error', 'message' => 'نام کاربری یا رمز عبور نادرست است'];
}


function generateSsoToken($tokenData)
{
    $tokenDir = defined('TOKEN_DIR') ? TOKEN_DIR : '/tmp/sso_tokens';
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