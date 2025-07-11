<?php
// File: admin/image_proxy.php

// دریافت URL عکس از پارامتر کوئری
$imageUrl = $_GET['url'] ?? '';

// اگر URL خالی است، خارج شو
if (empty($imageUrl)) {
    header("HTTP/1.0 400 Bad Request");
    exit('Error: Image URL not provided.');
}

// --- بخش امنیتی بسیار مهم ---
// اطمینان حاصل می‌کنیم که فقط از دامنه‌های دیسکورد می‌توان عکس گرفت
// این کار از سوءاستفاده از اسکریپت شما به عنوان یک پروکسی باز جلوگیری می‌کند
$allowed_domains = [
    'cdn.discordapp.com',
    'media.discordapp.net',
];

$url_parts = parse_url($imageUrl);
if (!isset($url_parts['host']) || !in_array($url_parts['host'], $allowed_domains)) {
    header("HTTP/1.0 403 Forbidden");
    exit('Error: Access to this domain is not allowed.');
}

// استفاده از cURL برای دانلود محتوای عکس از لینک
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $imageUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // دنبال کردن ریدایرکت‌ها
curl_setopt($ch, CURLOPT_TIMEOUT, 10); // ۱۰ ثانیه مهلت
// ممکن است برخی سرورها نیاز به User-Agent داشته باشند
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

$imageData = curl_exec($ch);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// اگر دانلود موفقیت‌آمیز بود
if ($httpCode == 200 && $imageData) {
    // هدر صحیح را برای نمایش عکس تنظیم می‌کنیم
    header('Content-Type: ' . $contentType);
    // و محتوای عکس را به مرورگر ارسال می‌کنیم
    echo $imageData;
} else {
    // در صورت بروز خطا، یک تصویر جایگزین یا پیام خطا نمایش می‌دهیم
    header("HTTP/1.0 404 Not Found");
    // می‌توانید به جای این متن، یک عکس پیش‌فرض را نمایش دهید
    exit('Error: Could not fetch the image.');
}