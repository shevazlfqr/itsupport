<aside id="app-sidebar" class="sidebar">
    <a class="brand" href="{{ route('dashboard') }}">
        <span class="brand-mark">H</span>
        <span>Helpdesk</span>
    </a>
    <nav>
        <span class="nav-label">WORKSPACE</span>
        <a class="{{ !$ticketId && $view === 'dashboard' ? 'active' : '' }}" href="{{ route('dashboard') }}">
            <i aria-hidden="true"></i> Dashboard
        </a>
        <a class="{{ $view === 'tickets' || $ticketId ? 'active' : '' }}" href="{{ route('dashboard', ['view' => 'tickets']) }}">
            <i aria-hidden="true"></i> Semua Ticket <b>{{ count($tickets) }}</b>
        </a>
        <a class="{{ $view === 'new' ? 'active' : '' }}" href="{{ route('tickets.create') }}">
            <i aria-hidden="true"></i> Buat Ticket
        </a>
        @if (in_array($user['role'], ['it', 'admin'], true))
            <a class="{{ $view === 'analytics' ? 'active' : '' }}" href="{{ route('dashboard', ['view' => 'analytics']) }}">
                <i aria-hidden="true"></i> Analitik
            </a>
        @endif
        @if ($user['role'] === 'admin')
            <a class="{{ $view === 'users' ? 'active' : '' }}" href="{{ route('users.index') }}">
                <i aria-hidden="true"></i> Kelola User <b>{{ count($data['users']) }}</b>
            </a>
        @endif
    </nav>
    <div class="sidebar-help">
        <span>?</span>
        <strong>Butuh bantuan cepat?</strong>
        <p>Untuk insiden kritis, hubungi ext. 101.</p>
    </div>
    <div class="profile">
        <div class="avatar">{{ e(strtoupper(substr($user['name'], 0, 1))) }}</div>
        <div>
            <strong>{{ e($user['name']) }}</strong>
            <span>{{ e($user['role'] === 'admin' ? 'Administrator' : ($user['role'] === 'it' ? 'Tim IT' : $user['department'])) }}</span>
        </div>
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="profile-logout">Keluar</button>
        </form>
    </div>
</aside>
