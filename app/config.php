<?php
declare(strict_types=1);

const BASE_PATH = __DIR__ . "/..";
const UPLOAD_PATH = BASE_PATH . "/uploads";
const DB_HOST = "127.0.0.1";
const DB_PORT = 3306;
const DB_NAME = "itsupport";
const DB_USER = "root";
const DB_PASSWORD = "";

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
