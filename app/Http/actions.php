<?php
declare(strict_types=1);

$action = $_POST["action"] ?? "";
if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
    return;
}

verifyCsrf();
if ($action === "login") {
    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";

    // Rate limiting check
    $limiter = rateLimiter();
    $key = 'login:' . $email . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    if ($limiter->tooManyAttempts($key)) {
        $seconds = $limiter->availableIn($key);
        $minutes = ceil($seconds / 60);
        flash("error", "Terlalu banyak percobaan login. Coba lagi dalam {$minutes} menit.");
        redirect();
    }

    $data = readData();
    $match = null;
    foreach ($data["users"] as $candidate) {
        if (
            strtolower($candidate["email"]) === $email &&
            ($candidate["active"] ?? true) &&
            password_verify($password, $candidate["password"])
        ) {
            $match = $candidate;
        }
    }
    if (!$match) {
        // Increment failed login attempts
        $limiter->hit($key);
        $remaining = $limiter->remainingAttempts($key);

        if ($remaining > 0) {
            flash("error", "Email atau password salah. {$remaining} percobaan tersisa.");
        } else {
            flash("error", "Email atau password salah. Akun diblokir sementara.");
        }
        redirect();
    }

    // Clear rate limit on successful login
    $limiter->clear($key);

    session_regenerate_id(true);
    $_SESSION["user_id"] = $match["id"];
    flash("success", "Selamat datang, " . $match["name"] . ".");
    redirect();
}
if ($action === "logout") {
    session_destroy();
    redirect();
}

$data = readData();
$user = currentUser($data);
if (!$user) {
    flash("error", "Silakan login terlebih dahulu.");
    redirect();
}

if (in_array($action, ["create_user", "update_user", "toggle_user"], true)) {
    if ($user["role"] !== "admin") {
        http_response_code(403);
        exit("Akses khusus Admin.");
    }

    $targetId = (int) ($_POST["user_id"] ?? 0);
    $target = $targetId ? userById($data, $targetId) : null;
    if ($action !== "create_user" && !$target) {
        flash("error", "Data pengguna tidak ditemukan.");
        redirect("index.php?view=users");
    }

    if ($action === "toggle_user") {
        if ($targetId === $user["id"]) {
            flash("error", "Admin tidak dapat menonaktifkan akunnya sendiri.");
            redirect("index.php?view=users");
        }
        updateData(function (&$records) use ($targetId) {
            foreach ($records["users"] as &$account) {
                if ($account["id"] === $targetId) {
                    $account["active"] = !($account["active"] ?? true);
                }
            }
        });
        flash("success", ($target["active"] ?? true) ? "Akun berhasil dinonaktifkan." : "Akun berhasil diaktifkan.");
        redirect("index.php?view=users");
    }

    $name = trim($_POST["name"] ?? "");
    $email = strtolower(trim($_POST["email"] ?? ""));
    $role = $_POST["role"] ?? "employee";
    $jobTitle = trim($_POST["job_title"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $password = $_POST["password"] ?? "";
    $validRoles = ["employee", "it", "admin"];

    if (mb_strlen($name) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, $validRoles, true) || mb_strlen($jobTitle) < 2 || mb_strlen($department) < 2) {
        flash("error", "Nama, email, role, jabatan, dan divisi wajib diisi dengan benar.");
        redirect("index.php?view=users");
    }
    foreach ($data["users"] as $account) {
        if (strtolower($account["email"]) === $email && $account["id"] !== $targetId) {
            flash("error", "Email tersebut sudah digunakan akun lain.");
            redirect("index.php?view=users");
        }
    }
    if (($action === "create_user" || $password !== "") && mb_strlen($password) < 8) {
        $passwordErrors = validateStrongPassword($password);
        if (!empty($passwordErrors)) {
            flash("error", "Password tidak memenuhi syarat: " . implode(", ", $passwordErrors) . ".");
            redirect("index.php?view=users");
        }
    }
    if ($targetId === $user["id"] && $role !== "admin") {
        flash("error", "Admin tidak dapat menurunkan role akunnya sendiri.");
        redirect("index.php?view=users");
    }

    updateData(function (&$records) use ($action, $targetId, $name, $email, $role, $jobTitle, $department, $password) {
        $payload = ["name" => $name, "email" => $email, "role" => $role, "job_title" => $jobTitle, "department" => $department];
        if ($action === "create_user") {
            $records["users"][] = $payload + ["id" => nextId($records["users"]), "password" => password_hash($password, PASSWORD_DEFAULT), "active" => true];
            return;
        }
        foreach ($records["users"] as &$account) {
            if ($account["id"] === $targetId) {
                $account = array_merge($account, $payload);
                if ($password !== "") $account["password"] = password_hash($password, PASSWORD_DEFAULT);
            }
        }
    });
    $successMessage = $action === "create_user" ? "Akun karyawan berhasil ditambahkan." : "Data pengguna berhasil diperbarui.";
    if (($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "") === "user-modal") {
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["success" => true, "message" => $successMessage], JSON_UNESCAPED_UNICODE);
        exit;
    }
    flash("success", $successMessage);
    redirect("index.php?view=users");
}

