<?php
declare(strict_types=1);

require_once __DIR__ . "/app/bootstrap.php";

// Security Headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

// Content Security Policy
$csp = [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline'",
    "style-src 'self' 'unsafe-inline'",
    "img-src 'self' data:",
    "font-src 'self'",
    "connect-src 'self'",
    "frame-ancestors 'none'",
    "base-uri 'self'",
    "form-action 'self'",
];
header("Content-Security-Policy: " . implode("; ", $csp));

// HTTPS enforcement (only in production)
if (env('APP_ENV') === 'production' && empty($_SERVER['HTTPS'])) {
    $redirectUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $redirectUrl);
    exit();
}

// HSTS (only send in production over HTTPS)
if (env('APP_ENV') === 'production' && !empty($_SERVER['HTTPS'])) {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}

require __DIR__ . "/app/Http/actions.php";

$data = readData();
$user = currentUser($data);
$flash = $_SESSION["flash"] ?? null;
unset($_SESSION["flash"]);
require __DIR__ . "/app/Http/updates_api.php";

// CSV Export handler - must run before any HTML output
if (isset($_GET["export"]) && $_GET["export"] === "csv" && $user) {
    require_once __DIR__ . "/app/Support/csv_export.php";
    exportTicketsCsv($data, $user);
}
if (!$user): ?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light dark"><title>Helpdesk — IT Support</title><script>document.documentElement.dataset.theme=new URLSearchParams(location.search).get('theme')||localStorage.getItem('helpdesk-theme')||'light'</script><link rel="stylesheet" href="assets/styles.css?v=20260902-1"></head>
<body class="login-page"><main class="login-shell"><section class="login-visual"><div class="brand brand-light"><span class="brand-mark">H</span><span>Helpdesk</span></div><div class="hero-copy"><span class="eyebrow">INTERNAL IT SUPPORT</span><h1>Bantuan IT,<br><em>tanpa ribet.</em></h1><p>Laporkan kendala, pantau progres, dan kembali bekerja dengan tenang.</p></div><div class="visual-stats"><div><strong>01</strong><span>Buat laporan</span></div><div><strong>02</strong><span>Tim IT menangani</span></div><div><strong>03</strong><span>Pantau sampai selesai</span></div></div></section>
<section class="login-panel"><form method="post" class="login-form"><input type="hidden" name="csrf" value="<?= e(
    csrf(),
) ?>"><input type="hidden" name="action" value="login"><div class="mobile-brand brand"><span class="brand-mark">H</span><span>Helpdesk</span></div><span class="eyebrow dark">PORTAL KARYAWAN</span><h2>Masuk ke akunmu</h2><p class="muted">Gunakan akun kantor untuk mengakses layanan IT.</p><?php if (
    $flash
): ?><div class="alert <?= e($flash["type"]) ?>"><?= e(
    $flash["message"],
) ?></div><?php endif; ?><label>Email kantor<input type="email" name="email" placeholder="nama@kantor.local" required autofocus></label><label>Password<input type="password" name="password" placeholder="Masukkan password" required></label><button class="btn primary full" type="submit">Masuk ke Helpdesk <span>→</span></button></form><p class="login-footer">Butuh akses? Hubungi administrator IT kantor.</p></section></main><script src="assets/app.js?v=20260902-1"></script></body></html>
<?php exit();endif;

$tickets = array_values(
    array_filter($data["tickets"], fn($t) => canView($t, $user)),
);
usort(
    $tickets,
    fn($a, $b) => strtotime($b["updated_at"]) <=> strtotime($a["updated_at"]),
);
$view = $_GET["view"] ?? "dashboard";
if ($view === "users" && $user["role"] !== "admin") {
    http_response_code(403);
    exit("Akses khusus Admin.");
}
if ($view === "analytics" && !in_array($user["role"], ["it", "admin"], true)) {
    http_response_code(403);
    exit("Akses khusus tim IT dan Admin.");
}
$ticketId = isset($_GET["ticket"]) ? (int) $_GET["ticket"] : null;
$selected = null;
if ($ticketId) {
    foreach ($tickets as $t) {
        if ($t["id"] === $ticketId) {
            $selected = $t;
        }
    };
}
if ($selected) {
    $readAt = date(DATE_ATOM);
    updateData(function (&$d) use ($user, $ticketId, $readAt) {
        $found = false;
        foreach ($d["reads"] as &$r) {
            if (
                $r["ticket_id"] === $ticketId &&
                $r["user_id"] === $user["id"]
            ) {
                $r["last_read_at"] = $readAt;
                $found = true;
            }
        }
        if (!$found) {
            $d["reads"][] = [
                "ticket_id" => $ticketId,
                "user_id" => $user["id"],
                "last_read_at" => $readAt,
            ];
        }
    });
    $found = false;
    foreach ($data["reads"] as &$r) {
        if ($r["ticket_id"] === $ticketId && $r["user_id"] === $user["id"]) {
            $r["last_read_at"] = $readAt;
            $found = true;
        }
    }
    if (!$found) {
        $data["reads"][] = [
            "ticket_id" => $ticketId,
            "user_id" => $user["id"],
            "last_read_at" => $readAt,
        ];
    }
}
$unreadByTicket = [];
$unreadLatest = [];
foreach ($tickets as $t) {
    $last = "1970-01-01T00:00:00+00:00";
    foreach ($data["reads"] as $r) {
        if ($r["ticket_id"] === $t["id"] && $r["user_id"] === $user["id"]) {
            $last = $r["last_read_at"];
        }
    }
    foreach ($data["comments"] as $c) {
        if (
            $c["ticket_id"] === $t["id"] &&
            $c["user_id"] !== $user["id"] &&
            strtotime($c["created_at"]) > strtotime($last)
        ) {
            $unreadByTicket[$t["id"]] = ($unreadByTicket[$t["id"]] ?? 0) + 1;
            $unreadLatest[$t["id"]] = "Komentar baru";
        }
    }
    foreach ($data["activities"] as $a) {
        if (
            $a["ticket_id"] === $t["id"] &&
            $a["user_id"] !== $user["id"] &&
            $a["message"] !== "Menambahkan komentar" &&
            strtotime($a["created_at"]) > strtotime($last)
        ) {
            $unreadByTicket[$t["id"]] = ($unreadByTicket[$t["id"]] ?? 0) + 1;
            $unreadLatest[$t["id"]] = $a["message"];
        }
    }
}
$unreadTotal = array_sum($unreadByTicket);
$mine = $user["role"] === "it" && ($_GET["mine"] ?? "") === "1";
$search = trim($_GET["q"] ?? "");
$statusFilter = $_GET["status"] ?? "";
$priorityFilter = $_GET["priority"] ?? "";
$categoryFilter = $_GET["category"] ?? "";
$dateFrom = $_GET["date_from"] ?? "";
$dateTo = $_GET["date_to"] ?? "";

