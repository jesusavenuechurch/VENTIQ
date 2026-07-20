<?php

namespace App\Filament\Widgets;

use App\Models\Organization;
use App\Models\OrganizationPackage;
use App\Models\PaymentSession;
use App\Models\SettlementItem;
use App\Models\Ticket;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class SuperAdminWidget extends Widget
{
    protected static string $view = 'filament.widgets.super-admin-widget';
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = -10;

    public static function canView(): bool
    {
        return Auth::user()?->isSuperAdmin() ?? false;
    }

    protected function getViewData(): array
    {
        // Package revenue — money Ventiq earned from package sales
        $packageRevenue = OrganizationPackage::where('status', 'active')
            ->where('is_free_trial', false)
            ->sum('price_paid');

        // Pending package approvals
        $pendingPackages = OrganizationPackage::where('status', 'pending')->count();

        // Online ticket revenue Ventiq is currently holding
        $holdingBalance = SettlementItem::whereNull('settlement_id')
            ->sum('amount_received');

        // Total owed to orgs from unsettled items
        $owedToOrgs = SettlementItem::whereNull('settlement_id')
            ->sum('amount_owed_to_org');

        // Ventiq's cut from unsettled (holding - owed)
        $ventiqCutPending = round($holdingBalance - $owedToOrgs, 2);

        // Active orgs
        $activeOrgs = Organization::where('is_active', true)->count();

        // New orgs this month
        $newOrgsThisMonth = Organization::where('is_active', true)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Total online tickets this month
        $onlineTicketsThisMonth = PaymentSession::where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('payable_type', 'ticket')
            ->count();

        // Pending ticket approvals across all orgs
        $pendingTickets = Ticket::where('payment_status', 'pending')->count();

        return [
            'package_revenue'       => $packageRevenue,
            'pending_packages'      => $pendingPackages,
            'holding_balance'       => $holdingBalance,
            'owed_to_orgs'          => $owedToOrgs,
            'ventiq_cut_pending'    => $ventiqCutPending,
            'active_orgs'           => $activeOrgs,
            'new_orgs_this_month'   => $newOrgsThisMonth,
            'online_tickets_month'  => $onlineTicketsThisMonth,
            'pending_tickets'       => $pendingTickets,

            'quick_actions' => [
                [
                    'title'  => 'Pending Packages',
                    'value'  => $pendingPackages,
                    'icon'   => 'heroicon-s-cube',
                    'color'  => '#F07F22',
                    'route'  => 'filament.admin.resources.package-purchases.index',
                    'urgent' => $pendingPackages > 0,
                ],
                [
                    'title'  => 'Pending Tickets',
                    'value'  => $pendingTickets,
                    'icon'   => 'heroicon-s-clock',
                    'color'  => '#EF4444',
                    'route'  => 'filament.admin.resources.tickets.index',
                    'urgent' => $pendingTickets > 0,
                ],
                [
                    'title'  => 'Settlements',
                    'value'  => 'M' . number_format($owedToOrgs, 0) . ' due',
                    'icon'   => 'heroicon-s-banknotes',
                    'color'  => '#10B981',
                    'route'  => 'filament.admin.resources.settlements.index',
                    'urgent' => $owedToOrgs > 0,
                ],
                [
                    'title'  => 'Organisations',
                    'value'  => $activeOrgs . ' active',
                    'icon'   => 'heroicon-s-building-office',
                    'color'  => '#1D4069',
                    'route'  => 'filament.admin.resources.organizations.index',
                    'urgent' => false,
                ],
            ],
        ];
    }
}