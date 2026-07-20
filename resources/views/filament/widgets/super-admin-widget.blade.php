<x-filament-widgets::widget>
<div class="v-breakout-wrapper">
    <div class="v-glass-panel" x-data="{ loaded: false }" x-init="setTimeout(() => loaded = true, 100)">

        <div class="v-mesh"></div>
        <div class="v-scanner"></div>

        <div class="v-content" x-show="loaded" x-cloak>

            <header class="v-header">
                <div class="v-sys-info">
                    <span class="v-glitch">ADMIN</span>
                    <span class="v-divider">|</span>
                    <span>VENTIQ CONTROL • {{ date('Y') }}</span>
                </div>
                <div class="v-package-badge">SUPER ADMIN</div>
            </header>

            <section class="v-hero">
                <h1 class="v-title">Ventiq Overview</h1>
                <p class="v-lead">
                    {{ $active_orgs }} active organisations · {{ $new_orgs_this_month }} new this month · {{ $online_tickets_month }} online tickets this month
                </p>
            </section>

            {{-- ── FINANCIAL STATS ──────────────────────────────────── --}}
            <div class="v-stats-grid">

                <div class="v-stat-card" style="--stat-color: #10B981">
                    <span class="v-stat-value">M{{ number_format($package_revenue, 0) }}</span>
                    <span class="v-stat-label">Package Revenue</span>
                    <span class="v-stat-badge">Ventiq earnings</span>
                </div>

                <div class="v-stat-card" style="--stat-color: #3B82F6">
                    <span class="v-stat-value">M{{ number_format($holding_balance, 0) }}</span>
                    <span class="v-stat-label">Holding (Ticket $)</span>
                    <span class="v-stat-badge">In Ventiq account</span>
                </div>

                <div class="v-stat-card" style="--stat-color: #F07F22">
                    <span class="v-stat-value">M{{ number_format($owed_to_orgs, 0) }}</span>
                    <span class="v-stat-label">Owed to Orgs</span>
                    <span class="v-stat-badge">Settle this</span>
                </div>

                <div class="v-stat-card" style="--stat-color: #8B5CF6">
                    <span class="v-stat-value">M{{ number_format($ventiq_cut_pending, 0) }}</span>
                    <span class="v-stat-label">Ventiq Cut (Tickets)</span>
                    <span class="v-stat-badge">After settlement</span>
                </div>

                @if($pending_packages > 0)
                <div class="v-stat-card" style="--stat-color: #EF4444">
                    <span class="v-stat-value">{{ $pending_packages }}</span>
                    <span class="v-stat-label">Packages Pending</span>
                    <span class="v-stat-badge">Needs approval</span>
                </div>
                @endif

                @if($pending_tickets > 0)
                <div class="v-stat-card" style="--stat-color: #EF4444">
                    <span class="v-stat-value">{{ $pending_tickets }}</span>
                    <span class="v-stat-label">Tickets Pending</span>
                    <span class="v-stat-badge">Manual payments</span>
                </div>
                @endif

            </div>

            {{-- ── QUICK ACTIONS ────────────────────────────────────── --}}
            <div class="v-grid" style="margin-top: 1.5rem;">
                @foreach($quick_actions as $action)
                    <a href="{{ route($action['route']) }}" class="v-card">
                        <div class="v-icon-frame" style="--icon-color: {{ $action['color'] }}">
                            <x-filament::icon :icon="$action['icon']" class="h-6 w-6" />
                        </div>
                        <div class="v-card-body">
                            <h3 class="v-card-label">{{ $action['title'] }}</h3>
                            @if($action['value'])
                                <p class="v-card-desc">{{ $action['value'] }}</p>
                            @endif
                        </div>
                        @if($action['urgent'])
                            <div class="v-status is-pending">● Urgent</div>
                        @endif
                    </a>
                @endforeach
            </div>

        </div>
    </div>
</div>
</x-filament-widgets::widget>