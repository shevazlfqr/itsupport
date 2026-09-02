<?php
declare(strict_types=1);

function exportTicketsCsv(array $data, array $user): void
{
    // Build user lookup
    $userMap = [];
    foreach ($data["users"] as $u) {
        $userMap[$u["id"]] = $u;
    }

    // Filter tickets user can view
    $tickets = array_filter($data["tickets"], fn($t) => canView($t, $user));
    usort($tickets, fn($a, $b) => strtotime($b["updated_at"]) <=> strtotime($a["updated_at"]));

    // Apply same filters as the list view
    $statusFilter   = $_GET["status"]   ?? "";
    $priorityFilter = $_GET["priority"] ?? "";
    $categoryFilter = $_GET["category"] ?? "";
    $search         = trim($_GET["q"]   ?? "");
    $dateFrom       = $_GET["date_from"] ?? "";
    $dateTo         = $_GET["date_to"]   ?? "";

    $tickets = array_filter($tickets, function ($t) use (
        $statusFilter, $priorityFilter, $categoryFilter, $search, $dateFrom, $dateTo, $userMap
    ) {
        if ($statusFilter   && $t["status"]   !== $statusFilter)   return false;
        if ($priorityFilter && $t["priority"] !== $priorityFilter) return false;
        if ($categoryFilter && $t["category"] !== $categoryFilter) return false;
        if ($search) {
            $requesterName = $userMap[$t["requester_id"]]["name"] ?? "";
            $haystack = $t["subject"] . " " . $t["id"] . " " . $t["description"] . " " . $requesterName;
            if (stripos($haystack, $search) === false) return false;
        }
        if ($dateFrom && strtotime($t["created_at"]) < strtotime($dateFrom)) return false;
        if ($dateTo   && strtotime($t["created_at"]) > strtotime($dateTo . " 23:59:59")) return false;
        return true;
    });

    // Set CSV headers
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"tickets-" . date("Y-m-d") . ".csv\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    $out = fopen("php://output", "w");

    // BOM for Excel UTF-8 compatibility
    fputs($out, "\xEF\xBB\xBF");

    // Header row
    fputcsv($out, [
        "ID Ticket",
        "Subjek",
        "Kategori",
        "Prioritas",
        "Status",
        "Pemohon",
        "Departemen",
        "Ditangani Oleh",
        "Tanggal Dibuat",
        "Tanggal Update",
        "Catatan Resolusi",
    ]);

    $statusLabels   = STATUSES;
    $priorityLabels = PRIORITIES;

    foreach ($tickets as $ticket) {
        $requester = $userMap[$ticket["requester_id"]] ?? null;
        $assignee  = isset($ticket["assignee_id"]) ? ($userMap[$ticket["assignee_id"]] ?? null) : null;

        fputcsv($out, [
            "#" . $ticket["id"],
            $ticket["subject"],
            $ticket["category"],
            $priorityLabels[$ticket["priority"]] ?? $ticket["priority"],
            $statusLabels[$ticket["status"]]   ?? $ticket["status"],
            $requester["name"]  ?? "-",
            $requester["department"] ?? "-",
            $assignee["name"]   ?? "-",
            date("d/m/Y H:i", strtotime($ticket["created_at"])),
            date("d/m/Y H:i", strtotime($ticket["updated_at"])),
            $ticket["resolution_note"] ?? "",
        ]);
    }

    fclose($out);
    exit();
}
