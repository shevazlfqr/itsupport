<?php
declare(strict_types=1);

if (($_GET["api"] ?? "") !== "updates") {
    return;
}
header("Content-Type: application/json; charset=utf-8");
if (!$user) {
    http_response_code(401);
    echo json_encode(["error" => "unauthorized"]);
    exit();
}

$since = strtotime($_GET["since"] ?? "") ?: time();
$visibleIds = [];
foreach ($data["tickets"] as $ticket) {
    if (canView($ticket, $user)) {
        $visibleIds[] = $ticket["id"];
    }
}
$events = [];
foreach ($data["comments"] as $comment) {
    if (
        in_array($comment["ticket_id"], $visibleIds, true) &&
        $comment["user_id"] !== $user["id"] &&
        strtotime($comment["created_at"]) > $since
    ) {
        $events[] = [
            "ticket_id" => $comment["ticket_id"],
            "type" => "comment",
            "message" => "Komentar baru",
            "created_at" => $comment["created_at"],
        ];
    }
}
foreach ($data["activities"] as $activity) {
    if (
        in_array($activity["ticket_id"], $visibleIds, true) &&
        $activity["user_id"] !== $user["id"] &&
        $activity["message"] !== "Menambahkan komentar" &&
        strtotime($activity["created_at"]) > $since
    ) {
        $events[] = [
            "ticket_id" => $activity["ticket_id"],
            "type" => "activity",
            "message" => $activity["message"],
            "created_at" => $activity["created_at"],
        ];
    }
}
usort(
    $events,
    fn($left, $right) => strtotime($left["created_at"]) <=>
        strtotime($right["created_at"]),
);
echo json_encode(
    [
        "changed" => !empty($events),
        "events" => $events,
        "server_time" => date(DATE_ATOM),
    ],
    JSON_UNESCAPED_UNICODE,
);
exit();
