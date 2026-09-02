<?php
declare(strict_types=1);

require_once __DIR__ . "/env.php";

// Load environment variables
loadEnv(__DIR__ . "/../.env");

const BASE_PATH = __DIR__ . "/..";
const UPLOAD_PATH = BASE_PATH . "/uploads";

// Database configuration from environment variables
define("DB_HOST", env("DB_HOST", "127.0.0.1"));
define("DB_PORT", (int) env("DB_PORT", 3306));
define("DB_NAME", env("DB_NAME", "itsupport"));
define("DB_USER", env("DB_USER", "root"));
define("DB_PASSWORD", env("DB_PASSWORD", ""));

const STATUSES = [
    "new" => "Baru",
    "assigned" => "Ditugaskan",
    "in_progress" => "Sedang dikerjakan",
    "completed" => "Selesai",
    "closed" => "Ditutup",
    "cancelled" => "Dibatalkan",
];

const PRIORITIES = [
    "low" => "Rendah",
    "medium" => "Sedang",
    "high" => "Tinggi",
    "urgent" => "Urgent",
];

const CATEGORIES = [
    "Perangkat",
    "Software",
    "Jaringan",
    "Akun & Akses",
    "Email",
    "Lainnya",
];
const MAX_SCREENSHOTS = 5;
const MAX_SCREENSHOT_SIZE = 10 * 1024 * 1024;
