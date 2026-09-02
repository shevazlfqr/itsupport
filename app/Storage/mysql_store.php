<?php
declare(strict_types=1);

function db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) return $connection;
    $dsn = sprintf("mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4", DB_HOST, DB_PORT, DB_NAME);
    $connection = new PDO($dsn, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $connection;
}

function normalizeData(array &$data): void
{
    foreach (["users", "tickets", "comments", "activities", "attachments", "reads"] as $key) {
        $data[$key] = $data[$key] ?? [];
    }
    foreach ($data["users"] as &$user) {
        $user["job_title"] = $user["job_title"] ?? ($user["role"] === "it" ? "IT Support" : "Karyawan");
        $user["department"] = $user["department"] ?? "-";
        $user["active"] = (bool) ($user["active"] ?? true);
    }
    unset($user);
    $legacyStatuses = ["open" => "new", "waiting" => "assigned", "resolved" => "completed"];
    foreach ($data["tickets"] as &$ticket) {
        if (isset($legacyStatuses[$ticket["status"]])) $ticket["status"] = $legacyStatuses[$ticket["status"]];
        $ticket["resolution_note"] = $ticket["resolution_note"] ?? "";
        // Backfill SLA deadline for tickets created before SLA tracking existed
        if (empty($ticket["sla_deadline"])) {
            $ticket["sla_deadline"] = calcSlaDeadline($ticket["created_at"], $ticket["priority"]);
        }
    }
    unset($ticket);
}

function readData(): array
{
    $pdo = db();
    $data = [
        "users" => $pdo->query("SELECT id, name, email, password, role, department, job_title, active FROM users ORDER BY id")->fetchAll(),
        "tickets" => $pdo->query("SELECT id, subject, description, category, priority, status, requester_id, assignee_id, resolution_note, created_at, updated_at, sla_deadline FROM tickets ORDER BY id")->fetchAll(),
        "comments" => $pdo->query("SELECT id, ticket_id, user_id, message, created_at FROM comments ORDER BY id")->fetchAll(),
        "activities" => $pdo->query("SELECT id, ticket_id, user_id, message, created_at FROM activities ORDER BY id")->fetchAll(),
        "attachments" => $pdo->query("SELECT id, ticket_id, user_id, stored_name, original_name, mime, size, created_at FROM attachments ORDER BY id")->fetchAll(),
        "reads" => $pdo->query("SELECT ticket_id, user_id, last_read_at FROM ticket_reads ORDER BY ticket_id, user_id")->fetchAll(),
    ];
    foreach ($data["users"] as &$user) {
        $user["id"] = (int) $user["id"];
        $user["active"] = (bool) $user["active"];
    }
    unset($user);
    foreach ($data["tickets"] as &$ticket) {
        $ticket["id"] = (int) $ticket["id"];
        $ticket["requester_id"] = (int) $ticket["requester_id"];
        $ticket["assignee_id"] = $ticket["assignee_id"] === null ? null : (int) $ticket["assignee_id"];
    }
    unset($ticket);
    foreach (["comments", "activities", "attachments"] as $collection) {
        foreach ($data[$collection] as &$item) {
            $item["id"] = (int) $item["id"];
            $item["ticket_id"] = (int) $item["ticket_id"];
            $item["user_id"] = (int) $item["user_id"];
            if (isset($item["size"])) $item["size"] = (int) $item["size"];
        }
        unset($item);
    }
    foreach ($data["reads"] as &$read) {
        $read["ticket_id"] = (int) $read["ticket_id"];
        $read["user_id"] = (int) $read["user_id"];
    }
    unset($read);
    normalizeData($data);
    return $data;
}

function mysqlDate(string $value): string
{
    return date("Y-m-d H:i:s", strtotime($value));
}

function persistData(PDO $pdo, array $data): void
{
    normalizeData($data);
    foreach (["ticket_reads", "attachments", "comments", "activities", "tickets", "users"] as $table) {
        $pdo->exec("DELETE FROM " . $table);
    }

    $statement = $pdo->prepare("INSERT INTO users (id, name, email, password, role, department, job_title, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($data["users"] as $user) {
        $statement->execute([$user["id"], $user["name"], $user["email"], $user["password"], $user["role"], $user["department"], $user["job_title"], $user["active"] ? 1 : 0]);
    }

    $statement = $pdo->prepare("INSERT INTO tickets (id, subject, description, category, priority, status, requester_id, assignee_id, resolution_note, created_at, updated_at, sla_deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($data["tickets"] as $ticket) {
        $statement->execute([$ticket["id"], $ticket["subject"], $ticket["description"], $ticket["category"], $ticket["priority"], $ticket["status"], $ticket["requester_id"], $ticket["assignee_id"], $ticket["resolution_note"] ?? "", mysqlDate($ticket["created_at"]), mysqlDate($ticket["updated_at"]), isset($ticket["sla_deadline"]) ? mysqlDate($ticket["sla_deadline"]) : null]);
    }

    $statement = $pdo->prepare("INSERT INTO comments (id, ticket_id, user_id, message, created_at) VALUES (?, ?, ?, ?, ?)");
    foreach ($data["comments"] as $comment) $statement->execute([$comment["id"], $comment["ticket_id"], $comment["user_id"], $comment["message"], mysqlDate($comment["created_at"])]);

    $statement = $pdo->prepare("INSERT INTO activities (id, ticket_id, user_id, message, created_at) VALUES (?, ?, ?, ?, ?)");
    foreach ($data["activities"] as $activity) $statement->execute([$activity["id"], $activity["ticket_id"], $activity["user_id"], $activity["message"], mysqlDate($activity["created_at"])]);

    $statement = $pdo->prepare("INSERT INTO attachments (id, ticket_id, user_id, stored_name, original_name, mime, size, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($data["attachments"] as $attachment) $statement->execute([$attachment["id"], $attachment["ticket_id"], $attachment["user_id"], $attachment["stored_name"], $attachment["original_name"], $attachment["mime"], $attachment["size"], mysqlDate($attachment["created_at"])]);

    $statement = $pdo->prepare("INSERT INTO ticket_reads (ticket_id, user_id, last_read_at) VALUES (?, ?, ?)");
    foreach ($data["reads"] as $read) $statement->execute([$read["ticket_id"], $read["user_id"], mysqlDate($read["last_read_at"])]);
}

function updateData(callable $callback)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $data = readData();
        $result = $callback($data);
        persistData($pdo, $data);
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
