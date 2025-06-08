<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/auth_functions.php';

session_start();

if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    header('Location: login.php');
    exit();
}
// دیگر نیازی به متغیرهای سایدبار در این صفحه نیست
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>چارت مدیریت | پنل مدیریت</title>
    
    <link rel="stylesheet" href="../assets/css/admin.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* استایل‌های پایه برای حالت تمام-صفحه */
        body {
            /* استفاده از همان پس‌زمینه برای حفظ یکپارچگی */
            background: url('../assets/images/background.jpg') no-repeat center center fixed;
            background-size: cover;
            padding: 0;
            margin: 0;
            font-family: 'Vazirmatn', sans-serif;
            color: var(--text-primary);
        }

        /* دکمه بازگشت شناور */
        .back-to-dashboard-btn {
            position: fixed;
            top: 25px;
            right: 25px;
            z-index: 1000;
            background: rgba(40, 40, 60, 0.8);
            backdrop-filter: blur(10px);
            color: white;
            padding: 12px 20px;
            border-radius: var(--border-radius);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid var(--glass-border);
            transition: all 0.3s ease;
        }
        .back-to-dashboard-btn:hover {
            background: var(--primary-color);
            box-shadow: 0 0 15px var(--primary-color);
        }

        /* کانتینر اصلی برای وسط‌چین کردن نمودار */
        .chart-full-screen-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            min-height: 100vh;
            box-sizing: border-box;
            padding: 20px;
        }

        /* --- کدهای CSS نمودار از اینجا شروع می‌شود (بدون تغییر) --- */
        .chart-background-container {
            background: rgba(30, 30, 50, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(124, 77, 255, 0.2);
            border-radius: var(--border-radius);
            padding: 30px 15px;
            overflow: hidden;
        }
        .org-chart-container {
            width: 100%;
            overflow-x: auto;
            padding-bottom: 20px;
            display: flex;
            justify-content: center;
        }
        .org-chart { display: inline-flex; padding: 0; margin: 0; }
        .org-chart ul { padding-top: 15px; position: relative; transition: all 0.5s; }
        .org-chart li { float: left; text-align: center; list-style-type: none; position: relative; padding: 15px 5px 0 5px; transition: all 0.5s; }
        .org-chart li::before, .org-chart li::after { content: ''; position: absolute; top: 0; right: 50%; border-top: 2px solid var(--glass-border); width: 50%; height: 15px; }
        .org-chart li::after { right: auto; left: 50%; border-left: 2px solid var(--glass-border); }
        .org-chart li:only-child { padding-top: 0; }
        .org-chart li:only-child::before, .org-chart li:only-child::after { display: none; }
        .org-chart li:first-child::before, .org-chart li:last-child::after { border: 0 none; }
        .org-chart li:last-child::before { border-right: 2px solid var(--glass-border); border-radius: 0 5px 0 0; }
        .org-chart li:first-child::after { border-radius: 5px 0 0 0; }
        .org-chart ul ul::before { content: ''; position: absolute; top: 0; left: 50%; border-left: 2px solid var(--glass-border); width: 0; height: 15px; }
        .org-chart li .card { background: var(--glass-bg); backdrop-filter: blur(12px); border: 1px solid var(--glass-border); padding: 15px; min-width: 160px; display: inline-block; border-radius: var(--border-radius); position: relative; transition: transform 0.3s ease, box-shadow 0.3s ease; direction: rtl; }
        .org-chart li .card:hover { transform: translateY(-5px); box-shadow: 0 12px 30px rgba(0,0,0,0.4); }
        .card .avatar { width: 50px; height: 50px; line-height: 50px; border-radius: 50%; background-color: rgba(255, 255, 255, 0.1); display: inline-block; font-size: 22px; margin: -40px auto 10px auto; border: 2px solid var(--text-muted); color: var(--text-primary); position: relative; }
        .card .name { font-size: 1rem; font-weight: 600; color: var(--text-primary); }
        .card .role { font-size: 0.8rem; font-weight: 500; margin-top: 4px; }
        .card-founder { box-shadow: 0 0 20px -2px #ffd700; }
        .card-founder .role, .card-founder .avatar { color: #ffd700; border-color: #ffd700;}
        .card-owner { box-shadow: 0 0 20px -2px #ff4f4f; }
        .card-owner .role, .card-owner .avatar { color: #ff4f4f; border-color: #ff4f4f;}
        .card-co-owner { box-shadow: 0 0 20px -2px #7c4dff; }
        .card-co-owner .role, .card-co-owner .avatar { color: #7c4dff; border-color: #7c4dff;}
        .card-manager { box-shadow: 0 0 20px -2px #00c853; }
        .card-manager .role, .card-manager .avatar { color: #00c853; border-color: #00c853;}
        .card-developer { box-shadow: 0 0 20px -2px #2196f3; }
        .card-developer .role, .card-developer .avatar { color: #2196f3; border-color: #2196f3;}
        a.node-link { text-decoration: none; }
    </style>
</head>
<body>

<a href="admin_panel.php" class="back-to-dashboard-btn">
    <i class="fas fa-arrow-left"></i> بازگشت به داشبورد
</a>

<div class="chart-full-screen-wrapper">
    <div class="chart-background-container">
        <div class="org-chart-container">
            <ul class="org-chart">
                <li>
                    <a href="staff_profile.php?discord_id=246703882044964864" class="node-link">
                        <div class="card card-founder"><div class="avatar"><i class="fas fa-crown"></i></div><div class="name">Arshia</div><div class="role">Founder</div></div>
                    </a>
                    <ul>
                        <li>
                            <a href="staff_profile.php?discord_id=585385236918042624" class="node-link">
                                <div class="card card-owner"><div class="avatar"><i class="fas fa-gem"></i></div><div class="name">DoT ExE</div><div class="role">Owner</div></div>
                            </a>
                            <ul>
                                <li>
                                    <a href="staff_profile.php?discord_id=757308856346083460" class="node-link">
                                        <div class="card card-co-owner"><div class="avatar"><i class="fas fa-user-shield"></i></div><div class="name">Look</div><div class="role">Co-Owner</div></div>
                                    </a>
                                    <ul>
                                        <li>
                                            <a href="staff_profile.php?discord_id=1230456654588411946" class="node-link">
                                                <div class="card card-manager"><div class="avatar"><i class="fas fa-user-tie"></i></div><div class="name">sinagp</div><div class="role">Manager</div></div>
                                            </a>
                                            <ul>
                                                <li><a href="staff_profile.php?discord_id=865619608546050059" class="node-link"><div class="card card-developer"><div class="avatar"><i class="fas fa-code"></i></div><div class="name">Itz_iliya</div><div class="role">Developer</div></div></a></li>
                                                <li><a href="staff_profile.php?discord_id=740981195159896184" class="node-link"><div class="card card-developer"><div class="avatar"><i class="fas fa-code"></i></div><div class="name">Carnoval15</div><div class="role">Developer</div></div></a></li>
                                            </ul>
                                        </li>
                                        <li><a href="staff_profile.php?discord_id=849366025379250176" class="node-link"><div class="card card-manager"><div class="avatar"><i class="fas fa-user-tie"></i></div><div class="name">fakedem</div><div class="role">Manager</div></div></a></li>
                                    </ul>
                                </li>
                                <li>
                                    <a href="staff_profile.php?discord_id=994286583861751988" class="node-link">
                                        <div class="card card-co-owner"><div class="avatar"><i class="fas fa-user-shield"></i></div><div class="name">Dark_killer</div><div class="role">Co-Owner</div></div>
                                    </a>
                                    <ul>
                                        <li>
                                            <a href="staff_profile.php?discord_id=1355069146647498893" class="node-link">
                                                <div class="card card-manager"><div class="avatar"><i class="fas fa-user-tie"></i></div><div class="name">Shadow</div><div class="role">Manager</div></div>
                                            </a>
                                            <ul>
                                                <li><a href="staff_profile.php?discord_id=1073628803886239744" class="node-link"><div class="card card-developer"><div class="avatar"><i class="fas fa-code"></i></div><div class="name">BNK</div><div class="role">Developer</div></div></a></li>
                                                <li><a href="staff_profile.php?discord_id=1225781633219690608" class="node-link"><div class="card card-developer"><div class="avatar"><i class="fas fa-code"></i></div><div class="name">threep</div><div class="role">Developer</div></div></a></li>
                                            </ul>
                                        </li>
                                    </ul>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</div>

</body>
</html>