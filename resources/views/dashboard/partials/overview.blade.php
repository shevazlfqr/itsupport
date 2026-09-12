<div class="page-title"><div><span class="eyebrow dark"><?= date(
    "l, d F Y",
) ?></span><h1><?= e($workspaceTitle) ?></h1><p><?= e(
    $workspaceDescription,
) ?></p></div></div>
<?php if (
    $view === "dashboard" &&
    $user["role"] === "admin"
): ?><section class="admin-analytics"><div class="analytics-heading"><div><span class="eyebrow dark">RINGKASAN OPERASIONAL</span><h2>Pemantauan layanan IT</h2><p>Distribusi ticket, beban teknisi, dan pola masalah yang diperbarui berkala.</p></div><span class="live-indicator"><i></i> Diperbarui berkala</span></div><div class="analytics-kpis"><article><span>Total ticket</span><strong><?= count(
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
<?php if ($view === "dashboard" && $user["role"] === "employee"): ?><section class="role-focus role-focus-requester" aria-labelledby="requester-status-title"><header class="role-focus-header"><div><span class="eyebrow dark">MY TICKETS</span><h2 id="requester-status-title">Status ticket kamu</h2><p>Pantau ticket terbaru dan tindak lanjut dari tim IT.</p></div><a class="btn primary compact" href="index.php?view=new">Buat ticket</a></header><div class="role-focus-metrics"><div><strong><?= $counts[
    "new"
] + $counts[
    "assigned"
] + $counts[
    "in_progress"
] ?></strong><span>ticket aktif</span></div><div><strong><?= $counts[
    "in_progress"
] ?></strong><span>sedang dikerjakan</span></div><div><strong><?= $counts[
    "completed"
] + $counts[
    "closed"
] ?></strong><span>selesai</span></div></div><div class="queue-list"><?php if (!$requesterRecent): ?><div class="role-focus-empty"><strong>Belum ada ticket</strong><span>Buat ticket pertama untuk meminta bantuan tim IT.</span></div><?php else:foreach (
    $requesterRecent
    as $recentTicket
): ?><a class="queue-item" href="index.php?ticket=<?= $recentTicket[
    "id"
] ?>"><div class="queue-item-copy"><div class="queue-item-labels"><span class="badge status-<?= e(
    $recentTicket["status"],
) ?>"><?= e(STATUSES[$recentTicket["status"]] ?? $recentTicket["status"]) ?></span></div><strong><?= e(
    $recentTicket["subject"],
) ?></strong><small>#<?= e($recentTicket["id"]) ?> · Diperbarui <?= e(
    relativeDate($recentTicket["updated_at"]),
) ?></small></div><span class="queue-item-arrow" aria-hidden="true"></span></a><?php endforeach;endif; ?></div></section><?php endif; ?>
<?php if (
    $view === "dashboard" && $user["role"] === "it"
): ?><section class="role-focus role-focus-it" aria-labelledby="it-queue-title"><header class="role-focus-header"><div><span class="eyebrow dark">WORK QUEUE</span><h2 id="it-queue-title">Ticket yang perlu ditindak</h2><p>Urutan berdasarkan urgensi, penugasan, dan usia ticket.</p></div><a class="btn light compact" href="index.php?view=tickets">Buka semua ticket</a></header><div class="role-focus-metrics"><div><strong><?= $itActiveCount ?></strong><span>ticket aktif di antrean</span></div><div><strong><?= $counts[
    "new"
] ?></strong><span>belum ditinjau</span></div><div><strong><?= $itHighPriorityCount ?></strong><span>prioritas tinggi</span></div></div><div class="queue-list"><?php if (!$itQueue): ?><div class="role-focus-empty"><strong>Queue sedang kosong</strong><span>Tidak ada ticket aktif yang perlu ditindak.</span></div><?php else:foreach (
    $itQueue
    as $queueTicket
): ?><a class="queue-item" href="index.php?ticket=<?= $queueTicket[
    "id"
] ?>"><div class="queue-item-copy"><div class="queue-item-labels"><span class="badge priority-<?= e(
    $queueTicket["priority"],
) ?>"><?= e(PRIORITIES[$queueTicket["priority"]] ?? $queueTicket["priority"]) ?></span><span class="badge status-<?= e(
    $queueTicket["status"],
) ?>"><?= e(STATUSES[$queueTicket["status"]] ?? $queueTicket["status"]) ?></span></div><strong><?= e(
    $queueTicket["subject"],
) ?></strong><small>#<?= e($queueTicket["id"]) ?> · <?= e(
    $queueTicket["category"],
) ?></small></div><span class="queue-item-arrow" aria-hidden="true"></span></a><?php endforeach;endif; ?></div></section>
<?php endif; ?>
<?php if ($view !== "dashboard" || $user["role"] === "admin"): ?><section class="table-card"><div class="table-head"><div><h2><?= $view ===
"tickets"
    ? "Daftar Ticket"
    : "Ticket Terbaru" ?></h2><p><?= count(
    $filtered,
) ?> ticket ditemukan</p></div><form class="filters" method="get"><input type="hidden" name="view" value="tickets"><div class="search"><span aria-hidden="true"></span><input name="q" value="<?= e(
     $search,
 ) ?>" placeholder="Cari ticket..." aria-label="Cari ticket berdasarkan judul, nomor, deskripsi, atau pelapor"></div><select name="status" aria-label="Saring berdasarkan status" onchange="this.form.submit()"><option value="">Semua status</option><?php foreach (
    STATUSES
    as $k => $v
): ?><option value="<?= $k ?>" <?= $statusFilter === $k
    ? "selected"
    : "" ?>><?= e(
    $v,
) ?></option><?php endforeach; ?></select><select name="priority" aria-label="Saring berdasarkan prioritas" onchange="this.form.submit()"><option value="">Semua prioritas</option><?php foreach (PRIORITIES as $k => $v): ?><option value="<?= $k ?>" <?= $priorityFilter === $k ? "selected" : "" ?>><?= e($v) ?></option><?php endforeach; ?></select><select name="category" aria-label="Saring berdasarkan kategori" onchange="this.form.submit()"><option value="">Semua kategori</option><?php foreach (CATEGORIES as $cat): ?><option value="<?= e($cat) ?>" <?= $categoryFilter === $cat ? "selected" : "" ?>><?= e($cat) ?></option><?php endforeach; ?></select><input type="date" name="date_from" value="<?= e($dateFrom) ?>" title="Dari tanggal" onchange="this.form.submit()"><input type="date" name="date_to" value="<?= e($dateTo) ?>" title="Sampai tanggal" onchange="this.form.submit()"><?php if ($search || $statusFilter || $priorityFilter || $categoryFilter || $dateFrom || $dateTo): ?><a class="btn-reset-filter" href="index.php?view=tickets<?= $mine ? '&mine=1' : '' ?>">Reset filter</a><?php endif; ?><?php if (in_array($user["role"], ["it","admin"], true)): ?><a class="btn light compact" href="<?= e('index.php?' . http_build_query(array_filter(['view'=>'tickets','export'=>'csv','q'=>$search,'status'=>$statusFilter,'priority'=>$priorityFilter,'category'=>$categoryFilter,'date_from'=>$dateFrom,'date_to'=>$dateTo]))) ?>" title="Unduh daftar ticket dalam CSV">Unduh CSV</a><?php endif; ?></form></div><div class="table-wrap"><table><thead><tr><th>TICKET</th><th>KATEGORI</th><th>PRIORITAS</th><th>STATUS</th><th>SLA</th><th>DITANGANI</th><th>UPDATE</th><th></th></tr></thead><tbody><?php
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
) ?></span></td><td><?php if ($sla): ?><span class="sla-badge sla-<?= e($sla["status"]) ?>" data-sla-deadline="<?= e($t["sla_deadline"]) ?>" data-sla-priority="<?= e($t["priority"]) ?>"><i></i><?= e($sla["label"]) ?></span><?php else: ?><span class="sla-badge sla-none">Tidak tersedia</span><?php endif; ?></td><td><?= e($assignee["name"] ?? "Belum ditugaskan") ?></td><td><?= e(
    relativeDate($t["updated_at"]),
) ?></td><td><a class="row-arrow" href="index.php?ticket=<?= $t[
    "id"
] ?>"><span class="row-arrow-icon" aria-hidden="true"><svg viewBox="0 0 16 16" focusable="false"><path d="M5 3.5 9.5 8 5 12.5M9 8H2" /></svg></span><span>Buka</span></a></td></tr><?php
endforeach;
if (
    !$filtered
): ?><tr><td colspan="8"><div class="empty-state" role="status"><strong><?= $search || $statusFilter || $priorityFilter || $categoryFilter || $dateFrom || $dateTo ? "Filter tidak menemukan ticket" : "Belum ada ticket" ?></strong><p><?= $search || $statusFilter || $priorityFilter || $categoryFilter || $dateFrom || $dateTo ? "Coba ubah kata kunci atau reset filter untuk melihat ticket lain." : "Buat ticket pertama untuk meminta bantuan tim IT." ?></p><?php if ($search || $statusFilter || $priorityFilter || $categoryFilter || $dateFrom || $dateTo): ?><a class="btn light compact" href="index.php?view=tickets<?= $mine ? '&mine=1' : '' ?>">Reset filter</a><?php else: ?><a class="btn primary compact" href="index.php?view=new">Buat ticket</a><?php endif; ?></div></td></tr><?php endif;
?></tbody></table></div><?php if (
    $view === "dashboard"
): ?><a class="view-all" href="index.php?view=tickets">Lihat semua ticket</a><?php endif; ?></section><?php endif; ?>
