<?php
// File: .php-cs-fixer.dist.php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__, // ریشه اصلی پروژه را برای فایل‌هایی مثل index.php بررسی می‌کند
        __DIR__ . '/admin',
        __DIR__ . '/includes',
    ])
    ->name('*.php') // فقط فایل‌های PHP را بررسی کن
    ->exclude('vendor'); // پوشه vendor را بررسی نکن

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'strict_param' => true,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder($finder);