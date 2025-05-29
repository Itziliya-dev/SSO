<?php
// این خطوط باید در ابتدای فایل باشند
error_reporting(E_ALL); // برای محیط توسعه
ini_set('display_errors', 1); // برای محیط توسعه

// مسیر config.php را با ساختار پروژه خود تطبیق دهید
// اگر server_control.php در پوشه server_management است و includes در ریشه است:
require_once __DIR__.'/../includes/config.php';
// require_once __DIR__.'/../includes/auth_functions.php'; // اگر توابع احراز هویت اینجا هم لازم است

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// بررسی لاگین استف و دسترسی‌های لازم
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_staff']) || !$_SESSION['is_staff']) {
    header('Location: ../login.php'); // یا داشبورد با پیام خطا
    exit();
}
$can_access_server_control = $_SESSION['can_access_server_control'] ?? false;
$staff_is_verify = $_SESSION['is_verify'] ?? 0;

if (!$can_access_server_control) {
    header('Location: ../dashboard.php?error=no_server_control_access');
    exit();
}
if (!$staff_is_verify) {
     header('Location: ../dashboard.php?error=staff_not_verified');
     exit();
}

// Server ID از config.php خوانده می‌شود
// نیازی به تعریف مجدد متغیر $pterodactyl_server_id نیست، مستقیماً از ثابت PTERODACTYL_SERVER_ID استفاده می‌کنیم

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>کنترل سرور Pterodactyl | پنل SSO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="preload" href="../assets/images/background.jpg" as="image">

    <style>
        :root {
            --text-color: #e5e7eb;
            --text-muted-color: #9ca3af;
            --card-bg: rgba(31, 41, 55, 0.88);
            --card-border: rgba(75, 85, 99, 0.7);
            --primary-color: #3b82f6;
            --primary-hover: #2563eb;
            --success-color: #10b981;
            --success-hover: #059669;
            --error-color: #ef4444;
            --error-hover: #dc2626;
            --warning-color: #f59e0b;
            --warning-hover: #d97706;
            --font-family: 'Vazirmatn', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            --top-bar-bg: rgba(17, 24, 39, 0.9); /* رنگ برای نوار عنوان جدید */
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--font-family);
            color: var(--text-color);
            background-color: #0f172a; /* یک رنگ پایه تیره */
            min-height: 100vh;
            direction: rtl;
            line-height: 1.6;
            padding-top: 60px; /* برای نوار عنوان ثابت */
            background-image: url('../assets/images/background.jpg'); /* تصویر پس‌زمینه */
            background-size: cover;
            background-position: center;
            background-attachment: fixed; /* ثابت ماندن پس‌زمینه هنگام اسکرول */
        }

        .top-title-bar {
            background-color: var(--top-bar-bg);
            backdrop-filter: blur(10px);
            color: var(--text-color);
            padding: 12px 25px;
            font-size: 16px;
            border-bottom: 1px solid var(--card-border);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 60px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        .top-title-bar h1 {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .top-title-bar .server-id-display {
            font-size: 13px;
            color: var(--text-muted-color);
            background-color: rgba(255,255,255,0.05);
            padding: 5px 10px;
            border-radius: 6px;
        }
        .top-title-bar a.back-to-dashboard-top {
            color: var(--text-muted-color);
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            transition: background-color 0.3s, color 0.3s;
        }
        .top-title-bar a.back-to-dashboard-top:hover {
            background-color: rgba(255,255,255,0.1);
            color: var(--text-color);
        }


        .main-container { /* برای چیدمان دو ستونی */
            display: flex;
            flex-direction: row; /* در حالت عادی */
            flex-wrap: wrap; /* برای موبایل */
            gap: 25px;
            padding: 25px;
            width: 100%;
            max-width: 1200px; /* عرض بیشتر برای دو ستون */
            margin: 20px auto 0 auto; /* فاصله از بالا و وسط‌چین */
        }

        .status-column, .actions-column {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.35);
            border: 1px solid var(--card-border);
            backdrop-filter: blur(12px);
            flex-basis: calc(50% - 12.5px); /* عرض هر ستون با احتساب gap */
            flex-grow: 1;
        }

        .status-box h2, .actions-box h2 { /* استایل عنوان باکس‌ها */
            font-size: 19px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #fff;
            border-bottom: 1px solid var(--card-border);
            padding-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .status-box h2 .fas, .actions-box h2 .fas {
             color: var(--text-muted-color);
             font-size: 0.9em;
        }
        .status-item { /* استایل آیتم‌های وضعیت */
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 0; font-size: 15px;
        }
        .status-item:not(:last-child) { border-bottom: 1px dashed rgba(255,255,255,0.08); }
        .status-item strong { font-weight: 500; color: var(--text-muted-color); min-width: 130px; }
        .status-item span { font-weight: 600; text-align: left; }
        .status-item span .fas.fa-spin { margin-right: 5px; }
        .status-item span .fa-sync-alt.fa-spin { font-size: 1em; color: var(--primary-color); } /* برای لودینگ کوچک */


        .action-buttons { /* چیدمان دکمه‌ها */
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 15px;
        }
        .action-btn { /* استایل دکمه‌ها (مشابه قبل) */
            color: white; border: none; padding: 12px 20px;
            border-radius: 8px; font-size: 15px; font-weight: 500;
            cursor: pointer; transition: background-color 0.25s, transform 0.15s, box-shadow 0.25s;
            display: inline-flex; align-items: center; justify-content: center;
            gap: 8px; text-decoration: none; box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
        .action-btn .fas { font-size: 0.95em; }
        .action-btn.start { background-color: var(--success-color); }
        .action-btn.start:hover { background-color: var(--success-hover); }
        .action-btn.restart { background-color: var(--warning-color); color: #1f2937; }
        .action-btn.restart:hover { background-color: var(--warning-hover); }
        .action-btn.stop { background-color: var(--error-color); }
        .action-btn.stop:hover { background-color: var(--error-hover); }


        .message-area { /* پیام‌های موفقیت/خطا */
            margin-bottom: 20px; padding: 12px 18px; border-radius: 8px;
            text-align: center; font-size: 14.5px; display: none;
            align-items: center; justify-content: center; gap: 10px;
            animation: fadeInMessage 0.5s ease-out;
            /* قرار دادن پیام در بالای ستون‌ها اگر لازم باشد */
            width: calc(100% - 50px); /* با توجه به پدینگ main-container */
            margin-left: auto;
            margin-right: auto;
        }
        @keyframes fadeInMessage { /* ... انیمیشن مشابه قبل ... */ }
        .message-area.success { /* ... */ }
        .message-area.error { /* ... */ }


        .loading-overlay { /* لودینگ تمام صفحه (فقط برای ارسال دستورات) */
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(17, 24, 39, 0.9); /* تیره‌تر */
            display: none; justify-content: center; align-items: center;
            z-index: 9999; backdrop-filter: blur(8px);
        }
        .loading-overlay .fa-spinner { color: var(--primary-color); font-size: 3.5em; }

        /* واکنش‌گرایی برای چیدمان ستون‌ها */
        @media (max-width: 900px) { /* نقطه شکست برای تک ستونی شدن */
            .main-container {
                flex-direction: column;
            }
            .status-column, .actions-column {
                flex-basis: 100%; /* تمام عرض */
            }
        }
        @media (max-width: 600px) {
            body { padding-top: 50px; }
            .top-title-bar { height: 50px; padding: 0 15px; flex-direction: column; align-items: flex-start; justify-content: center; gap: 3px;}
            .top-title-bar h1 { font-size: 16px; }
            .top-title-bar .server-id-display { font-size: 11px; padding: 3px 7px;}
            .top-title-bar a.back-to-dashboard-top { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); padding: 5px 8px; font-size: 13px;}

            .main-container { padding: 15px; padding-top: calc(50px + 20px); } /* 50 هدر + 20 فاصله */
            .status-column, .actions-column { padding: 20px; }
            .status-box h2, .actions-box h2 { font-size: 17px; margin-bottom: 15px; padding-bottom: 10px;}
            .status-item { font-size: 14px; flex-direction: column; align-items: flex-start; gap: 3px;}
            .status-item strong { margin-bottom: 2px;}
            .action-buttons { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="background-shapes"></div>

    <div class="top-title-bar">
        <h1><i class="fas fa-gamepad"></i> کنترل سرور SCP:SL | TC Modded</h1>
        <span class="server-id-display">شناسه سرور: <?= htmlspecialchars(PTERODACTYL_SERVER_ID) ?></span>
        <a href="../dashboard.php" class="back-to-dashboard-top"><i class="fas fa-arrow-left"></i> داشبورد</a>
    </div>

    <div id="message-area" class="message-area">
        </div>

    <div class="main-container">
        <div class="status-column">
            <div class="status-box">
                <h2><i class="fas fa-info-circle"></i> وضعیت فعلی سرور</h2>
                <div class="status-item">
                    <strong>وضعیت کلی:</strong>
                    <span id="server-current-status"><i class="fas fa-spinner fa-spin"></i> ...</span>
                </div>
                <div class="status-item">
                    <strong>آدرس IP:</strong>
                    <span id="server-ip">...</span>
                </div>
                <div class="status-item">
                    <strong>پورت:</strong>
                    <span id="server-port">...</span>
                </div>
                <div class="status-item">
                    <strong>مصرف RAM:</strong>
                    <span id="server-ram">...</span>
                </div>
                <div class="status-item">
                    <strong>بار CPU:</strong>
                    <span id="server-cpu">...</span>
                </div>
                <div class="status-item">
                    <strong>آخرین بروزرسانی:</strong>
                    <span id="last-updated">...</span>
                </div>
            </div>
        </div>

        <div class="actions-column">
            <div class="actions-box">
                <h2><i class="fas fa-cogs"></i> دستورات سرور</h2>
                <div class="action-buttons">
                    <button type="button" data-action="start" class="action-btn start">
                        <i class="fas fa-play"></i> Start
                    </button>
                    <button type="button" data-action="restart" class="action-btn restart">
                        <i class="fas fa-sync-alt"></i> Restart
                    </button>
                    <button type="button" data-action="stop" class="action-btn stop">
                        <i class="fas fa-stop"></i> Stop
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="loading-overlay" id="loading-overlay">
        <i class="fas fa-spinner fa-spin"></i>
    </div>

    <script src="../assets/js/server_control.js"></script>
</body>
</html>