$filtered = array_values(
    array_filter(
        $tickets,
        function ($t) use ($mine, $search, $statusFilter, $priorityFilter, $categoryFilter, $dateFrom, $dateTo, $user, $data) {
            if ($mine && $t["assignee_id"] !== $user["id"]) return false;
            if ($statusFilter && $t["status"] !== $statusFilter) return false;
            if ($priorityFilter && $t["priority"] !== $priorityFilter) return false;
            if ($categoryFilter && $t["category"] !== $categoryFilter) return false;
            if ($search) {
                // Search by subject, ticket ID, and description
                $requester = null;
                foreach ($data["users"] as $u) {
                    if ($u["id"] === $t["requester_id"]) { $requester = $u; break; }
                }
                $haystack = $t["subject"] . " " . $t["id"] . " " . $t["description"] . " " . ($requester["name"] ?? "");
                if (stripos($haystack, $search) === false) return false;
            }
            if ($dateFrom && strtotime($t["created_at"]) < strtotime($dateFrom)) return false;
            if ($dateTo && strtotime($t["created_at"]) > strtotime($dateTo . " 23:59:59")) return false;
            return true;
        }
    ),
);
$counts = [];
foreach (STATUSES as $key => $label) {
    $counts[$key] = count(
        array_filter($tickets, fn($t) => $t["status"] === $key),
    );
}
$itUsers = array_values(
    array_filter($data["users"], fn($u) => $u["role"] === "it"),
);
$categoryCounts = array_fill_keys(CATEGORIES, 0);
$priorityCounts = array_fill_keys(array_keys(PRIORITIES), 0);
foreach ($tickets as $t) {
    $categoryCounts[$t["category"]] = $categoryCounts[$t["category"]] ?? 0;
    $categoryCounts[$t["category"]]++;
    $priorityCounts[$t["priority"]]++;
}
$techStats = [];
foreach ($itUsers as $it) {
    $handled = array_values(
        array_filter($tickets, fn($t) => $t["assignee_id"] === $it["id"]),
    );
    $techStats[] = [
        "user" => $it,
        "total" => count($handled),
        "active" => count(
            array_filter(
                $handled,
                fn($t) => in_array(
                    $t["status"],
                    ["assigned", "in_progress"],
                    true,
                ),
            ),
        ),
        "done" => count(
            array_filter(
                $handled,
                fn($t) => in_array($t["status"], ["completed", "closed"], true),
            ),
        ),
    ];
}
usort($techStats, fn($a, $b) => $b["total"] <=> $a["total"]);
$recentActivities = $data["activities"];
usort(
    $recentActivities,
    fn($a, $b) => strtotime($b["created_at"]) <=> strtotime($a["created_at"]),
);
$recentActivities = array_slice($recentActivities, 0, 6);
$statusColors = [
    "new" => "#4d8cff",
    "assigned" => "#8369e8",
    "in_progress" => "#f3a72f",
    "completed" => "#28a978",
    "closed" => "#718096",
    "cancelled" => "#e15b64",
];
$chartParts = [];
$chartCursor = 0;
$ticketTotal = max(count($tickets), 1);
foreach (STATUSES as $key => $label) {
    $next = $chartCursor + ($counts[$key] / $ticketTotal) * 100;
    $chartParts[] =
        $statusColors[$key] . " " . $chartCursor . "% " . $next . "%";
    $chartCursor = $next;
}
$statusGradient = implode(",", $chartParts);
$maxCategory = max(1, max($categoryCounts));
$techTotals = array_map(fn($stat) => $stat["total"], $techStats);
$maxTech = $techTotals ? max(1, max($techTotals)) : 1;

$crumb = $selected
    ? "Ticket #" . $selected["id"]
    : ($view === "new"
        ? "Buat Ticket"
        : ($view === "tickets"
            ? "Semua Ticket"
            : ($view === "users"
                ? "Kelola User"
                : ($view === "analytics" ? "Analitik" : "Dashboard"))));

