<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/auth_functions.php';

session_start();

if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    header('Location: login.php');
    exit();
}

// پردازش عملیات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDbConnection();
    
    if (isset($_POST['mark_all_read'])) {
        $stmt = $conn->prepare("UPDATE login_attempts SET viewed = 1 WHERE viewed = 0");
        $stmt->execute();
    } elseif (isset($_POST['mark_as_read'])) {
        $stmt = $conn->prepare("UPDATE login_attempts SET viewed = 1 WHERE id = ?");
        $stmt->bind_param("i", $_POST['alert_id']);
        $stmt->execute();
    } elseif (isset($_POST['delete_alert'])) {
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE id = ?");
        $stmt->bind_param("i", $_POST['alert_id']);
        $stmt->execute();
    } elseif (isset($_POST['delete_all'])) {
        $stmt = $conn->prepare("DELETE FROM login_attempts");
        $stmt->execute();
    }
}

// دریافت هشدارها
$conn = getDbConnection();
$alerts = $conn->query("
    SELECT * FROM login_attempts 
    WHERE attempt_time > (NOW() - INTERVAL 7 DAY)
    ORDER BY viewed ASC, attempt_time DESC
");
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>هشدارهای امنیتی | پنل مدیریت</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        /* استایل‌های سفارشی */
        .security-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .security-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .security-title {
            font-size: 24px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        /* استایل وضعیت‌ها */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
        }
        
        .status-new {
            background-color: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }
        
        .status-viewed {
            background-color: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
        }
        
        /* جدول فشرده */
        .compact-table {
            font-size: 13px;
        }
        
        .compact-table th, 
        .compact-table td {
            padding: 10px 12px;
        }
        
        /* دکمه‌های عملیاتی گرد */
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
            color: white;
        }
        
        .mark-btn {
            background-color: rgba(124, 77, 255, 0.8);
        }
        
        .mark-btn:hover {
            background-color: rgba(124, 77, 255, 1);
            transform: scale(1.1);
        }
        
        .delete-btn {
            background-color: rgba(255, 79, 79, 0.8);
            margin-right: 5px;
        }
        
        .delete-btn:hover {
            background-color: rgba(255, 79, 79, 1);
            transform: scale(1.1);
        }
        
        .delete-all-btn {
            background-color: rgba(255, 79, 79, 0.8);
        }
        
        .delete-all-btn:hover {
            background-color: rgba(255, 79, 79, 1);
        }
    </style>
</head>
<body>
    <div class="security-container">
        <div class="security-header">
            <h1 class="security-title">
                <i class="fas fa-shield-alt"></i>
                هشدارهای امنیتی
            </h1>
            <a href="admin_panel.php" class="btn-panel">
                <i class="fas fa-arrow-left"></i> بازگشت
            </a>
        </div>
        
        <div class="alert-actions">
            <form method="POST" style="display: inline;">
                <button type="submit" name="mark_all_read" class="submit-btn small">
                    <i class="fas fa-check-double"></i> علامت‌گذاری همه
                </button>
            </form>
            <form method="POST" style="display: inline;">
                <button type="submit" name="delete_all" class="submit-btn small delete-all-btn">
                    <i class="fas fa-trash-alt"></i> حذف همه
                </button>
            </form>
        </div>
        
        <div class="table-container">
            <table class="user-table compact-table">
                <thead>
                    <tr>
                        <th width="5%">شناسه</th>
                        <th width="20%">نام کاربری</th>
                        <th width="20%">آی‌پی</th>
                        <th width="20%">تاریخ/زمان</th>
                        <th width="15%">وضعیت</th>
                        <th width="10%">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($alerts->num_rows > 0): ?>
                        <?php while($alert = $alerts->fetch_assoc()): ?>
                        <tr>
                            <td><?= $alert['id'] ?></td>
                            <td><?= htmlspecialchars($alert['username']) ?></td>
                            <td><?= htmlspecialchars($alert['ip_address']) ?></td>
                            <td><?= date('Y/m/d H:i', strtotime($alert['attempt_time'])) ?></td>
                            <td>
                                <span class="status-badge <?= $alert['viewed'] ? 'status-viewed' : 'status-new' ?>">
                                    <i class="fas fa-<?= $alert['viewed'] ? 'check-circle' : 'exclamation-circle' ?>"></i>
                                    <?= $alert['viewed'] ? 'دیده شده' : 'جدید' ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex;">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="alert_id" value="<?= $alert['id'] ?>">
                                        <button type="submit" name="mark_as_read" class="action-btn mark-btn" title="علامت‌گذاری">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="alert_id" value="<?= $alert['id'] ?>">
                                        <button type="submit" name="delete_alert" class="action-btn delete-btn" title="حذف">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px;">
                                <i class="fas fa-check-circle" style="color: #4CAF50; font-size: 24px;"></i>
                                <p style="margin-top: 10px;">هیچ تلاش ناموفق ای در ورود به سیستم ثبت نشده است.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    // اسکریپت مدیریت عملیات
    document.addEventListener('DOMContentLoaded', function() {
        // تأییدیه برای عملیات گروهی
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (this.querySelector('[name="mark_all_read"]')) {
                    if (!confirm('آیا از علامت‌گذاری همه هشدارها به عنوان دیده شده اطمینان دارید؟')) {
                        e.preventDefault();
                    }
                }
                
                if (this.querySelector('[name="delete_all"]')) {
                    if (!confirm('آیا از حذف تمام هشدارها اطمینان دارید؟ این عمل غیرقابل بازگشت است!')) {
                        e.preventDefault();
                    }
                }
                
                if (this.querySelector('[name="delete_alert"]')) {
                    if (!confirm('آیا از حذف این هشدار اطمینان دارید؟')) {
                        e.preventDefault();
                    }
                }
            });
        });
        
        // نمایش توضیح ابزار برای دکمه‌ها
        tippy('[title]', {
            content(reference) {
                return reference.getAttribute('title');
            },
            placement: 'top',
            theme: 'light',
            animation: 'fade'
        });
    });
    </script>
    
    <!-- کتابخانه tooltip -->
    <script src="https://unpkg.com/tippy.js@6"></script>
</body>
</html>