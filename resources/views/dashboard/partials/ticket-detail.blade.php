<?php

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
<a class="back-link" href="index.php?view=tickets"><span class="back-link-icon" aria-hidden="true"><svg viewBox="0 0 16 16" focusable="false"><path d="M10.5 3.5 6 8l4.5 4.5M6.5 8H14" /></svg></span><span>Kembali ke daftar ticket</span></a><div class="ticket-head"><div><div class="ticket-meta"><span>#<?= e(
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
    ); ?><a href="{{ asset('storage/uploads/' . $attachment['stored_name']) }}" target="_blank"><?php if (
    $isHeic
): ?><span class="file-placeholder">HEIC</span><?php else: ?><img src="{{ asset('storage/uploads/' . $attachment['stored_name']) }}" alt="{{ e($attachment['original_name']) }}"><?php endif; ?><span><?= e(
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
): ?><span class="sla-badge sla-done"><i></i>Tidak dipantau lagi</span><?php else: ?>Tidak tersedia<?php endif; ?></dd></div><div><dt>Ditangani oleh</dt><dd><?= e(
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