// ===== Analytics data (only computed for the analytics view) =====
$analytics = null;
if ($view === "analytics") {
    $rangeDays = (int) ($_GET["range"] ?? 30);
    if (!in_array($rangeDays, [7, 30, 90], true)) $rangeDays = 30;
    $rangeStart = strtotime("-" . ($rangeDays - 1) . " days midnight");

    // Volume trend: tickets created per day
    $volumeLabels = [];
    $volumeCreated = [];
    $volumeResolved = [];
    for ($i = 0; $i < $rangeDays; $i++) {
        $dayStart = strtotime("+$i days", $rangeStart);
        $dayEnd = $dayStart + 86400;
        $volumeLabels[] = date("d/m", $dayStart);
        $created = 0;
        $resolved = 0;
        foreach ($tickets as $t) {
            $c = strtotime($t["created_at"]);
            if ($c >= $dayStart && $c < $dayEnd) $created++;
            if (in_array($t["status"], ["completed", "closed"], true)) {
                $u = strtotime($t["updated_at"]);
                if ($u >= $dayStart && $u < $dayEnd) $resolved++;
            }
        }
        $volumeCreated[] = $created;
        $volumeResolved[] = $resolved;
    }

    // Average resolution time (hours) per category
    $resolutionByCategory = [];
    foreach (CATEGORIES as $cat) {
        $resolutionByCategory[$cat] = ["sum" => 0, "count" => 0];
    }
    $totalResolutionSum = 0;
    $totalResolutionCount = 0;
    $slaBreached = 0;
    $slaOnTime = 0;
    foreach ($tickets as $t) {
        if (!in_array($t["status"], ["completed", "closed"], true)) continue;
        $hours = (strtotime($t["updated_at"]) - strtotime($t["created_at"])) / 3600;
        if ($hours < 0) $hours = 0;
        if (isset($resolutionByCategory[$t["category"]])) {
            $resolutionByCategory[$t["category"]]["sum"] += $hours;
            $resolutionByCategory[$t["category"]]["count"]++;
        }
        $totalResolutionSum += $hours;
        $totalResolutionCount++;
        // SLA compliance
        if (!empty($t["sla_deadline"])) {
            if (strtotime($t["updated_at"]) > strtotime($t["sla_deadline"])) {
                $slaBreached++;
            } else {
                $slaOnTime++;
            }
        }
    }
    $avgResolutionLabels = [];
    $avgResolutionValues = [];
    foreach ($resolutionByCategory as $cat => $agg) {
        $avgResolutionLabels[] = $cat;
        $avgResolutionValues[] = $agg["count"] ? round($agg["sum"] / $agg["count"], 1) : 0;
    }

    $avgResolutionOverall = $totalResolutionCount
        ? round($totalResolutionSum / $totalResolutionCount, 1)
        : 0;
    $slaTotal = $slaBreached + $slaOnTime;
    $slaCompliance = $slaTotal ? round(($slaOnTime / $slaTotal) * 100) : 100;

    // Active SLA risk right now
    $slaAtRisk = 0;
    $slaOverdue = 0;
    foreach ($tickets as $t) {
        $s = slaStatus($t);
        if (!$s) continue;
        if ($s["status"] === "breached") $slaOverdue++;
        elseif ($s["status"] === "warning") $slaAtRisk++;
    }

    $analytics = [
        "range_days" => $rangeDays,
        "volume" => [
            "labels" => $volumeLabels,
            "created" => $volumeCreated,
            "resolved" => $volumeResolved,
        ],
        "resolution" => [
            "labels" => $avgResolutionLabels,
            "values" => $avgResolutionValues,
        ],
        "status" => [
            "labels" => array_values(STATUSES),
            "values" => array_map(fn($k) => $counts[$k], array_keys(STATUSES)),
            "colors" => array_map(fn($k) => $statusColors[$k], array_keys(STATUSES)),
        ],
        "priority" => [
            "labels" => array_values(PRIORITIES),
            "values" => array_map(fn($k) => $priorityCounts[$k], array_keys(PRIORITIES)),
        ],
        "tech" => [
            "labels" => array_map(fn($s) => $s["user"]["name"], $techStats),
            "done" => array_map(fn($s) => $s["done"], $techStats),
            "active" => array_map(fn($s) => $s["active"], $techStats),
        ],
        "kpi" => [
            "avg_resolution" => $avgResolutionOverall,
            "sla_compliance" => $slaCompliance,
            "sla_at_risk" => $slaAtRisk,
            "sla_overdue" => $slaOverdue,
            "total_resolved" => $totalResolutionCount,
        ],
    ];
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light dark"><title>Helpdesk IT Support</title><script>document.documentElement.dataset.theme=new URLSearchParams(location.search).get('theme')||localStorage.getItem('helpdesk-theme')||'light'</script><link rel="stylesheet" href="assets/styles.css?v=20260902-1"></head><body>
<div class="app-shell"><aside class="sidebar"><a class="brand" href="index.php"><span class="brand-mark">H</span><span>Helpdesk</span></a><nav><span class="nav-label">WORKSPACE</span><a class="<?= !$ticketId &&
$view === "dashboard"
    ? "active"
    : "" ?>" href="index.php"><i>⌂</i> Dashboard</a><a class="<?= $view ===
    "tickets" || $ticketId
    ? "active"
    : "" ?>" href="index.php?view=tickets"><i>▤</i> Semua Ticket <b><?= count(
    $tickets,
) ?></b></a><a class="<?= $view === "new"
    ? "active"
    : "" ?>" href="index.php?view=new"><i>＋</i> Buat Ticket</a><?php if (in_array($user["role"], ["it","admin"], true)): ?><a class="<?= $view === "analytics" ? "active" : "" ?>" href="index.php?view=analytics"><i>▨</i> Analitik</a><?php endif; ?><?php if ($user["role"] === "admin"): ?><a class="<?= $view === "users" ? "active" : "" ?>" href="index.php?view=users"><i>♙</i> Kelola User <b><?= count($data["users"]) ?></b></a><?php endif; ?></nav><div class="sidebar-help"><span>?</span><strong>Butuh bantuan cepat?</strong><p>Untuk insiden kritis, hubungi ext. 101.</p></div><div class="profile"><div class="avatar"><?= e(
    strtoupper(substr($user["name"], 0, 1)),
) ?></div><div><strong><?= e($user["name"]) ?></strong><span><?= e(
    $user["role"] === "admin"
        ? "Administrator"
        : ($user["role"] === "it"
            ? "Tim IT"
            : $user["department"]),
) ?></span></div><form method="post"><input type="hidden" name="csrf" value="<?= e(
    csrf(),
) ?>"><input type="hidden" name="action" value="logout"><button title="Keluar">↗</button></form></div></aside>
<main class="content"><header class="topbar"><button class="menu-toggle" aria-label="Menu">☰</button><div class="crumb">IT Support <span>/</span> <?= e($crumb) ?></div><div class="top-actions"><div class="notification"><button class="notification-button" type="button" aria-label="Notifikasi"><span>🔔</span><?php if (
    $unreadTotal
): ?><b><?= $unreadTotal ?></b><?php endif; ?></button><div class="notification-menu"><div class="notification-head"><div><strong>Aktivitas terbaru</strong><span>Update ticket untuk kamu</span></div><i><?= $unreadTotal ?></i></div><div class="notification-list"><?php if (
    !$unreadByTicket
): ?><div class="notification-empty"><span>✓</span><strong>Semua sudah dibaca</strong><p>Belum ada aktivitas baru.</p></div><?php else:foreach (
        $unreadByTicket
        as $noticeTicketId => $amount
    ):

        $noticeTicket = null;
        foreach ($tickets as $t) {
            if ($t["id"] === $noticeTicketId) {
                $noticeTicket = $t;
            }
        }
        if (!$noticeTicket) {
            continue;
        }
        ?><a href="index.php?ticket=<?= $noticeTicketId ?>"><i><?= e(
    strtoupper(substr($noticeTicket["subject"], 0, 1)),
) ?></i><span><strong><?= e($noticeTicket["subject"]) ?></strong><small><?= e(
    $unreadLatest[$noticeTicketId] ?? "Update ticket",
) ?> · #<?= $noticeTicketId ?></small></span><b><?= $amount ?></b></a><?php
    endforeach;endif; ?></div></div></div><a class="btn primary compact" href="index.php?view=new">＋ Buat Ticket</a></div></header><div class="page">
