<x-filament-widgets::widget>
<div class="v-breakout-wrapper">
    <div class="v-glass-panel" x-data="{ loaded: false }" x-init="setTimeout(() => loaded = true, 100)">

        <div class="v-mesh"></div>
        <div class="v-scanner"></div>

        <div class="v-content" x-show="loaded" x-cloak>

            {{-- ── HEADER (shared) ──────────────────────────────────── --}}
            <header class="v-header">
                <div class="v-sys-info">
                    @if($mode === 'operations')
                        <span class="v-glitch">LIVE</span>
                    @else
                        <span class="v-glitch">SETUP</span>
                    @endif
                    <span class="v-divider">|</span>
                    <span>ORG: {{ strtoupper(substr($org_name, 0, 3)) }} • {{ date('Y') }}</span>
                </div>
                <div class="v-package-badge">{{ $package?->display_name ?? 'NO PACKAGE' }}</div>
            </header>

            {{-- ══════════════════════════════════════════════════════ --}}
            {{-- ONBOARDING MODE                                        --}}
            {{-- ══════════════════════════════════════════════════════ --}}
            @if($mode === 'onboarding')

                <section class="v-hero">
                    <h1 class="v-title">Welcome, {{ $org_name }}</h1>
                    <p class="v-lead">Complete the steps below to publish your first event.</p>
                </section>

                <div class="v-grid">
                    @foreach($steps as $step)
                        <a href="{{ route($step['route'], $step['params']) }}" class="v-card">
                            <div class="v-icon-frame" style="--icon-color: {{ $step['color'] }}">
                                <x-filament::icon :icon="$step['icon']" class="h-6 w-6" />
                            </div>
                            <div class="v-card-body">
                                <h3 class="v-card-label">{{ $step['title'] }}</h3>
                                <p class="v-card-desc">{{ $step['desc'] }}</p>
                            </div>
                            <div class="v-status {{ $steps_completed[$step['id']] ? 'is-complete' : 'is-pending' }}">
                                {{ $steps_completed[$step['id']] ? '✓ DONE' : '○ PENDING' }}
                            </div>
                        </a>
                    @endforeach
                </div>

            @endif

            {{-- ══════════════════════════════════════════════════════ --}}
            {{-- OPERATIONS MODE                                        --}}
            {{-- ══════════════════════════════════════════════════════ --}}
            @if($mode === 'operations')

                <section class="v-hero">
                    <h1 class="v-title">{{ $org_name }}</h1>
                    @if($upcoming_event)
                        <p class="v-lead">
                            Next event:
                            <strong style="color: #F07F22;">{{ $upcoming_event->name }}</strong>
                            — {{ $upcoming_event->event_date->format('d M Y') }}
                        </p>
                    @else
                        <p class="v-lead">No upcoming events. Ready to create one?</p>
                    @endif
                </section>

                {{-- ── STAT CARDS ──────────────────────────────────── --}}
                <div class="v-stats-grid">

                    <div class="v-stat-card" style="--stat-color: #F07F22">
                        <span class="v-stat-value">{{ $pending_count }}</span>
                        <span class="v-stat-label">Pending</span>
                        @if($pending_count > 0)
                            <span class="v-stat-badge">Action needed</span>
                        @endif
                    </div>

                    <div class="v-stat-card" style="--stat-color: #10B981">
                        <span class="v-stat-value">{{ $active_count }}</span>
                        <span class="v-stat-label">Active Tickets</span>
                    </div>

                    <div class="v-stat-card" style="--stat-color: #3B82F6">
                        <span class="v-stat-value">{{ $checked_in_count }}</span>
                        <span class="v-stat-label">Checked In</span>
                    </div>

                    <div class="v-stat-card" style="--stat-color: #8B5CF6">
                        <span class="v-stat-value">{{ $today_count }}</span>
                        <span class="v-stat-label">New Today</span>
                    </div>

                    <div class="v-stat-card" style="--stat-color: #1D4069">
                        <span class="v-stat-value">M{{ number_format($total_revenue, 0) }}</span>
                        <span class="v-stat-label">Revenue</span>
                    </div>

                    @if($unsettled_balance > 0)
                    <div class="v-stat-card" style="--stat-color: #F07F22">
                        <span class="v-stat-value">M{{ number_format($unsettled_balance, 0) }}</span>
                        <span class="v-stat-label">Pending Settlement</span>
                        <span class="v-stat-badge">Held by Ventiq</span>
                    </div>
                    @endif

                </div>

                {{-- ── QUICK ACTIONS ────────────────────────────────── --}}
                <div class="v-grid" style="margin-top: 1.5rem;">
                    @foreach($quick_actions as $action)
                        <a href="{{ route($action['route'], $action['params']) }}" class="v-card">
                            <div class="v-icon-frame" style="--icon-color: {{ $action['color'] }}">
                                <x-filament::icon :icon="$action['icon']" class="h-6 w-6" />
                            </div>
                            <div class="v-card-body">
                                <h3 class="v-card-label">{{ $action['title'] }}</h3>
                                @if($action['value'])
                                    <p class="v-card-desc">{{ $action['value'] }}</p>
                                @endif
                            </div>
                            @if($action['badge'] && $action['value'] > 0)
                                <div class="v-status is-pending">
                                    {{ $action['value'] }} waiting
                                </div>
                            @endif
                        </a>
                    @endforeach
                </div>

            @endif

        </div>
    </div>
</div>
</x-filament-widgets::widget>