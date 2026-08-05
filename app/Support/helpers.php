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
        http_response_code(419);
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