<?php if (
    $flash
): ?><script type="application/json" id="flash-data"><?= json_encode(
    $flash,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP,
) ?></script><?php endif; ?>
<?php if ($selected):

    $requester = userById($data, $selected["requester_id"]);
    $assignee = userById($data, $selected["assignee_id"]);
    $comments = array_values(
        array_filter(
            $data["comments"],
            fn($c) => $c["ticket_id"] === $selected["id"],
        ),
    );
    $attachments = array_values(
        array_filter(
            $data["attachments"],
            fn($a) => $a["ticket_id"] === $selected["id"],
        ),
    );
    $activities = array_values(
        array_filter(
            $data["activities"],
            fn($a) => $a["ticket_id"] === $selected["id"],
        ),
    );
    usort(
        $activities,
        fn($a, $b) => strtotime($b["created_at"]) <=>
            strtotime($a["created_at"]),
    );
    ?>
<script type="application/json" id="ticket-ui-data"><?= json_encode(
    [
        "status" => $selected["status"],
        "role" => $user["role"],
        "is_requester" => $selected["requester_id"] === $user["id"],
        "csrf" => csrf(),
        "resolution_note" => in_array(
            $selected["status"],
            ["completed", "closed"],
            true,
        )
            ? $selected["resolution_note"] ?? ""
            : "",
        "comments" => array_map(
            fn($c) => ["id" => $c["id"], "date" => fmtDate($c["created_at"])],
            $comments,
        ),
        "attachments" => array_map(
            fn($a) => [
                "name" => $a["original_name"],
                "mime" => $a["mime"],
                "size" => $a["size"],
            ],
            $attachments,
        ),
    ],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP,
) ?></script>
<a class="back-link" href="index.php?view=tickets">← Kembali ke daftar ticket</a><div class="ticket-head"><div><div class="ticket-meta"><span>#<?= e(
    $selected["id"],
) ?></span><span class="badge priority-<?= e($selected["priority"]) ?>"><?= e(
    PRIORITIES[$selected["priority"]],
) ?></span><span class="badge status-<?= e($selected["status"]) ?>"><?= e(
    STATUSES[$selected["status"]],
) ?></span><?php $headSla = slaStatus($selected); if (
    $headSla
): ?><span class="sla-badge sla-<?= e($headSla["status"]) ?>" data-sla-deadline="<?= e($selected["sla_deadline"]) ?>" data-sla-priority="<?= e($selected["priority"]) ?>"><i></i><?= e($headSla["label"]) ?></span><?php endif; ?></div><h1><?= e(
    $selected["subject"],
) ?></h1><p>Dibuat oleh <strong><?= e(
    $requester["name"] ?? "-",
) ?></strong> · <?= e(fmtDate($selected["created_at"])) ?></p></div></div>
<?php
$hasActiveSolution =
    in_array($selected["status"], ["completed", "closed"], true) &&
    !empty($selected["resolution_note"]);
if ($attachments || $hasActiveSolution): ?><div class="ticket-extras"><?php
if (
    $attachments
): ?><div class="card attachments"><strong>Lampiran screenshot</strong><?php foreach (
    $attachments
    as $attachment
):
    $isHeic = in_array(
        $attachment["mime"],
        [
            "image/heic",
            "image/heif",
            "image/heic-sequence",
            "image/heif-sequence",
        ],
        true,
    ); ?><a href="uploads/<?= e(
    $attachment["stored_name"],
) ?>" target="_blank"><?php if (
    $isHeic
): ?><span class="file-placeholder">HEIC</span><?php else: ?><img src="uploads/<?= e(
    $attachment["stored_name"],
) ?>" alt="<?= e($attachment["original_name"]) ?>"><?php endif; ?><span><?= e(
    $attachment["original_name"],
) ?></span></a><?php
endforeach; ?></div><?php endif;
if (
    $hasActiveSolution
): ?><div class="card resolution-note"><strong>Catatan penyelesaian</strong><p><?= nl2br(
    e($selected["resolution_note"]),
) ?></p></div><?php endif;
?></div><?php endif;
?><div id="resolution-data" data-note="<?= e(
    $selected["resolution_note"] ?? "",
) ?>" hidden></div>
<div class="detail-grid"><section class="detail-main"><article class="card description-card"><div class="author-row"><div class="avatar"><?= e(
    strtoupper(substr($requester["name"] ?? "?", 0, 1)),
) ?></div><div><strong><?= e($requester["name"] ?? "-") ?></strong><span><?= e(
    $requester["department"] ?? "",
) ?></span></div></div><p><?= nl2br(
    e($selected["description"]),
) ?></p></article><h3 class="section-title">Percakapan <span><?= count(
    $comments,
) ?></span></h3><?php
if (
    !$comments
): ?><div class="empty-mini">Belum ada percakapan. Tulis update pertama di bawah.</div><?php endif;
foreach ($comments as $c):
    $cu = userById(
        $data,
        $c["user_id"],
    ); ?><article class="comment"><div class="avatar <?= ($cu["role"] ?? "") ===
