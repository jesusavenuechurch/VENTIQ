<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Models\Ticket;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\DB;

class EventDashboard extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Event Dashboard';
    protected static ?string $navigationGroup = 'Analytics';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.event-dashboard';

    public ?int $selectedEventId = null;
    public $stats = [];
    public $tierBreakdown = [];
    public $deliveryStats = [];
    public $recentActivity = [];
    public $failedDeliveries = [];

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        // Get first event by default (if user has access)
        $firstEvent = $this->getAvailableEvents()->first();
        
        if ($firstEvent) {
            $this->selectedEventId = $firstEvent->id;
            $this->loadDashboardData();
        }
    }

    protected function getFormSchema(): array
    {
        return [
            Select::make('selectedEventId')
                ->label('Select Event')
                ->options($this->getAvailableEvents()->pluck('name', 'id'))
                ->reactive()
                ->afterStateUpdated(fn () => $this->loadDashboardData()),
        ];
    }

    /**
     * Get events user has access to
     */
    protected function getAvailableEvents()
    {
        $user = auth()->user();

        $query = Event::query()->with('organization');

        if (!$user->isSuperAdmin()) {
            $query->where('organization_id', $user->organization_id);
        }

        return $query->orderBy('event_date', 'desc')->get();
    }

    /**
     * Load all dashboard data
     */
    public function loadDashboardData(): void
    {
        if (!$this->selectedEventId) {
            return;
        }

        $event = Event::with(['tiers', 'organization'])->find($this->selectedEventId);
        
        if (!$event) {
            return;
        }

        // 1. Quick Stats
        $this->stats = $this->getQuickStats($event);

        // 2. Tier Breakdown
        $this->tierBreakdown = $this->getTierBreakdown($event);

        // 3. Delivery Stats
        $this->deliveryStats = $this->getDeliveryStats($event);

        // 4. Recent Activity
        $this->recentActivity = $this->getRecentActivity($event);

        // 5. Failed Deliveries
        $this->failedDeliveries = $this->getFailedDeliveries($event);
    }

    /**
     * Get quick stats (tickets sold, revenue, check-ins, pending)
     */
    protected function getQuickStats(Event $event): array
    {
        $tickets = Ticket::where('event_id', $event->id);

        $totalTickets = $tickets->count();
        $totalRevenue = $tickets->sum('amount');
        $checkedIn = $tickets->whereNotNull('checked_in_at')->count();
        $pendingPayment = $tickets->where('payment_status', 'pending')->count();

        // Calculate capacity
        $totalCapacity = $event->tiers->sum('quantity_available') ?? 0;
        $capacityPercentage = $totalCapacity > 0 ? round(($totalTickets / $totalCapacity) * 100, 1) : 0;

        // Calculate check-in percentage
        $checkInPercentage = $totalTickets > 0 ? round(($checkedIn / $totalTickets) * 100, 1) : 0;

        // Revenue goal (sum of all tier capacities × prices)
        $revenueGoal = $event->tiers->sum(function ($tier) {
            return ($tier->quantity_available ?? 0) * $tier->price;
        });
        $revenuePercentage = $revenueGoal > 0 ? round(($totalRevenue / $revenueGoal) * 100, 1) : 0;

        return [
            'total_tickets' => $totalTickets,
            'capacity_percentage' => $capacityPercentage,
            'total_revenue' => $totalRevenue,
            'revenue_goal' => $revenueGoal,
            'revenue_percentage' => $revenuePercentage,
            'checked_in' => $checkedIn,
            'check_in_percentage' => $checkInPercentage,
            'pending_payment' => $pendingPayment,
        ];
    }

    /**
     * Get tier breakdown
     */
    protected function getTierBreakdown(Event $event): array
    {
        $breakdown = [];

        foreach ($event->tiers as $tier) {
            $sold = Ticket::where('event_id', $event->id)
                ->where('event_tier_id', $tier->id)
                ->count();

            $revenue = Ticket::where('event_id', $event->id)
                ->where('event_tier_id', $tier->id)
                ->sum('amount');

            $capacity = $tier->quantity_available ?? 0;
            $percentage = $capacity > 0 ? round(($sold / $capacity) * 100, 1) : 0;

            $breakdown[] = [
                'name' => $tier->tier_name,
                'price' => $tier->price,
                'sold' => $sold,
                'capacity' => $capacity,
                'percentage' => $percentage,
                'revenue' => $revenue,
                'color' => $tier->tier_color ?? '#3B82F6',
            ];
        }

        return $breakdown;
    }

    /**
     * Get delivery statistics
     */
    protected function getDeliveryStats(Event $event): array
    {
        $tickets = Ticket::where('event_id', $event->id);

        $delivered = $tickets->clone()->where('delivery_status', 'delivered')->count();
        $pending = $tickets->clone()->where('delivery_status', 'pending')->count();
        $failed = $tickets->clone()->where('delivery_status', 'failed')->count();
        $total = $tickets->count();

        // Delivery methods breakdown
        $whatsapp = $tickets->clone()->where('preferred_delivery', 'whatsapp')->count();
        $email = $tickets->clone()->where('preferred_delivery', 'email')->count();
        $both = $tickets->clone()->where('preferred_delivery', 'both')->count();

        $deliveredPercentage = $total > 0 ? round(($delivered / $total) * 100, 1) : 0;

        return [
            'delivered' => $delivered,
            'pending' => $pending,
            'failed' => $failed,
            'total' => $total,
            'delivered_percentage' => $deliveredPercentage,
            'whatsapp' => $whatsapp,
            'email' => $email,
            'both' => $both,
        ];
    }

    /**
     * Get recent activity
     */
    protected function getRecentActivity(Event $event): array
    {
        return Ticket::where('event_id', $event->id)
            ->with(['client', 'tier'])
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'client_name' => $ticket->holder_name,
                    'action' => $this->getActivityAction($ticket),
                    'icon' => $this->getActivityIcon($ticket),
                    'color' => $this->getActivityColor($ticket),
                    'time' => $ticket->updated_at->diffForHumans(),
                    'details' => $this->getActivityDetails($ticket),
                ];
            })
            ->toArray();
    }

    /**
     * Get failed deliveries
     */
    protected function getFailedDeliveries(Event $event): array
    {
        return Ticket::where('event_id', $event->id)
            ->where('delivery_status', 'failed')
            ->with(['client', 'tier'])
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($ticket) {
                $logs = $ticket->delivery_log ?? [];
                $lastLog = collect($logs)->last();
                
                return [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'client_name' => $ticket->holder_name,
                    'client_phone' => $ticket->client->phone,
                    'error' => $lastLog['error'] ?? 'Unknown error',
                    'time' => $ticket->updated_at->diffForHumans(),
                ];
            })
            ->toArray();
    }

    /**
     * Helper: Get activity action text
     */
    protected function getActivityAction(Ticket $ticket): string
    {
        if ($ticket->checked_in_at) {
            return 'Checked in';
        }
        
        if ($ticket->delivery_status === 'delivered') {
            return 'Ticket delivered';
        }
        
        if ($ticket->payment_status === 'completed') {
            return 'Payment approved';
        }
        
        if ($ticket->payment_status === 'pending') {
            return 'New registration';
        }
        
        return 'Updated';
    }

    /**
     * Helper: Get activity icon
     */
    protected function getActivityIcon(Ticket $ticket): string
    {
        if ($ticket->checked_in_at) {
            return 'heroicon-o-check-badge';
        }
        
        if ($ticket->delivery_status === 'delivered') {
            return 'heroicon-o-paper-airplane';
        }
        
        if ($ticket->payment_status === 'completed') {
            return 'heroicon-o-check-circle';
        }
        
        return 'heroicon-o-ticket';
    }

    /**
     * Helper: Get activity color
     */
    protected function getActivityColor(Ticket $ticket): string
    {
        if ($ticket->checked_in_at) {
            return 'success';
        }
        
        if ($ticket->delivery_status === 'delivered') {
            return 'info';
        }
        
        if ($ticket->payment_status === 'completed') {
            return 'success';
        }
        
        return 'gray';
    }

    /**
     * Helper: Get activity details
     */
    protected function getActivityDetails(Ticket $ticket): string
    {
        $parts = [];
        
        $parts[] = $ticket->tier->tier_name;
        
        if ($ticket->amount > 0) {
            $parts[] = config('constants.currency.symbol') . ' ' . number_format($ticket->amount);
        }
        
        return implode(' • ', $parts);
    }

    /**
     * Export database for selected event
     */
    public function exportDatabase()
    {
        if (!$this->selectedEventId) {
            return;
        }

        $event = Event::find($this->selectedEventId);
        
        $tickets = Ticket::where('event_id', $this->selectedEventId)
            ->with(['client', 'tier'])
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'clients-' . $event->slug . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($tickets) {
            $handle = fopen('php://output', 'w');
            
            // Headers
            fputcsv($handle, [
                'Ticket Number',
                'Client Name',
                'Email',
                'Phone',
                'Tier',
                'Amount',
                'Payment Status',
                'Delivery Status',
                'Checked In',
                'Registration Date',
            ]);
            
            // Data
            foreach ($tickets as $ticket) {
                fputcsv($handle, [
                    $ticket->ticket_number,
                    $ticket->holder_name,
                    $ticket->client->email ?? '',
                    $ticket->client->phone,
                    $ticket->tier->tier_name,
                    $ticket->amount,
                    $ticket->payment_status,
                    $ticket->delivery_status,
                    $ticket->checked_in_at ? 'Yes' : 'No',
                    $ticket->created_at->format('Y-m-d H:i'),
                ]);
            }
            
            fclose($handle);
        }, $filename);
    }

    /**
     * Can access this page?
     */
    public static function canAccess(): bool
    {
        return auth()->user()->can('view_ticket');
    }
}