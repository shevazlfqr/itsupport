<header class="topbar">
    <button type="button" class="menu-toggle" aria-label="Menu navigasi" aria-expanded="false" aria-controls="app-sidebar"><span class="menu-toggle-icon" aria-hidden="true"><i></i><i></i><i></i></span></button>
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
                    <div class="notification-head-copy">
                        <strong>Aktivitas terbaru</strong>
                        <span>Pembaruan tiket yang kamu ikuti</span>
                    </div>
                    @if ($unreadTotal)
                        <form class="notification-mark-all" method="POST" action="{{ route('notifications.read-all') }}">
                            @csrf
                            <button class="notification-mark-all-button" type="submit" aria-label="Tandai semua notifikasi sudah dibaca">Tandai semua dibaca</button>
                        </form>
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
