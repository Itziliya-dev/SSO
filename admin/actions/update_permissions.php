<?php
header('Content-Type: application/json');

require_once __DIR__.'/../../includes/config.php';
require_once __DIR__.'/../../includes/database.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['is_owner'])) { exit(json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز'])); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit(json_encode(['success' => false, 'message' => 'متد نامعتبر'])); }

$user_id = $_POST['user_id'] ?? 0;
$user_type = $_POST['user_type'] ?? '';
$submitted_perms = $_POST['permissions'] ?? [];

if (empty($user_id) || !in_array($user_type, ['user', 'staff'])) {
    exit(json_encode(['success' => false, 'message' => 'اطلاعات ارسالی ناقص است.']));
}

$conn = getDbConnection();
$table = ($user_type === 'user') ? 'user_permissions' : 'staff_permissions';
$id_column = ($user_type === 'user') ? 'user_id' : 'staff_id';

// لیست تمام کلیدهای دسترسی ممکن
$granular_perm_keys = ['can_view_dashboard','can_manage_users','can_manage_staff','can_manage_permissions','can_create_user','can_manage_requests','can_view_archive','can_view_chart','can_view_alerts','can_manage_settings'];
$base_perm_keys = ['is_owner', 'has_user_panel', 'has_developer_access'];
$all_perm_keys = array_merge($base_perm_keys, $granular_perm_keys);

try {
    $final_values_map = [];
    // پردازش دسترسی‌های پایه
    foreach($base_perm_keys as $key) {
        $final_values_map[$key] = isset($submitted_perms[$key]) ? 1 : 0;
    }
    
    // پردازش دسترسی‌های دقیق
    $selected_sections = $submitted_perms['sections'] ?? [];
    foreach ($granular_perm_keys as $key) {
        $final_values_map[$key] = ($final_values_map['is_owner'] && in_array($key, $selected_sections)) ? 1 : 0;
    }
    
    // ساخت کوئری
    $sql_keys = implode(', ', $all_perm_keys);
    $sql_placeholders = rtrim(str_repeat('?, ', count($all_perm_keys)), ', ');
    $update_clauses = implode(', ', array_map(fn($k) => "$k = VALUES($k)", $all_perm_keys));
    
    $sql = "INSERT INTO $table ($id_column, $sql_keys) VALUES (?, $sql_placeholders) ON DUPLICATE KEY UPDATE $update_clauses";
    
    $stmt = $conn->prepare($sql);
    $bind_values = array_merge([$user_id], array_values($final_values_map));
    $stmt->bind_param(str_repeat('i', count($bind_values)), ...$bind_values);
    $stmt->execute();
    
    if ($stmt->affected_rows >= 0) {
        echo json_encode(['success' => true, 'message' => 'دسترسی‌ها با موفقیت به‌روزرسانی شد.']);
    } else {
        throw new Exception("خطا در اجرای کوئری: " . $stmt->error);
    }
    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    error_log("Permission Update Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا در پایگاه داده.']);
}