"it"
    ? "it-avatar"
    : "" ?>"><?= e(
    strtoupper(substr($cu["name"] ?? "?", 0, 1)),
) ?></div><div><div class="comment-head"><strong><?= e(
    $cu["name"] ?? "-",
) ?> <?= ($cu["role"] ?? "") === "it"
     ? "<small>Tim IT</small>"
     : "" ?></strong><time><?= e(
    relativeDate($c["created_at"]),
) ?></time></div><p><?= nl2br(e($c["message"])) ?></p></div></article><?php
endforeach;
?><form method="post" class="comment-form"><input type="hidden" name="csrf" value="<?= e(
    csrf(),
) ?>"><input type="hidden" name="action" value="comment"><input type="hidden" name="ticket_id" value="<?= $selected[
    "id"
] ?>"><textarea name="message" placeholder="Tulis komentar atau update..." aria-label="Tulis komentar atau update untuk ticket ini" required></textarea><div><span>Update akan terlihat oleh requester dan tim IT.</span><button class="btn primary" type="submit">Kirim Komentar</button></div></form></section>
<aside class="detail-side"><?php
if (
    $user["role"] === "it" &&
    !$selected["assignee_id"]
): ?><form method="post" class="card action-card"><input type="hidden" name="csrf" value="<?= e(
    csrf(),
) ?>"><input type="hidden" name="action" value="claim_ticket"><input type="hidden" name="ticket_id" value="<?= $selected[
    "id"
] ?>"><h3>Ticket belum ditugaskan</h3><p class="muted">Ambil ticket ini jika kamu siap menanganinya.</p><button class="btn primary full" type="submit">Ambil Ticket</button></form><?php endif;
if (
    $user["role"] === "admin" ||
    ($user["role"] === "it" && $selected["assignee_id"] === $user["id"])
): ?><form method="post" class="card action-card"><input type="hidden" name="csrf" value="<?= e(
    csrf(),
) ?>"><input type="hidden" name="action" value="update_ticket"><input type="hidden" name="ticket_id" value="<?= $selected[
    "id"
] ?>"><h3>Kelola Ticket</h3><label>Status<select name="status"><?php foreach (
    STATUSES
    as $k => $v
): ?><option value="<?= $k ?>" <?= $selected["status"] === $k
    ? "selected"
    : "" ?>><?= e($v) ?></option><?php endforeach; ?></select></label><?php if (
    $user["role"] === "admin"
): ?><label>Ditugaskan ke<select name="assignee_id"><option value="">Belum ditugaskan</option><?php foreach (
    $itUsers
    as $it
): ?><option value="<?= $it["id"] ?>" <?= $selected["assignee_id"] === $it["id"]
    ? "selected"
    : "" ?>><?= e(
    $it["name"],
) ?></option><?php endforeach; ?></select></label><?php endif; ?><button class="btn dark full" type="submit">Simpan Perubahan</button></form><?php endif;
?><div class="card info-card"><h3>Detail Ticket</h3><dl><div><dt>Kategori</dt><dd><?= e(
    $selected["category"],
) ?></dd></div><div><dt>Prioritas</dt><dd><?= e(
    PRIORITIES[$selected["priority"]],
) ?></dd></div><?php $selectedSla = slaStatus($selected); ?><div><dt>Target SLA</dt><dd><?php if (
    $selectedSla
): ?><span class="sla-badge sla-<?= e($selectedSla["status"]) ?>" data-sla-deadline="<?= e($selected["sla_deadline"]) ?>" data-sla-priority="<?= e($selected["priority"]) ?>"><i></i><?= e($selectedSla["label"]) ?></span><small class="sla-note"><?= e(fmtDate($selected["sla_deadline"])) ?></small><?php elseif (
    !empty($selected["sla_deadline"])
): ?><span class="sla-badge sla-done"><i></i>Tidak dipantau lagi</span><?php else: ?>—<?php endif; ?></dd></div><div><dt>Ditangani oleh</dt><dd><?= e(
    $assignee["name"] ?? "Belum ditugaskan",
) ?></dd></div><div><dt>Terakhir diperbarui</dt><dd><?= e(
    relativeDate($selected["updated_at"]),
) ?></dd></div></dl></div><div class="card timeline-card"><h3>Riwayat Aktivitas</h3><?php foreach (
    $activities
    as $a
):
    $au = userById(
        $data,
        $a["user_id"],
    ); ?><div class="timeline-item"><i></i><p><strong><?= e(
    $au["name"] ?? "-",
) ?></strong> <?= e(strtolower($a["message"])) ?><span><?= e(
    fmtDate($a["created_at"]),
) ?></span></p></div><?php
endforeach; ?></div></aside></div>
<?php
elseif ($view === "new"): ?>
<div class="page-title"><div><span class="eyebrow dark">LAYANAN IT</span><h1>Buat ticket baru</h1><p>Ceritakan kendala dengan jelas supaya tim IT bisa membantu lebih cepat.</p></div></div><form method="post" class="card create-form"><input type="hidden" name="csrf" value="<?= e(
    csrf(),
) ?>"><input type="hidden" name="action" value="create_ticket"><div class="form-section"><span class="step">01</span><div><h3>Apa yang bermasalah?</h3><p>Pilih kategori yang paling sesuai.</p></div></div><div class="category-grid"><?php foreach (
    CATEGORIES
    as $i => $cat
): ?><label class="category-option"><input type="radio" name="category" value="<?= e(
    $cat,
) ?>" <?= $i === 0 ? "required" : "" ?>><span><b><?= [
    "⌨",
    "⬡",
    "⌁",
    "◉",
    "✉",
    "•••",
][$i] ?></b><?= e(
    $cat,
) ?></span></label><?php endforeach; ?></div><hr><div class="form-section"><span class="step">02</span><div><h3>Jelaskan kendalanya</h3><p>Sertakan pesan error dan langkah yang sudah dicoba.</p></div></div><label>Judul ticket<input type="text" name="subject" minlength="5" maxlength="100" placeholder="Contoh: Tidak bisa login ke email kantor" required></label><label>Deskripsi<textarea name="description" minlength="10" rows="6" placeholder="Jelaskan kapan masalah mulai terjadi, dampaknya, dan apa yang sudah kamu coba..." required></textarea></label><label>Prioritas<select name="priority"><?php foreach (
    PRIORITIES
    as $k => $v
): ?><option value="<?= $k ?>" <?= $k === "medium" ? "selected" : "" ?>><?= e(
    $v,
) ?></option><?php endforeach; ?></select><small>Pilih Urgent hanya jika pekerjaan terhenti total atau berdampak ke banyak orang.</small></label><div class="form-actions"><a class="btn ghost" href="index.php">Batal</a><button class="btn primary" type="submit">Kirim Ticket <span>→</span></button></div></form>
<?php elseif ($view === "users"):
    $managedUsers = $data["users"];
    usort($managedUsers, fn($left, $right) => strcasecmp($left["name"], $right["name"]));
    $roleLabels = ["employee" => "Karyawan", "it" => "Tim IT", "admin" => "Admin"];
