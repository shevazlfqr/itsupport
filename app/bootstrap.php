<?php
declare(strict_types=1);

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/Support/helpers.php";
require_once __DIR__ . "/Storage/mysql_store.php";
require_once __DIR__ . "/Support/uploads.php";

$sessionPath = BASE_PATH . "/storage/sessions";
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0775, true);
}
session_save_path($sessionPath);
session_start();
date_default_timezone_set("Asia/Jakarta");
