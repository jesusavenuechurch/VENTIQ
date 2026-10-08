<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\OrganizationPaymentMethodResource;
use App\Filament\Resources\PackagePurchaseResource;
use App\Filament\Resources\TicketResource;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\SettlementItem;
use App\Models\OrganizationPaymentMethod;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class WelcomeWidget extends Widget
{
    protected static string $view = 'filament.widgets.welcome-widget';
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = -10;

    public static function canView(): bool
    {
        $user = Auth::user();
        if (!$user || $user->isSuperAdmin() || $user->isSalesAgent()) return false;
        return (bool) $user->organization_id;
    }

    protected function getViewData(): array
    {
        $user = Auth::user();
        $org  = $user->organization;

        if (!$org) return [];

        $hasEvents  = Event::where('organization_id', $org->id)->exists();
        $package    = $org->activePackages()->first();

        // ── OPERATIONS MODE ───────────────────────────────────────────
        if ($hasEvents) {
            $ticketBase = Ticket::whereHas('event',
                fn ($q) => $q->where('organization_id', $org->id)
            );

            $pendingCount   = (clone $ticketBase)->where('payment_status', 'pending')->count();
            $activeCount    = (clone $ticketBase)->where('status', 'active')->count();
            $checkedInCount = (clone $ticketBase)->where('status', 'checked_in')->count();
            $todayCount     = (clone $ticketBase)->whereDate('created_at', today())->count();
            $totalRevenue   = (clone $ticketBase)->where('payment_status', 'completed')->sum('amount');

            // Settlement balance — how much Ventiq is holding for this org
            $unsettledBalance = SettlementItem::where('organization_id', $org->id)
                ->whereNull('settlement_id')
                ->sum('amount_owed_to_org');

            $upcomingEvent = Event::where('organization_id', $org->id)
                ->where('event_date', '>=', now())
                ->where('status', 'published')
                ->orderBy('event_date')
                ->first();

            return [
                'mode'             => 'operations',
                'org_name'         => $org->name,
                'package'          => $package,
                'pending_count'    => $pendingCount,
                'active_count'     => $activeCount,
                'checked_in_count' => $checkedInCount,
                'today_count'      => $todayCount,
                'total_revenue'    => $totalRevenue,
                'unsettled_balance'=> $unsettledBalance,
                'upcoming_event'   => $upcomingEvent,

                'quick_actions' => [
                    [
                        'title'  => 'Pending Approvals',
                        'value'  => $pendingCount,
                        'icon'   => 'heroicon-s-clock',
                        'color'  => '#F07F22',
                        'url'    => TicketResource::getUrl('index'),
                        'badge'  => $pendingCount > 0,
                    ],
                    [
                        'title'  => 'Create Event',
                        'value'  => null,
                        'icon'   => 'heroicon-s-plus-circle',
                        'color'  => '#1D4069',
                        'url'    => EventResource::getUrl('create'),
                        'badge'  => false,
                    ],
                    [
                        'title'  => 'My Events',
                        'value'  => null,
                        'icon'   => 'heroicon-s-calendar-days',
                        'color'  => '#10B981',
                        'url'    => EventResource::getUrl('index'),
                        'badge'  => false,
                    ],
                    [
                        'title'  => 'My Package',
                        'value'  => $package?->display_name,
                        'icon'   => 'heroicon-s-cube',
                        'color'  => '#8B5CF6',
                        'url'    => PackagePurchaseResource::getUrl('index'),
                        'badge'  => false,
                    ],
                ],
            ];
        }

        // ── ONBOARDING MODE ───────────────────────────────────────────
        $hasOnlinePayment = OrganizationPaymentMethod::where('organization_id', $org->id)
            ->where('payment_method', 'online')
            ->where('is_active', true)
            ->exists();

        $hasManualPayment = OrganizationPaymentMethod::where('organization_id', $org->id)
            ->where('payment_method', '!=', 'online')
            ->where('is_active', true)
            ->exists();

        return [
            'mode'     => 'onboarding',
            'org_name' => $org->name,
            'package'  => $package,

            'steps_completed' => [
                'package'        => $package !== null,
                'payment_method' => $hasOnlinePayment || $hasManualPayment,
                'event'          => false,
            ],

            'steps' => [
                [
                    'id'     => 'package',
                    'title'  => 'Activate a Package',
                    'desc'   => 'Start with a free trial or purchase a package to unlock your event capacity.',
                    'icon'   => 'heroicon-o-cube',
                    'url'    => PackagePurchaseResource::getUrl('index'),
                    'color'  => '#8B5CF6',
                ],
                [
                    'id'     => 'payment_method',
                    'title'  => 'Set Up Payments',
                    'desc'   => 'Online payments are configured during event creation. You can also add manual methods like M-Pesa or bank transfer.',
                    'icon'   => 'heroicon-o-credit-card',
                    'url'    => OrganizationPaymentMethodResource::getUrl('index'),
                    'color'  => '#F07F22',
                ],
                [
                    'id'     => 'event',
                    'title'  => 'Create Your First Event',
                    'desc'   => 'Add event details, set up tickets, choose free or paid, publish and share the link.',
                    'icon'   => 'heroicon-o-rocket-launch',
                    'url'    => EventResource::getUrl('create'),
                    'color'  => '#10B981',
                ],
            ],
        ];
    }
}