<?php
declare(strict_types=1);

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/Support/helpers.php";
require_once __DIR__ . "/Storage/mysql_store.php";
require_once __DIR__ . "/Support/uploads.php";
require_once __DIR__ . "/Support/rate_limiter.php";

$isProduction = env('APP_ENV') === 'production';

// Jangan pernah tampilkan detail error (path, stack trace) ke browser di produksi.
ini_set('display_errors', $isProduction ? '0' : '1');
ini_set('display_startup_errors', $isProduction ? '0' : '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$sessionPath = BASE_PATH . "/storage/sessions";
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0700, true);
}

// Configure secure session settings
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', $isProduction ? '1' : '0');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_lifetime', '0');
ini_set('session.gc_maxlifetime', '1800'); // 30 minutes

session_save_path($sessionPath);
session_start();
date_default_timezone_set("Asia/Jakarta");

// Check session timeout for authenticated users
if (isset($_SESSION["user_id"])) {
    checkSessionTimeout();
}