?>
<div class="page-title user-page-title"><div><span class="eyebrow dark">ADMINISTRATION</span><h1>Kelola user</h1><p>Tambah akun karyawan dan atur aksesnya tanpa registrasi publik.</p></div><div class="user-page-actions"><span class="user-count"><?= count($managedUsers) ?> akun</span><button class="btn primary js-add-user" type="button">＋ Tambah Karyawan</button></div></div><div id="user-management-data" data-csrf="<?= e(csrf()) ?>" hidden></div>
<div class="user-management-grid">
<section class="card user-directory"><header><div><h2>Daftar pengguna</h2><p>Seluruh akun yang dapat mengakses portal.</p></div></header><div class="user-list">
<?php foreach ($managedUsers as $managedUser): $isActive = $managedUser["active"] ?? true; $editableUser = ["id" => $managedUser["id"], "name" => $managedUser["name"], "email" => $managedUser["email"], "role" => $managedUser["role"], "job_title" => $managedUser["job_title"] ?? "", "department" => $managedUser["department"] ?? ""]; ?><article class="user-row <?= $isActive ? "" : "is-inactive" ?>"><div class="avatar"><?= e(strtoupper(substr($managedUser["name"], 0, 1))) ?></div><div class="user-identity"><strong><?= e($managedUser["name"]) ?></strong><span><?= e($managedUser["email"]) ?></span><small><?= e($managedUser["job_title"] ?? "Karyawan") ?> · <?= e($managedUser["department"] ?? "-") ?></small></div><div class="user-role"><span class="role-badge role-<?= e($managedUser["role"]) ?>"><?= e($roleLabels[$managedUser["role"]] ?? $managedUser["role"]) ?></span><small class="account-state <?= $isActive ? "active" : "" ?>"><?= $isActive ? "Aktif" : "Nonaktif" ?></small></div><div class="user-actions"><button class="btn ghost compact js-edit-user" type="button" data-user="<?= e(json_encode($editableUser, JSON_UNESCAPED_UNICODE)) ?>">Edit</button><?php if ($managedUser["id"] !== $user["id"]): ?><form method="post" data-confirm-title="<?= $isActive ? "Nonaktifkan akun?" : "Aktifkan akun?" ?>" data-confirm-message="<?= e($managedUser["name"]) ?> <?= $isActive ? "tidak dapat login sampai diaktifkan kembali." : "akan dapat login kembali ke portal." ?>" data-confirm-label="<?= $isActive ? "Nonaktifkan" : "Aktifkan" ?>"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="toggle_user"><input type="hidden" name="user_id" value="<?= $managedUser["id"] ?>"><button class="btn ghost compact" type="submit"><?= $isActive ? "Nonaktifkan" : "Aktifkan" ?></button></form><?php endif; ?></div></article><?php endforeach; ?>
</div></section></div>
<?php elseif ($view === "analytics"): ?>
<div class="page-title analytics-page-title"><div><span class="eyebrow dark">LAPORAN &amp; INSIGHT</span><h1>Analitik layanan IT</h1><p>Tren ticket, kecepatan penyelesaian, dan kepatuhan SLA tim.</p></div><div class="range-switch"><?php foreach ([7 => "7 hari", 30 => "30 hari", 90 => "90 hari"] as $rangeValue => $rangeLabel): ?><a class="<?= $analytics["range_days"] === $rangeValue ? "active" : "" ?>" href="index.php?view=analytics&amp;range=<?= $rangeValue ?>"><?= e($rangeLabel) ?></a><?php endforeach; ?></div></div>
<script type="application/json" id="analytics-data"><?= json_encode($analytics, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<div class="analytics-kpis"><article><span>Rata-rata penyelesaian</span><strong><?= e($analytics["kpi"]["avg_resolution"]) ?> <small>jam</small></strong><small><?= $analytics["kpi"]["total_resolved"] ?> ticket terselesaikan</small></article><article><span>Kepatuhan SLA</span><strong><?= $analytics["kpi"]["sla_compliance"] ?>%</strong><small>Selesai sebelum deadline</small></article><article><span>Mendekati deadline</span><strong><?= $analytics["kpi"]["sla_at_risk"] ?></strong><small>Ticket aktif perlu dikebut</small></article><article><span>SLA terlampaui</span><strong><?= $analytics["kpi"]["sla_overdue"] ?></strong><small>Ticket aktif sudah lewat batas</small></article></div>
<div class="chart-grid">
<article class="analytics-card chart-card chart-wide"><header><div><h3>Tren volume ticket</h3><p>Ticket masuk vs terselesaikan (<?= $analytics["range_days"] ?> hari terakhir)</p></div></header><div class="chart-canvas tall"><canvas id="chart-volume" aria-label="Grafik tren volume ticket" role="img"></canvas></div></article>
<article class="analytics-card chart-card"><header><div><h3>Rata-rata waktu penyelesaian</h3><p>Dalam jam, per kategori</p></div></header><div class="chart-canvas"><canvas id="chart-resolution" aria-label="Grafik rata-rata waktu penyelesaian per kategori" role="img"></canvas></div></article>
<article class="analytics-card chart-card"><header><div><h3>Distribusi status</h3><p>Komposisi seluruh ticket</p></div><span><?= count($tickets) ?> total</span></header><div class="chart-canvas"><canvas id="chart-status" aria-label="Grafik distribusi status ticket" role="img"></canvas></div></article>
<article class="analytics-card chart-card"><header><div><h3>Sebaran prioritas</h3><p>Tingkat urgensi ticket</p></div></header><div class="chart-canvas"><canvas id="chart-priority" aria-label="Grafik sebaran prioritas ticket" role="img"></canvas></div></article>
<article class="analytics-card chart-card"><header><div><h3>Performa teknisi</h3><p>Ticket selesai vs masih aktif</p></div><span>Bukan skor performa</span></header><div class="chart-canvas"><?php if ($analytics["tech"]["labels"]): ?><canvas id="chart-tech" aria-label="Grafik performa teknisi" role="img"></canvas><?php else: ?><div class="empty-mini">Belum ada teknisi terdaftar.</div><?php endif; ?></div></article>
</div>
<script src="assets/vendor/chart.umd.min.js"></script>
<script src="assets/analytics.js?v=20260902-1"></script>
<?php else: ?>
<div class="page-title"><div><span class="eyebrow dark"><?= date(
    "l, d F Y",
) ?></span><h1><?= $view === "tickets"
    ? "Semua ticket"
    : "Selamat datang, " .
        e(explode(" ", $user["name"])[0]) .
        " 👋" ?></h1><p><?= $view === "tickets"
    ? "Kelola dan pantau seluruh permintaan bantuan IT."
    : "Berikut ringkasan layanan IT kamu hari ini." ?></p></div></div>
<?php if (
    $view === "dashboard" &&
    $user["role"] === "admin"
): ?><section class="admin-analytics"><div class="analytics-heading"><div><span class="eyebrow dark">CONTROL CENTER</span><h2>Pemantauan layanan IT</h2><p>Distribusi ticket, beban teknisi, dan pola masalah secara real-time.</p></div><span class="live-indicator"><i></i> Live monitoring</span></div><div class="analytics-kpis"><article><span>Total ticket</span><strong><?= count(
    $tickets,
) ?></strong><small><?= $counts[
    "new"
] ?> perlu ditinjau</small></article><article><span>Ticket aktif</span><strong><?= $counts[
     "assigned"
 ] + $counts["in_progress"] ?></strong><small><?= $counts[
    "in_progress"
] ?> sedang dikerjakan</small></article><article><span>Penyelesaian</span><strong><?= count(
     $tickets,
 )
     ? round(
         (($counts["completed"] + $counts["closed"]) / count($tickets)) * 100,
     )
     : 0 ?>%</strong><small><?= $counts["completed"] +
    $counts[
        "closed"
    ] ?> ticket terselesaikan</small></article><article><span>Prioritas tinggi</span><strong><?= $priorityCounts[
     "high"
 ] +
     $priorityCounts[
         "urgent"
     ] ?></strong><small>Perlu perhatian lebih</small></article></div><div class="analytics-grid"><article class="analytics-card status-chart"><header><div><h3>Distribusi status</h3><p>Komposisi seluruh ticket</p></div><span><?= count(
    $tickets,
) ?> total</span></header><div class="donut-wrap"><div class="donut" style="background:conic-gradient(<?= $statusGradient ?>)"><div><strong><?= count(
    $tickets,
) ?></strong><span>Ticket</span></div></div><div class="chart-legend"><?php foreach (
    STATUSES
    as $key => $label
): ?><div><i style="background:<?= $statusColors[$key] ?>"></i><span><?= e(
    $label,
) ?></span><b><?= $counts[
    $key
] ?></b></div><?php endforeach; ?></div></div></article><article class="analytics-card category-chart"><header><div><h3>Klasifikasi masalah</h3><p>Ticket berdasarkan kategori</p></div></header><div class="bar-list"><?php foreach (
    $categoryCounts
    as $category => $amount
): ?><div><label><span><?= e(
    $category,
) ?></span><b><?= $amount ?></b></label><i><b style="width:<?= round(
    ($amount / $maxCategory) * 100,
) ?>%"></b></i></div><?php endforeach; ?></div></article><article class="analytics-card tech-board"><header><div><h3>Beban kerja teknisi</h3><p>Distribusi ticket yang ditangani</p></div><span>Bukan skor performa</span></header><div class="tech-list"><?php foreach (
    $techStats
    as $index => $stat
): ?><div class="tech-row"><em><?= $index + 1 ?></em><div class="avatar"><?= e(
    strtoupper(substr($stat["user"]["name"], 0, 1)),
) ?></div><div class="tech-info"><strong><?= e(
    $stat["user"]["name"],
) ?></strong><span><?= $stat["active"] ?> aktif · <?= $stat[
     "done"
 ] ?> selesai</span><i><b style="width:<?= round(
     ($stat["total"] / $maxTech) * 100,
 ) ?>%"></b></i></div><b><?= $stat[
    "total"
] ?></b></div><?php endforeach; ?></div></article><article class="analytics-card activity-feed"><header><div><h3>Aktivitas terbaru</h3><p>Perubahan terkini oleh tim</p></div></header><div><?php foreach (
    $recentActivities
    as $activity
):
    $actor = userById(
        $data,
        $activity["user_id"],
    ); ?><a href="index.php?ticket=<?= $activity[
    "ticket_id"
] ?>"><i></i><span><strong><?= e($actor["name"] ?? "-") ?></strong> <?= e(
    strtolower($activity["message"]),
) ?><small>Ticket #<?= $activity["ticket_id"] ?> · <?= e(
     relativeDate($activity["created_at"]),
 ) ?></small></span></a><?php
