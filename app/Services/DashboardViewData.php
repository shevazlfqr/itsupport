<?php

namespace App\Services;

use Illuminate\Http\Request;

class DashboardViewData
{
    public function build(Request $request): array
    {
        require_once app_path('Support/legacy.php');

        $data = readData();
        $user = currentUser($data);
        $flash = session('error')
            ? ['type' => 'error', 'message' => session('error')]
            : (session('success') ? ['type' => 'success', 'message' => session('success')] : null);
        abort_unless($user, 401);

        $tickets = array_values(
            array_filter($data['tickets'], fn ($t) => canView($t, $user)),
        );
        usort(
            $tickets,
            fn ($a, $b) => strtotime($b['updated_at']) <=> strtotime($a['updated_at']),
        );
        $view = $request->string('view', 'dashboard')->toString();
        if ($view === 'users' && $user['role'] !== 'admin') {
            abort(403, 'Akses khusus Admin.');
        }
        if ($view === 'analytics' && ! in_array($user['role'], ['it', 'admin'], true)) {
            abort(403, 'Akses khusus tim IT dan Admin.');
        }
        $ticketId = $request->integer('ticket') ?: null;
        $selected = null;
        if ($ticketId) {
            foreach ($tickets as $t) {
                if ($t['id'] === $ticketId) {
                    $selected = $t;
                }
            }
        }
        if ($selected) {
            $readAt = date(DATE_ATOM);
            $found = false;
            foreach ($data['reads'] as &$r) {
                if ($r['ticket_id'] === $ticketId && $r['user_id'] === $user['id']) {
                    $r['last_read_at'] = $readAt;
                    $found = true;
                }
            }
            if (! $found) {
                $data['reads'][] = [
                    'ticket_id' => $ticketId,
                    'user_id' => $user['id'],
                    'last_read_at' => $readAt,
                ];
            }
        }
        $unreadByTicket = [];
        $unreadLatest = [];
        foreach ($tickets as $t) {
            $last = '1970-01-01T00:00:00+00:00';
            foreach ($data['reads'] as $r) {
                if ($r['ticket_id'] === $t['id'] && $r['user_id'] === $user['id']) {
                    $last = $r['last_read_at'];
                }
            }
            foreach ($data['comments'] as $c) {
                if (
                    $c['ticket_id'] === $t['id'] &&
                    $c['user_id'] !== $user['id'] &&
                    strtotime($c['created_at']) > strtotime($last)
                ) {
                    $unreadByTicket[$t['id']] = ($unreadByTicket[$t['id']] ?? 0) + 1;
                    $unreadLatest[$t['id']] = 'Komentar baru';
                }
            }
            foreach ($data['activities'] as $a) {
                if (
                    $a['ticket_id'] === $t['id'] &&
                    $a['user_id'] !== $user['id'] &&
                    $a['message'] !== 'Menambahkan komentar' &&
                    strtotime($a['created_at']) > strtotime($last)
                ) {
                    $unreadByTicket[$t['id']] = ($unreadByTicket[$t['id']] ?? 0) + 1;
                    $unreadLatest[$t['id']] = $a['message'];
                }
            }
        }
        $unreadTotal = array_sum($unreadByTicket);
        $mine = $user['role'] === 'it' && $request->query('mine') === '1';
        $search = trim((string) $request->query('q', ''));
        $statusFilter = (string) $request->query('status', '');
        $priorityFilter = (string) $request->query('priority', '');
        $categoryFilter = (string) $request->query('category', '');
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');

        $filtered = array_values(
            array_filter(
                $tickets,
                function ($t) use ($mine, $search, $statusFilter, $priorityFilter, $categoryFilter, $dateFrom, $dateTo, $user, $data) {
                    if ($mine && $t['assignee_id'] !== $user['id']) {
                        return false;
                    }
                    if ($statusFilter && $t['status'] !== $statusFilter) {
                        return false;
                    }
                    if ($priorityFilter && $t['priority'] !== $priorityFilter) {
                        return false;
                    }
                    if ($categoryFilter && $t['category'] !== $categoryFilter) {
                        return false;
                    }
                    if ($search) {
                        $requester = null;
                        foreach ($data['users'] as $u) {
                            if ($u['id'] === $t['requester_id']) {
                                $requester = $u;
                                break;
                            }
                        }
                        $haystack = $t['subject'].' '.$t['id'].' '.$t['description'].' '.($requester['name'] ?? '');
                        if (stripos($haystack, $search) === false) {
                            return false;
                        }
                    }
                    if ($dateFrom && strtotime($t['created_at']) < strtotime($dateFrom)) {
                        return false;
                    }
                    if ($dateTo && strtotime($t['created_at']) > strtotime($dateTo.' 23:59:59')) {
                        return false;
                    }

                    return true;
                }
            ),
        );
        $counts = [];
        foreach (STATUSES as $key => $label) {
            $counts[$key] = count(
                array_filter($tickets, fn ($t) => $t['status'] === $key),
            );
        }
        $activeStatuses = ['new', 'assigned', 'in_progress'];
        $priorityRank = ['urgent' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        $itQueue = array_values(
            array_filter($tickets, fn ($t) => in_array($t['status'], $activeStatuses, true)),
        );
        usort($itQueue, function ($left, $right) use ($priorityRank) {
            $priority = ($priorityRank[$left['priority']] ?? 9) <=> ($priorityRank[$right['priority']] ?? 9);
            if ($priority !== 0) {
                return $priority;
            }
            $assignment = (int) empty($right['assignee_id']) <=> (int) empty($left['assignee_id']);
            if ($assignment !== 0) {
                return $assignment;
            }

            return strtotime($left['created_at']) <=> strtotime($right['created_at']);
        });
        $itHighPriorityCount = count(
            array_filter($itQueue, fn ($t) => in_array($t['priority'], ['high', 'urgent'], true)),
        );
        $itActiveCount = count($itQueue);
        $itQueue = array_slice($itQueue, 0, 5);
        $requesterRecent = array_slice($tickets, 0, 4);
        $itUsers = array_values(
            array_filter($data['users'], fn ($u) => $u['role'] === 'it'),
        );
        $categoryCounts = array_fill_keys(CATEGORIES, 0);
        $priorityCounts = array_fill_keys(array_keys(PRIORITIES), 0);
        foreach ($tickets as $t) {
            $categoryCounts[$t['category']] = $categoryCounts[$t['category']] ?? 0;
            $categoryCounts[$t['category']]++;
            $priorityCounts[$t['priority']]++;
        }
        $techStats = [];
        foreach ($itUsers as $it) {
            $handled = array_values(
                array_filter($tickets, fn ($t) => $t['assignee_id'] === $it['id']),
            );
            $techStats[] = [
                'user' => $it,
                'total' => count($handled),
                'active' => count(
                    array_filter(
                        $handled,
                        fn ($t) => in_array(
                            $t['status'],
                            ['assigned', 'in_progress'],
                            true,
                        ),
                    ),
                ),
                'done' => count(
                    array_filter(
                        $handled,
                        fn ($t) => in_array($t['status'], ['completed', 'closed'], true),
                    ),
                ),
            ];
        }
        usort($techStats, fn ($a, $b) => $b['total'] <=> $a['total']);
        $recentActivities = $data['activities'];
        usort(
            $recentActivities,
            fn ($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']),
        );
        $recentActivities = array_slice($recentActivities, 0, 6);
        $statusColors = [
            'new' => '#4d8cff',
            'assigned' => '#8369e8',
            'in_progress' => '#f3a72f',
            'completed' => '#28a978',
            'closed' => '#718096',
            'cancelled' => '#e15b64',
        ];
        $chartParts = [];
        $chartCursor = 0;
        $ticketTotal = max(count($tickets), 1);
        foreach (STATUSES as $key => $label) {
            $next = $chartCursor + ($counts[$key] / $ticketTotal) * 100;
            $chartParts[] =
                $statusColors[$key].' '.$chartCursor.'% '.$next.'%';
            $chartCursor = $next;
        }
        $statusGradient = implode(',', $chartParts);
        $maxCategory = max(1, max($categoryCounts));
        $techTotals = array_map(fn ($stat) => $stat['total'], $techStats);
        $maxTech = $techTotals ? max(1, max($techTotals)) : 1;

        $crumb = $selected
            ? 'Ticket #'.$selected['id']
            : ($view === 'new'
                ? 'Buat Ticket'
                : ($view === 'tickets'
                    ? 'Semua Ticket'
                    : ($view === 'users'
                        ? 'Kelola User'
                        : ($view === 'analytics' ? 'Analitik' : 'Dashboard'))));

        $analytics = null;
        if ($view === 'analytics') {
            $rangeDays = (int) ($_GET['range'] ?? 30);
            if (! in_array($rangeDays, [7, 30, 90], true)) {
                $rangeDays = 30;
            }
            $rangeStart = strtotime('-'.($rangeDays - 1).' days midnight');

            $volumeLabels = [];
            $volumeCreated = [];
            $volumeResolved = [];
            for ($i = 0; $i < $rangeDays; $i++) {
                $dayStart = strtotime("+$i days", $rangeStart);
                $dayEnd = $dayStart + 86400;
                $volumeLabels[] = date('d/m', $dayStart);
                $created = 0;
                $resolved = 0;
                foreach ($tickets as $t) {
                    $c = strtotime($t['created_at']);
                    if ($c >= $dayStart && $c < $dayEnd) {
                        $created++;
                    }
                    if (in_array($t['status'], ['completed', 'closed'], true)) {
                        $u = strtotime($t['updated_at']);
                        if ($u >= $dayStart && $u < $dayEnd) {
                            $resolved++;
                        }
                    }
                }
                $volumeCreated[] = $created;
                $volumeResolved[] = $resolved;
            }

            $resolutionByCategory = [];
            foreach (CATEGORIES as $cat) {
                $resolutionByCategory[$cat] = ['sum' => 0, 'count' => 0];
            }
            $totalResolutionSum = 0;
            $totalResolutionCount = 0;
            $slaBreached = 0;
            $slaOnTime = 0;
            foreach ($tickets as $t) {
                if (! in_array($t['status'], ['completed', 'closed'], true)) {
                    continue;
                }
                $hours = (strtotime($t['updated_at']) - strtotime($t['created_at'])) / 3600;
                if ($hours < 0) {
                    $hours = 0;
                }
                if (isset($resolutionByCategory[$t['category']])) {
                    $resolutionByCategory[$t['category']]['sum'] += $hours;
                    $resolutionByCategory[$t['category']]['count']++;
                }
                $totalResolutionSum += $hours;
                $totalResolutionCount++;
                if (! empty($t['sla_deadline'])) {
                    if (strtotime($t['updated_at']) > strtotime($t['sla_deadline'])) {
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
                $avgResolutionValues[] = $agg['count'] ? round($agg['sum'] / $agg['count'], 1) : 0;
            }

            $avgResolutionOverall = $totalResolutionCount
                ? round($totalResolutionSum / $totalResolutionCount, 1)
                : 0;
            $slaTotal = $slaBreached + $slaOnTime;
            $slaCompliance = $slaTotal ? round(($slaOnTime / $slaTotal) * 100) : 100;

            $slaAtRisk = 0;
            $slaOverdue = 0;
            foreach ($tickets as $t) {
                $s = slaStatus($t);
                if (! $s) {
                    continue;
                }
                if ($s['status'] === 'breached') {
                    $slaOverdue++;
                } elseif ($s['status'] === 'warning') {
                    $slaAtRisk++;
                }
            }

            $analytics = [
                'range_days' => $rangeDays,
                'volume' => [
                    'labels' => $volumeLabels,
                    'created' => $volumeCreated,
                    'resolved' => $volumeResolved,
                ],
                'resolution' => [
                    'labels' => $avgResolutionLabels,
                    'values' => $avgResolutionValues,
                ],
                'status' => [
                    'labels' => array_values(STATUSES),
                    'values' => array_map(fn ($k) => $counts[$k], array_keys(STATUSES)),
                    'colors' => array_map(fn ($k) => $statusColors[$k], array_keys(STATUSES)),
                ],
                'priority' => [
                    'labels' => array_values(PRIORITIES),
                    'values' => array_map(fn ($k) => $priorityCounts[$k], array_keys(PRIORITIES)),
                ],
                'tech' => [
                    'labels' => array_map(fn ($s) => $s['user']['name'], $techStats),
                    'done' => array_map(fn ($s) => $s['done'], $techStats),
                    'active' => array_map(fn ($s) => $s['active'], $techStats),
                ],
                'kpi' => [
                    'avg_resolution' => $avgResolutionOverall,
                    'sla_compliance' => $slaCompliance,
                    'sla_at_risk' => $slaAtRisk,
                    'sla_overdue' => $slaOverdue,
                    'total_resolved' => $totalResolutionCount,
                ],
            ];
        }
        $workspaceTitle = $view === 'tickets'
            ? 'Semua ticket'
            : ($view === 'dashboard' && $user['role'] === 'it'
                ? 'Queue layanan IT'
                : ($view === 'dashboard' && $user['role'] === 'employee'
                    ? 'Ticket kamu'
                    : 'Selamat datang, '.e(explode(' ', $user['name'])[0])));
        $workspaceDescription = $view === 'tickets'
            ? 'Kelola dan pantau seluruh permintaan bantuan IT.'
            : ($view === 'dashboard' && $user['role'] === 'it'
                ? 'Prioritaskan ticket yang perlu ditindak hari ini.'
                : ($view === 'dashboard' && $user['role'] === 'employee'
                    ? 'Pantau progres laporan dan respons tim IT.'
                    : 'Berikut ringkasan layanan IT kamu hari ini.'));

        return [
            'data' => $data,
            'user' => $user,
            'flash' => $flash,
            'tickets' => $tickets,
            'view' => $view,
            'ticketId' => $ticketId,
            'selected' => $selected,
            'unreadByTicket' => $unreadByTicket,
            'unreadLatest' => $unreadLatest,
            'unreadTotal' => $unreadTotal,
            'mine' => $mine,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'priorityFilter' => $priorityFilter,
            'categoryFilter' => $categoryFilter,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'filtered' => $filtered,
            'counts' => $counts,
            'itQueue' => $itQueue,
            'itHighPriorityCount' => $itHighPriorityCount,
            'itActiveCount' => $itActiveCount,
            'requesterRecent' => $requesterRecent,
            'itUsers' => $itUsers,
            'categoryCounts' => $categoryCounts,
            'priorityCounts' => $priorityCounts,
            'techStats' => $techStats,
            'recentActivities' => $recentActivities,
            'statusColors' => $statusColors,
            'statusGradient' => $statusGradient,
            'maxCategory' => $maxCategory,
            'maxTech' => $maxTech,
            'crumb' => $crumb,
            'analytics' => $analytics,
            'workspaceTitle' => $workspaceTitle,
            'workspaceDescription' => $workspaceDescription,
        ];
    }
}
