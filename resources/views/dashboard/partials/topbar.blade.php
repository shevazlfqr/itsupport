<header class="topbar">
    <button type="button" class="menu-toggle" aria-label="Menu navigasi" aria-expanded="false" aria-controls="app-sidebar">Menu</button>
    <div class="crumb">IT Support <span>/</span> {{ e($crumb) }}</div>
    <div class="top-actions">
        <div class="notification">
            <button class="notification-button" type="button" aria-label="Notifikasi" aria-expanded="false" aria-controls="notification-menu">
                <span aria-hidden="true"></span>
                @if ($unreadTotal)
                    <b>{{ $unreadTotal }}</b>
                @endif
            </button>
            <div id="notification-menu" class="notification-menu" role="region" aria-label="Aktivitas terbaru">
                <div class="notification-head">
                    <div>
                        <strong>Aktivitas terbaru</strong>
                        <span>Perubahan terbaru pada ticket yang kamu ikuti</span>
                    </div>
                    @if ($unreadTotal)
                        <i>{{ $unreadTotal }}</i>
                    @endif
                </div>
                <div class="notification-list">
                    @if (!$unreadByTicket)
                        <div class="notification-empty">
                            <span aria-hidden="true"></span>
                            <strong>Semua sudah dibaca</strong>
                            <p>Belum ada aktivitas baru.</p>
                        </div>
                    @else
                        @foreach ($unreadByTicket as $noticeTicketId => $amount)
                            @php
                                $noticeTicket = collect($tickets)->firstWhere('id', $noticeTicketId);
                            @endphp
                            @continue(!$noticeTicket)
                            <a href="{{ route('tickets.show', $noticeTicketId) }}">
                                <i class="notification-item-icon" aria-hidden="true">{{ e(strtoupper(substr($noticeTicket['subject'], 0, 1))) }}</i>
                                <span>
                                    <strong>{{ e($noticeTicket['subject']) }}</strong>
                                    <small>{{ e($unreadLatest[$noticeTicketId] ?? 'Update ticket') }} · #{{ $noticeTicketId }}</small>
                                </span>
                                <b>{{ $amount }}</b>
                            </a>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
        <a class="btn primary compact" href="{{ route('tickets.create') }}">Buat Ticket</a>
    </div>
</header>