endforeach; ?></div></article></div></section><?php endif; ?>
<?php if (
    $view === "tickets" &&
    $user["role"] === "it"
): ?><div class="ticket-tabs"><a class="<?= !$mine
    ? "active"
    : "" ?>" href="index.php?view=tickets">Semua Ticket</a><a class="<?= $mine
    ? "active"
    : "" ?>" href="index.php?view=tickets&amp;mine=1">Ticket Saya</a></div><?php endif; ?>
<?php if (
    $view === "dashboard"
): ?><div class="stats-grid"><div class="stat-card blue"><div><span>Ticket Aktif</span><strong><?= $counts[
    "new"
] +
    $counts["assigned"] +
    $counts[
        "in_progress"
    ] ?></strong><small>Belum terselesaikan</small></div><i>↗</i></div><div class="stat-card amber"><div><span>Sedang Dikerjakan</span><strong><?= $counts[
    "in_progress"
] ?></strong><small>Ditangani tim IT</small></div><i>⚙</i></div><div class="stat-card green"><div><span>Ticket Selesai</span><strong><?= $counts[
    "completed"
] +
    $counts[
        "closed"
    ] ?></strong><small>Total terselesaikan</small></div><i>✓</i></div></div><div class="quick-banner"><div><i>⚡</i><div><strong>Ada kendala IT?</strong><p>Buat laporan baru dan tim IT akan segera menanganinya.</p></div></div><a class="btn light" href="index.php?view=new">Buat Ticket Sekarang →</a></div><?php endif; ?>
