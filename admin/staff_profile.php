<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/auth_functions.php';

session_start();

if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    header('Location: login.php');
    exit();
}

// دریافت آیدی دیسکورد از URL
$discord_id = $_GET['discord_id'] ?? '0';

if (!ctype_digit($discord_id) || $discord_id === '0') {
    die("خطا: شناسه دیسکورد نامعتبر است.");
}

// اطلاعات استاتیک تیم مدیریت (چون در دیتابیس نیستند)
$management_team_data = [
    // Founder
    '246703882044964864' => ['role' => 'Founder', 'join_date' => '2020-01-01', 'added_by' => 'System', 'bio' => 'چیزی ثبت نشده'],
    
    // Owner
    '585385236918042624' => ['role' => 'Owner', 'join_date' => '2020-02-15', 'added_by' => 'Arshia', 'bio' => 'چیزی ثبت نشده'],
    
    // Co-Owners
    '994286583861751988' => ['role' => 'Co-Owner', 'join_date' => '2021-05-20', 'added_by' => 'DoT ExE', 'bio' => 'چیزی ثبت نشده'],
    '757308856346083460' => ['role' => 'Co-Owner', 'join_date' => '2021-06-10', 'added_by' => 'DoT ExE', 'bio' => 'چیزی ثبت نشده'],
    
    // Managers
    '849366025379250176' => ['role' => 'Manager', 'join_date' => '2022-03-01', 'added_by' => 'Look', 'bio' => 'چیزی ثبت نشده'],
    '1355069146647498893' => ['role' => 'Manager', 'join_date' => '2022-04-12', 'added_by' => 'Dark_killer', 'bio' => 'چیزی ثبت نشده'],
    '1230456654588411946' => ['role' => 'Manager', 'join_date' => '2022-01-18', 'added_by' => 'Look', 'bio' => 'چیزی ثبت نشده'],
    
    // Developers (اضافه شده)
    '865619608546050059' => ['role' => 'H-Developer', 'join_date' => '2021-02-01', 'added_by' => 'سرهنگ (اونر قدیمی سرور)', 'bio' => 'ایلیا توسط سرهنگ به عنوان دولوپر انتخاب شد ، و بعد از آن به دلیل تشکیل رفاقت عمیق بین ایلیا و ارشیا ، ایلیا به عنوان سرپرست دولوپرا انتخاب شد و او مسئول توسعه و نظارت بر تاسیسات مجموعه شد.'], //Itz_iliya
    '740981195159896184' => ['role' => 'Developer', 'join_date' => '2023-07-01', 'added_by' => 'ارشیا', 'bio' => 'توسعه دهنده.'], //Carnoval15
    '1073628803886239744' => ['role' => 'Developer', 'join_date' => '2020-12-01', 'added_by' => 'ارشیا', 'bio' => 'توسه دهنده.'], //BNK
    '1225781633219690608' => ['role' => 'Developer', 'join_date' => '2024-04-01', 'added_by' => 'رامتین', 'bio' => 'توسعه دهنده.'], //threep
];

// پیدا کردن اطلاعات شخص از لیست بالا
$staff_info = $management_team_data[$discord_id] ?? null;

if (!$staff_info) {
    die("اطلاعاتی برای این عضو تیم مدیریت یافت نشد.");
}

// --- دریافت عکس پروفایل از بات دیسکورد ---
$avatar_url = null;
$discord_username = 'کاربر دیسکورد';
$bot_api_url = 'http://83.149.95.39:1030/get-avatar-url?discord_id=' . urlencode($discord_id);
$secret_token = '4a97dd86-4388-4cc0-a54f-65ebbf51649d'; // توکن امنیتی شما

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $bot_api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 ثانیه مهلت برای پاسخ
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $secret_token]);
$response = curl_exec($ch);
curl_close($ch);

if ($response) {
    $data = json_decode($response, true);
    if (isset($data['success']) && $data['success']) {
        $avatar_url = $data['avatar_url'];
        $discord_username = $data['username']; // نام کاربر از دیسکورد گرفته می‌شود
    }
}
// --- پایان بخش دریافت عکس ---

$currentPage = 'management_chart'; // برای فعال ماندن لینک سایدبار
$conn = getDbConnection();
$pending_requests_count = $conn->query("SELECT COUNT(id) as count FROM `registration_requests` WHERE status = 'pending'")->fetch_assoc()['count'];
$conn->close();
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پروفایل: <?= htmlspecialchars($discord_username) ?></title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/admin_dashboard_redesign.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .profile-layout { display: flex; gap: 30px; flex-wrap: wrap-reverse; } /* reverse برای نمایش بهتر در موبایل */
        .profile-info-card { flex: 1; min-width: 300px; }
        .profile-bio-card { flex: 2; min-width: 300px; }
        .profile-avatar-container { text-align: center; margin-bottom: 20px; }
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid var(--primary-color);
            box-shadow: 0 0 25px rgba(var(--primary-color-rgb, 124, 77, 255), 0.5);
            background-color: var(--card-bg);
        }
        .info-grid .detail-row { display: flex; justify-content: space-between; border-bottom: 1px solid var(--glass-border); padding: 14px 5px; }
        .info-grid .detail-label { font-weight: 500; color: var(--text-muted); }
        .info-grid .detail-value { font-weight: 600; color: var(--text-primary); }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__.'/../includes/_sidebar.php'; ?>
    <main class="main-content">
        <header class="main-header">
            <h1 class="header-title"><i class="fas fa-user-circle"></i> پروفایل: <?= htmlspecialchars($discord_username) ?></h1>
        </header>
        <div class="profile-layout">
            <div class="profile-bio-card">
                <div class="admin-card">
                    <h2><i class="fas fa-info-circle"></i> درباره <?= htmlspecialchars($discord_username) ?></h2>
                    <p style="line-height: 1.8; color: var(--text-secondary);">
                        <?= nl2br(htmlspecialchars($staff_info['bio'])) ?>
                    </p>
                </div>
            </div>
            <div class="profile-info-card">
                <div class="admin-card">
                    <div class="profile-avatar-container">
                        <img src="<?= $avatar_url ? 'image_proxy.php?url=' . urlencode($avatar_url) : '../assets/images/default-avatar.png' ?>" alt="آواتار" class="profile-avatar">
                    </div>
                    
                    <div class="user-details info-grid">
                        <div class="detail-row">
                            <span class="detail-label">نقش اصلی:</span>
                            <span class="detail-value"><?= htmlspecialchars($staff_info['role']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">تاریخ پیوستن به تیم:</span>
                            <span class="detail-value"><?= date('Y/m/d', strtotime($staff_info['join_date'])) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">معرف:</span>
                            <span class="detail-value"><?= htmlspecialchars($staff_info['added_by']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>