if ($action === "create_ticket") {
    $subject = trim($_POST["subject"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $category = $_POST["category"] ?? "";
    $priority = $_POST["priority"] ?? "medium";
    if (
        mb_strlen($subject) < 5 ||
        mb_strlen($description) < 10 ||
        !in_array($category, CATEGORIES, true) ||
        !isset(PRIORITIES[$priority])
    ) {
        flash(
            "error",
            "Lengkapi ticket dengan judul dan deskripsi yang jelas.",
        );
        redirect("index.php?view=new");
    }
    try {
        $attachments = saveScreenshots($_FILES["screenshots"] ?? []);
    } catch (RuntimeException $error) {
        flash("error", $error->getMessage());
        redirect("index.php?view=new");
    }

    $id = updateData(function (&$data) use (
        $user,
        $subject,
        $description,
        $category,
        $priority,
        $attachments
    ) {
        $id = nextId($data["tickets"], 1001);
        $now = date(DATE_ATOM);
        $data["tickets"][] = [
            "id" => $id,
            "subject" => $subject,
            "description" => $description,
            "category" => $category,
            "priority" => $priority,
            "status" => "new",
            "requester_id" => $user["id"],
            "assignee_id" => null,
            "created_at" => $now,
            "updated_at" => $now,
            "sla_deadline" => calcSlaDeadline($now, $priority),
        ];
        foreach ($attachments as $attachment) {
            $data["attachments"][] = [
                "id" => nextId($data["attachments"]),
                "ticket_id" => $id,
                "user_id" => $user["id"],
                "stored_name" => $attachment["stored_name"],
                "original_name" => $attachment["original_name"],
                "mime" => $attachment["mime"],
                "size" => $attachment["size"],
                "created_at" => $now,
            ];
        }
        $data["activities"][] = [
            "id" => nextId($data["activities"]),
            "ticket_id" => $id,
            "user_id" => $user["id"],
            "message" => "Ticket dibuat",
            "created_at" => $now,
        ];
        return $id;
    });
    flash("success", "Ticket #" . $id . " berhasil dibuat.");
    redirect("index.php?ticket=" . $id);
}

if (
    !in_array(
        $action,
        ["update_ticket", "claim_ticket", "requester_resolution", "comment"],
        true,
    )
) {
    return;
}
$ticketId = (int) ($_POST["ticket_id"] ?? 0);
$ticket = null;
foreach ($data["tickets"] as $candidate) {
    if ($candidate["id"] === $ticketId) {
        $ticket = $candidate;
    }
}
if (!$ticket || !canView($ticket, $user)) {
    http_response_code(403);
    exit("Akses ditolak.");
}

if ($action === "comment") {
    if (in_array($ticket["status"], ["closed", "cancelled"], true)) {
        flash(
            "error",
            "Ticket final bersifat read-only dan tidak menerima komentar baru.",
        );
        redirect("index.php?ticket=" . $ticketId);
    }
    $message = trim($_POST["message"] ?? "");
    if (mb_strlen($message) < 2) {
        flash("error", "Komentar tidak boleh kosong.");
        redirect("index.php?ticket=" . $ticketId);
    }
    updateData(function (&$data) use ($user, $ticketId, $message) {
        $now = date(DATE_ATOM);
        $data["comments"][] = [
            "id" => nextId($data["comments"]),
            "ticket_id" => $ticketId,
            "user_id" => $user["id"],
            "message" => $message,
            "created_at" => $now,
        ];
        $found = false;
        foreach ($data["reads"] as &$read) {
            if (
                $read["ticket_id"] === $ticketId &&
                $read["user_id"] === $user["id"]
            ) {
                $read["last_read_at"] = $now;
                $found = true;
            }
        }
        if (!$found) {
            $data["reads"][] = [
                "ticket_id" => $ticketId,
                "user_id" => $user["id"],
                "last_read_at" => $now,
            ];
        }
        foreach ($data["tickets"] as &$item) {
            if ($item["id"] === $ticketId) {
                $item["updated_at"] = $now;
            }
        }
        $data["activities"][] = [
            "id" => nextId($data["activities"]),
            "ticket_id" => $ticketId,
            "user_id" => $user["id"],
            "message" => "Menambahkan komentar",
            "created_at" => $now,
        ];
    });
    flash("success", "Komentar terkirim.");
    redirect("index.php?ticket=" . $ticketId);
}

if ($action === "requester_resolution") {
    $decision = $_POST["decision"] ?? "";
    if (
        $user["id"] !== $ticket["requester_id"] ||
        $ticket["status"] !== "completed" ||
        !in_array($decision, ["close", "reopen"], true)
    ) {
        http_response_code(403);
        exit("Keputusan ini tidak diizinkan.");
    }
    $nextStatus = $decision === "close" ? "closed" : "in_progress";
    $message =
        $decision === "close"
            ? "Requester mengonfirmasi masalah selesai dan menutup ticket"
            : "Requester melaporkan masalah belum selesai dan membuka kembali ticket";
    updateData(function (&$data) use ($user, $ticketId, $nextStatus, $message) {
        $now = date(DATE_ATOM);
        foreach ($data["tickets"] as &$item) {
            if ($item["id"] === $ticketId) {
                $item["status"] = $nextStatus;
                if ($nextStatus === "in_progress") {
                    $item["resolution_note"] = "";
                }
                $item["updated_at"] = $now;
            }
        }
        $data["activities"][] = [
            "id" => nextId($data["activities"]),
            "ticket_id" => $ticketId,
            "user_id" => $user["id"],
            "message" => $message,
            "created_at" => $now,
        ];
    });
    flash(
        "success",
        $decision === "close"
            ? "Ticket sudah ditutup. Terima kasih atas konfirmasinya."
            : "Ticket dibuka kembali dan dikirim ke teknisi.",
    );
    redirect("index.php?ticket=" . $ticketId);
}

if ($action === "claim_ticket") {
    if ($user["role"] !== "it" || $ticket["assignee_id"]) {
        http_response_code(403);
        exit("Ticket ini tidak dapat diambil.");
    }
    updateData(function (&$data) use ($user, $ticketId) {
        $now = date(DATE_ATOM);
        foreach ($data["tickets"] as &$item) {
            if ($item["id"] === $ticketId) {
                $item["assignee_id"] = $user["id"];
                $item["status"] = "assigned";
                $item["updated_at"] = $now;
            }
        }
        $data["activities"][] = [
            "id" => nextId($data["activities"]),
            "ticket_id" => $ticketId,
            "user_id" => $user["id"],
            "message" => "Mengambil ticket dan menjadi teknisi yang ditugaskan",
            "created_at" => $now,
        ];
    });
    flash("success", "Ticket berhasil kamu ambil.");
    redirect("index.php?ticket=" . $ticketId);
}

$mayUpdate =
    in_array($ticket["status"], ["new", "assigned", "in_progress"], true) &&
    ($user["role"] === "admin" ||
        ($user["role"] === "it" && $ticket["assignee_id"] === $user["id"]));
if (!$mayUpdate) {
    http_response_code(403);
    exit("Ambil ticket terlebih dahulu atau hubungi Admin.");
}
$status = $_POST["status"] ?? "";
$resolutionNote = trim(
    $_POST["resolution_note"] ?? ($ticket["resolution_note"] ?? ""),
);
$assigneeId =
    $user["role"] === "admin"
        ? ((int) ($_POST["assignee_id"] ?? 0) ?:
        null)
        : $ticket["assignee_id"];
if ($assigneeId && $status === "new") {
    $status = "assigned";
}
if (!$assigneeId && $status === "assigned") {
    $status = "new";
}
if (!isset(STATUSES[$status])) {
    flash("error", "Status tidak valid.");
    redirect("index.php?ticket=" . $ticketId);
}
if (!in_array($status, allowedStatusTransitions($ticket["status"]), true)) {
    flash(
        "error",
        "Perubahan status dari " .
            STATUSES[$ticket["status"]] .
            " ke " .
            STATUSES[$status] .
            " tidak diizinkan.",
    );
    redirect("index.php?ticket=" . $ticketId);
}
if (
    in_array($status, ["completed", "closed"], true) &&
    mb_strlen($resolutionNote) < 5
) {
    flash(
        "error",
        "Catatan penyelesaian wajib diisi saat ticket diselesaikan.",
    );
    redirect("index.php?ticket=" . $ticketId);
}
if (!in_array($status, ["completed", "closed"], true)) {
    $resolutionNote = "";
}
updateData(function (&$data) use (
    $user,
    $ticketId,
    $status,
    $assigneeId,
    $ticket,
    $resolutionNote
) {
    $now = date(DATE_ATOM);
    foreach ($data["tickets"] as &$item) {
        if ($item["id"] === $ticketId) {
            $item["status"] = $status;
            $item["assignee_id"] = $assigneeId;
            $item["resolution_note"] = $resolutionNote;
            $item["updated_at"] = $now;
        }
    }
    if ($ticket["status"] !== $status) {
        $data["activities"][] = [
            "id" => nextId($data["activities"]),
            "ticket_id" => $ticketId,
            "user_id" => $user["id"],
            "message" => "Status diubah menjadi " . STATUSES[$status],
            "created_at" => $now,
        ];
    }
    if ($ticket["assignee_id"] !== $assigneeId) {
        $assignee = userById($data, $assigneeId);
        $data["activities"][] = [
            "id" => nextId($data["activities"]),
            "ticket_id" => $ticketId,
            "user_id" => $user["id"],
            "message" => $assignee
                ? "Ditugaskan kepada " . $assignee["name"]
                : "Assignment dilepas",
            "created_at" => $now,
        ];
    }
});
flash("success", "Ticket berhasil diperbarui.");
redirect("index.php?ticket=" . $ticketId);