<section class="table-card"><div class="table-head"><div><h2><?= $view ===
"tickets"
    ? "Daftar Ticket"
    : "Ticket Terbaru" ?></h2><p><?= count(
    $filtered,
) ?> ticket ditemukan</p></div><form class="filters" method="get"><input type="hidden" name="view" value="tickets"><div class="search">⌕ <input name="q" value="<?= e(
     $search,
 ) ?>" placeholder="Cari ticket..." aria-label="Cari ticket berdasarkan judul, nomor, deskripsi, atau pelapor"></div><select name="status" aria-label="Saring berdasarkan status" onchange="this.form.submit()"><option value="">Semua status</option><?php foreach (
    STATUSES
    as $k => $v
): ?><option value="<?= $k ?>" <?= $statusFilter === $k
    ? "selected"
    : "" ?>><?= e(
    $v,
) ?></option><?php endforeach; ?></select><select name="priority" aria-label="Saring berdasarkan prioritas" onchange="this.form.submit()"><option value="">Semua prioritas</option><?php foreach (PRIORITIES as $k => $v): ?><option value="<?= $k ?>" <?= $priorityFilter === $k ? "selected" : "" ?>><?= e($v) ?></option><?php endforeach; ?></select><select name="category" aria-label="Saring berdasarkan kategori" onchange="this.form.submit()"><option value="">Semua kategori</option><?php foreach (CATEGORIES as $cat): ?><option value="<?= e($cat) ?>" <?= $categoryFilter === $cat ? "selected" : "" ?>><?= e($cat) ?></option><?php endforeach; ?></select><input type="date" name="date_from" value="<?= e($dateFrom) ?>" title="Dari tanggal" onchange="this.form.submit()"><input type="date" name="date_to" value="<?= e($dateTo) ?>" title="Sampai tanggal" onchange="this.form.submit()"><?php if ($search || $statusFilter || $priorityFilter || $categoryFilter || $dateFrom || $dateTo): ?><a class="btn-reset-filter" href="index.php?view=tickets<?= $mine ? '&mine=1' : '' ?>">✕ Reset</a><?php endif; ?><?php if (in_array($user["role"], ["it","admin"], true)): ?><a class="btn light compact" href="<?= e('index.php?' . http_build_query(array_filter(['view'=>'tickets','export'=>'csv','q'=>$search,'status'=>$statusFilter,'priority'=>$priorityFilter,'category'=>$categoryFilter,'date_from'=>$dateFrom,'date_to'=>$dateTo]))) ?>" title="Unduh CSV">⬇ CSV</a><?php endif; ?></form></div><div class="table-wrap"><table><thead><tr><th>TICKET</th><th>KATEGORI</th><th>PRIORITAS</th><th>STATUS</th><th>SLA</th><th>DITANGANI</th><th>UPDATE</th><th></th></tr></thead><tbody><?php
foreach (array_slice($filtered, 0, $view === "dashboard" ? 5 : null) as $t):
    $assignee = userById(
        $data,
        $t["assignee_id"],
    );
    $sla = slaStatus($t); ?><tr><td><a href="index.php?ticket=<?= $t["id"] ?>"><strong><?= e(
    $t["subject"],
) ?></strong><span>#<?= e($t["id"]) ?></span></a></td><td><?= e(
    $t["category"],
) ?></td><td><span class="priority-dot <?= e($t["priority"]) ?>"></span><?= e(
    PRIORITIES[$t["priority"]],
) ?></td><td><span class="badge status-<?= e($t["status"]) ?>"><?= e(
    STATUSES[$t["status"]],
) ?></span></td><td><?php if ($sla): ?><span class="sla-badge sla-<?= e($sla["status"]) ?>" data-sla-deadline="<?= e($t["sla_deadline"]) ?>" data-sla-priority="<?= e($t["priority"]) ?>"><i></i><?= e($sla["label"]) ?></span><?php else: ?><span class="sla-badge sla-none">—</span><?php endif; ?></td><td><?= e($assignee["name"] ?? "—") ?></td><td><?= e(
    relativeDate($t["updated_at"]),
) ?></td><td><a class="row-arrow" href="index.php?ticket=<?= $t[
    "id"
] ?>">→</a></td></tr><?php
endforeach;
if (
    !$filtered
): ?><tr><td colspan="8"><div class="empty-mini">Belum ada ticket yang cocok.</div></td></tr><?php endif;
?></tbody></table></div><?php if (
    $view === "dashboard"
): ?><a class="view-all" href="index.php?view=tickets">Lihat semua ticket →</a><?php endif; ?></section>
<?php endif; ?></div></main></div><script src="assets/app.js?v=20260902-1"></script></body></html>
