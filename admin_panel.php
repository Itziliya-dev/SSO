<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/auth_functions.php';

session_start();

if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    header('Location: login.php');
    exit();
}

$login_attempts = [];
if ($_SESSION['is_owner']) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT username, ip_address, attempt_time 
        FROM login_attempts 
        WHERE attempt_time > (NOW() - INTERVAL 24 HOUR)
        ORDER BY attempt_time DESC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $login_attempts = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_user'])) {
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        
        $conn = getDbConnection();
        $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        
        $success = "کاربر جدید با موفقیت ایجاد شد";
    }
}

$conn = getDbConnection();
$users = $conn->query("SELECT id, username, created_at FROM users ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت | SSO Center</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/registration_requests.css">
    <style>
        .admin-header .buttons-container {
            display: flex;
            gap: 10px;
        }
        .admin-header .btn-panel {
             background: var(--primary-color, #4a6bff);
             color: white;
             padding: 10px 20px;
             border-radius: 8px;
             text-decoration: none;
             transition: background 0.3s;
             display: inline-flex;
             align-items: center;
             gap: 8px;
        }
         .admin-header .btn-panel:hover {
            background: var(--primary-hover, #3a5bef);
         }
        .admin-header .btn-panel.create-user {
             background: #27ae60;
         }
         .admin-header .btn-panel.create-user:hover {
             background: #219a52;
         }
         .admin-content {
             padding-top: 20px;
         }

    </style>
</head>
<body>



<div id="resetPasswordModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>ریست پسورد برای <span id="modalUsername"></span></h3>
            <span class="close-modal">&times;</span>
        </div>
        <div class="modal-body">
            <form id="resetPasswordForm">
                <input type="hidden" id="resetUserId" name="user_id">
                <div class="form-group">
                    <label>رمز عبور جدید:</label>
                    <input type="password" id="newPassword" name="new_password" class="form-control glass-input" required>
                </div>
                <div class="form-group">
                    <label>تکرار رمز عبور:</label>
                    <input type="password" id="confirmPassword" name="confirm_password" class="form-control glass-input" required>
                </div>
                <button type="submit" class="submit-btn">
                    <i class="fas fa-save"></i> ذخیره پسورد جدید
                </button>
            </form>
        </div>
    </div>
</div>

<div id="requestDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>جزئیات درخواست ثبت نام</h3>
            <span class="close-modal">&times;</span>
        </div>
        <div class="modal-body">
            <div class="request-details">
                <div class="detail-row">
                    <span class="detail-label">کد پیگیری:</span>
                    <span class="detail-value" id="detail-tracking-code"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">نام کامل:</span>
                    <span class="detail-value" id="detail-fullname"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">نام کاربری:</span>
                    <span class="detail-value" id="detail-username"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">ایمیل:</span>
                    <span class="detail-value" id="detail-email"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">تلفن:</span>
                    <span class="detail-value" id="detail-phone"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">سن:</span>
                    <span class="detail-value" id="detail-age"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">آیدی دیسکورد:</span>
                    <span class="detail-value" id="detail-discord"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">آیدی استیم:</span>
                    <span class="detail-value" id="detail-steam"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">تاریخ درخواست:</span>
                    <span class="detail-value" id="detail-created-at"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">وضعیت:</span>
                    <span class="detail-value" id="detail-status"></span>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="admin-header">
    <h1 class="admin-title">
        <i class="fas fa-user-shield"></i>
        پنل مدیریت SSO
    </h1>
    <div>
    <div class="buttons-container">
        <a href="users_management.php" class="btn-panel" style="margin-left: 10px;">
            <i class="fas fa-users-cog"></i> مدیریت کاربران
        </a>
        <a href="staff_management.php" class="btn-panel">
            <i class="fa-solid fa-users-line"></i> مدیریت استف ها 
        </a>
        <a href="create_user.php" class="btn-panel create-user">
            <i class="fas fa-user-plus"></i> ایجاد کاربر جدید
        </a>
        <a href="staff_archive.php" class="btn-panel">
            <i class="fas fa-archive"></i> ارشیو
        </a>
        <a href="security_alerts.php" class="btn-panel alert-btn" id="securityAlertsBtn">
            <i class="fas fa-bell"></i> هشدارهای امنیتی
            <span class="alert-badge" id="alertBadge" style="display: none"></span>
        </a>
        <a href="/Dashboard/dashboard.php" class="btn-panel">
            <i class="fas fa-arrow-left"></i> بازگشت
        </a>
      </div>
  
    </div>
</div>
    

        <!-- لیست کاربران -->


<div class="admin-card pending-requests-card">
    <h2>
        <i class="fas fa-user-clock"></i>
        درخواست‌های ثبت نام
    </h2>
    
    <div class="table-container">
        <table class="user-table">
            <thead>
                <tr>
                    <th>کد پیگیری</th>
                    <th>نام کاربری</th>
                    <th>نام کامل</th>
                    <th>تاریخ درخواست</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $requests = $conn->query("SELECT * FROM registration_requests ORDER BY created_at DESC");
                while($request = $requests->fetch_assoc()): 
                ?>
                <tr>
                    <td><?= htmlspecialchars($request['tracking_code']) ?></td>
                    <td><?= htmlspecialchars($request['username']) ?></td>
                    <td><?= htmlspecialchars($request['fullname']) ?></td>
                    <td><?= date('Y/m/d H:i', strtotime($request['created_at'])) ?></td>
                    <td>
                        <span class="status-badge <?= $request['status'] ?>">
                            <?= 
                                $request['status'] === 'pending' ? 'در حال بررسی' : 
                                ($request['status'] === 'approved' ? 'تایید شده' : 
                                ($request['status'] === 'rejected' ? 'رد شده' : 
                                ($request['status'] === 'staff' ? 'Staff' : 'نامعلوم')))
                            ?>
                        </span>
                    </td>
                    <td>
                        <button class="action-btn view-request" 
                            data-id="<?= $request['id'] ?>" 
                            title="مشاهده جزئیات">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="action-btn approve-request" 
                            data-id="<?= $request['id'] ?>" 
                            title="تایید درخواست">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="action-btn staff-request" 
                            data-id="<?= $request['id'] ?>" 
                            title="تایید استف">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="action-btn reject-request" 
                            data-id="<?= $request['id'] ?>" 
                            title="رد درخواست">
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>


<div id="userDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>جزئیات کاربر</h3>
            <span class="close-modal">&times;</span>
        </div>
        <div class="modal-body">
            <div class="user-details">
                <!-- محتوای این بخش با JS پر خواهد شد -->
            </div>
        </div>
    </div>
</div>

<!-- مدال غیرفعال کردن کاربر -->
<div id="suspendUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>غیرفعال کردن کاربر</h3>
            <span class="close-modal">&times;</span>
        </div>
        <div class="modal-body">
            <form id="suspendUserForm">
                <input type="hidden" id="suspendUserId" name="user_id">
                <div class="form-group">
                    <label>دلیل غیرفعال سازی:</label>
                    <textarea name="reason" class="form-control glass-input" required></textarea>
                </div>
                <button type="submit" class="submit-btn">
                    <i class="fas fa-user-slash"></i> تایید غیرفعال سازی
                </button>
            </form>
        </div>
    </div>
</div>

<!-- مدال هشدار تلاش‌های ناموفق -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- سیستم هشدارهای امنیتی پیشرفته ---
    const securityAlertsBtn = document.getElementById('securityAlertsBtn');
    const alertBadge = document.getElementById('alertBadge');
    
    // متغیرهای حالت
    let unreadAlertsCount = 0;
    let alertsCheckInterval;
    let isFirstLoad = true;

    // --- توابع اصلی ---

    // بررسی هشدارهای خوانده نشده
    const checkUnreadAlerts = async () => {
        try {
            const response = await fetch('includes/check_alerts.php');
            
            if (!response.ok) {
                throw new Error(`خطای HTTP! وضعیت: ${response.status}`);
            }
            
            const data = await response.json();
            updateAlertCount(data.count || 0);
            
            return data.count;
        } catch (error) {
            console.error('خطا در دریافت هشدارها:', error);
            return 0;
        }
    };

    // به‌روزرسانی تعداد هشدارها
    const updateAlertCount = (count) => {
        unreadAlertsCount = count;
        
        // به‌روزرسانی نشانگر
        if (unreadAlertsCount > 0) {
            alertBadge.style.display = 'flex';
            alertBadge.textContent = unreadAlertsCount > 9 ? '9+' : unreadAlertsCount;
            
            // انیمیشن‌های مختلف بر اساس تعداد هشدارها
            if (unreadAlertsCount >= 5) {
                alertBadge.classList.add('critical-alert');
                securityAlertsBtn.classList.add('critical-alert-btn');
            } else {
                alertBadge.classList.add('new-alert');
                securityAlertsBtn.classList.add('has-alerts');
            }
            
            // برای اولین بار که هشدار می‌آید، یک هشدار صوتی پخش می‌شود
        } else {
            alertBadge.style.display = 'none';
            alertBadge.className = 'alert-badge';
            securityAlertsBtn.className = 'btn-panel';
        }
    };

    // پخش صدای هشدار

    // علامت‌گذاری هشدارها به عنوان خوانده شده
    const markAlertsAsRead = async () => {
        try {
            const response = await fetch('includes/mark_alerts_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ mark_as_read: true })
            });
            
            if (!response.ok) {
                throw new Error(`خطای HTTP! وضعیت: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                updateAlertCount(0);
            }
            
            return data.success;
        } catch (error) {
            console.error('خطا در علامت‌گذاری هشدارها:', error);
            return false;
        }
    };

    // --- رویدادها ---

    // کلیک روی دکمه هشدارهای امنیتی
    securityAlertsBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        
        // اگر هشدار خوانده‌نشده وجود دارد، اول علامت‌گذاری می‌شود
        if (unreadAlertsCount > 0) {
            await markAlertsAsRead();
        }
        
        // رفتن به صفحه هشدارها
        window.location.href = 'security_alerts.php';
    });

    // --- مقداردهی اولیه ---

    // بررسی اولیه هشدارها
    checkUnreadAlerts();
    
    // تنظیم بررسی دوره‌ای هر 45 ثانیه
    alertsCheckInterval = setInterval(checkUnreadAlerts, 10000);

    // پاکسازی interval هنگام خروج از صفحه
    window.addEventListener('beforeunload', () => {
        clearInterval(alertsCheckInterval);
    });

    // --- انیمیشن‌های سفارشی ---
    const styleElement = document.createElement('style');
    styleElement.innerHTML = `
        /* انیمیشن برای هشدارهای معمولی */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        /* انیمیشن برای هشدارهای بحرانی */
        @keyframes criticalPulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 0, 0, 0.7); }
            70% { transform: scale(1.2); box-shadow: 0 0 0 15px rgba(255, 0, 0, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 0, 0, 0); }
        }
        
        .alert-badge.new-alert {
            animation: pulse 1.5s infinite;
        }
        
        .alert-badge.critical-alert {
            animation: criticalPulse 0.8s infinite;
            background-color: #ff0000;
            font-size: 14px;
            width: 24px;
            height: 24px;
        }
        
        .btn-panel.has-alerts {
            position: relative;
            background-color: #ff4f4f;
        }
        
        .btn-panel.critical-alert-btn {
            animation: shake 0.5s ease-in-out infinite;
            background-color: #ff0000;
        }
        
        @keyframes shake {
            0% { transform: translateX(-3px); }
            25% { transform: translateX(3px); }
            50% { transform: translateX(-3px); }
            75% { transform: translateX(3px); }
            100% { transform: translateX(0); }
        }
    `;
    document.head.appendChild(styleElement);
});
</script>

<script src="assets/js/admin.js"></script>
</body>
</html>