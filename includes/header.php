<?php
// فایل: includes/header.php
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">

    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="stylesheet" href="/assets/css/admin_dashboard_redesign.css">
    <link rel="stylesheet" href="/assets/fonts/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">


    
</head>
<?php
$current_theme = $settings['vui_theme'] ?? 'vui-theme-default'; 
?>
<body class="<?= htmlspecialchars($current_theme) ?>">