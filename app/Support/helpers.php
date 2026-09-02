<?php
declare(strict_types=1);

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function redirect(string $url = "index.php"): void
{
    header("Location: " . $url);
    exit();
}

function csrf(): string
{
    return $_SESSION["csrf"] ??= bin2hex(random_bytes(24));
}

function verifyCsrf(): void
{
    if (!hash_equals($_SESSION["csrf"] ?? "", $_POST["csrf"] ?? "")) {
        // 403 dipakai (bukan 419) karena Apache/mod_php mengubah kode non-standar
        // seperti 419 menjadi 500, sehingga penyebab asli jadi menyesatkan di log.
        http_response_code(403);
        exit("Sesi formulir kedaluwarsa. Muat ulang halaman.");
    }
}

function flash(string $type, string $message): void
{
    $_SESSION["flash"] = compact("type", "message");
}

function currentUser(array $data): ?array
{
    $id = $_SESSION["user_id"] ?? null;
    foreach ($data["users"] as $user) {
        if ($user["id"] === $id && ($user["active"] ?? true)) {
            return $user;
        }
    }
    return null;
}

function userById(array $data, ?int $id): ?array
{
    foreach ($data["users"] as $user) {
        if ($user["id"] === $id) {
            return $user;
        }
    }
    return null;
}

function nextId(array $items, int $start = 1): int
{
    return empty($items) ? $start : max(array_column($items, "id")) + 1;
}

function canView(array $ticket, array $user): bool
{
    return in_array($user["role"], ["it", "admin"], true) ||
        $ticket["requester_id"] === $user["id"];
}

function allowedStatusTransitions(string $status): array
{
    $rules = [
        "new" => ["new", "assigned", "cancelled"],
        "assigned" => ["assigned", "in_progress", "cancelled", "new"],
        "in_progress" => ["in_progress", "completed", "cancelled"],
        "completed" => ["completed", "closed", "in_progress"],
        "closed" => ["closed"],
        "cancelled" => ["cancelled"],
    ];
    return $rules[$status] ?? [$status];
}

function fmtDate(string $date): string
{
    return date("d M Y, H:i", strtotime($date));
}

function relativeDate(string $date): string
{
    $diff = time() - strtotime($date);
    if ($diff < 60) {
        return "baru saja";
    }
    if ($diff < 3600) {
        return floor($diff / 60) . " menit lalu";
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . " jam lalu";
    }
    return floor($diff / 86400) . " hari lalu";
}

function checkSessionTimeout(): void
{
    $timeout = 1800; // 30 minutes in seconds

    if (isset($_SESSION["last_activity"])) {
        $elapsed = time() - $_SESSION["last_activity"];

        if ($elapsed > $timeout) {
            session_unset();
            session_destroy();
            session_start();
            flash("error", "Sesi Anda telah berakhir karena tidak aktif. Silakan login kembali.");
            redirect("index.php");
        }
    }

    $_SESSION["last_activity"] = time();
}

function validateStrongPassword(string $password): array
{
    $errors = [];

    if (mb_strlen($password) < 8) {
        $errors[] = "minimal 8 karakter";
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "minimal 1 huruf besar";
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "minimal 1 huruf kecil";
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "minimal 1 angka";
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "minimal 1 karakter spesial (!@#$%^&* dll)";
    }

    return $errors;
}

/**
 * SLA deadlines in hours per priority
 */
function slaHours(): array
{
    return [
        "urgent" => 4,
        "high"   => 8,
        "medium" => 24,
        "low"    => 72,
    ];
}

/**
 * Calculate SLA deadline datetime string from created_at and priority
 */
function calcSlaDeadline(string $createdAt, string $priority): string
{
    $hours = slaHours()[$priority] ?? 24;
    return date(DATE_ATOM, strtotime($createdAt) + $hours * 3600);
}

/**
 * Return SLA status info for a ticket
 * Returns: ['status' => 'ok'|'warning'|'breached', 'label' => '...', 'seconds_left' => int]
 */
function slaStatus(array $ticket): ?array
{
    if (empty($ticket["sla_deadline"])) return null;
    // Closed/cancelled/completed tickets — SLA no longer tracked
    if (in_array($ticket["status"], ["closed", "cancelled", "completed"], true)) return null;

    $deadline = strtotime($ticket["sla_deadline"]);
    $now      = time();
    $diff     = $deadline - $now;

    $hours = slaHours()[$ticket["priority"]] ?? 24;
    $warningThreshold = $hours * 3600 * 0.25; // warn in last 25% of SLA time

    if ($diff <= 0) {
        return ["status" => "breached", "label" => "SLA Terlampaui", "seconds_left" => $diff];
    }
    if ($diff <= $warningThreshold) {
        $mins = ceil($diff / 60);
        if ($mins >= 60) {
            $label = ceil($diff / 3600) . " jam lagi";
        } else {
            $label = $mins . " menit lagi";
        }
        return ["status" => "warning", "label" => $label, "seconds_left" => $diff];
    }
    $hrs = ceil($diff / 3600);
    return ["status" => "ok", "label" => "SLA: " . $hrs . " jam", "seconds_left" => $diff];
}
