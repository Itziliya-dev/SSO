<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/auth_functions.php';

session_start();

if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    header('Location: login.php');
    exit();
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
        <a href="dashboard.php" class="btn-panel">
            <i class="fas fa-arrow-left"></i> بازگشت
        </a>
      </div>
  
    </div>
</div>
    
    <div class="admin-content">
        <!-- فرم ایجاد کاربر -->
        <div class="admin-card create-user-card">
            <h2>
                <i class="fas fa-user-plus"></i>
                ایجاد کاربر جدید
            </h2>
            
            <form method="POST" class="user-form">
                <div class="form-group">
                    <label>نام کاربری:</label>
                    <input type="text" name="username" class="form-control glass-input" required>
                </div>
                
                <div class="form-group">
                    <label>رمز عبور:</label>
                    <input type="password" name="password" class="form-control glass-input" required>
                </div>
                
                <div class="form-group">
                    <label>ایمیل (اختیاری):</label>
                    <input type="email" name="email" class="form-control glass-input">
                </div>
                
                <div class="form-group">
                    <label>تلفن (اختیاری):</label>
                    <input type="tel" name="phone" class="form-control glass-input">
                </div>
                
                <button type="submit" name="create_user" class="submit-btn">
                    <i class="fas fa-save"></i> ایجاد کاربر
                </button>
                
                <?php if(isset($success)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?= $success ?>
                </div>
                <?php endif; ?>
            </form>
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


<script src="assets/js/admin.js"></script>
</body>
</html>