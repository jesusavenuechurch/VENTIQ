<?php

namespace App\Services\Reports;

use App\Models\Event;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class RegistrationSummaryService
{
    public function __construct(protected Event $event) {}

    public function downloadPdf(): \Symfony\Component\HttpFoundation\Response
    {
        $data = $this->buildData();

        $pdf = Pdf::loadView('reports.registration-summary', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont'          => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => false,
            ]);

        $filename = str($this->event->name)->slug() . '-registration-summary.pdf';

        return $pdf->download($filename);
    }

public function buildData(): array
{
    $event = $this->event->load(['organization', 'tiers', 'tickets.tier', 'tickets.payments']);

    $tickets       = $event->tickets;
    $checkedIn     = $tickets->where('status', 'checked_in');
    $complimentary = $tickets->where('is_complimentary', true);
    $paid          = $tickets->where('is_complimentary', false);

    // Per-tier breakdown
    $tierBreakdown = $event->tiers->map(function ($tier) use ($tickets) {
        $tierTickets = $tickets->where('event_tier_id', $tier->id);
        return [
            'name'          => $tier->tier_name,
            'price'         => $tier->price,
            'total'         => $tierTickets->count(),
            'checked_in'    => $tierTickets->where('status', 'checked_in')->count(),
            'complimentary' => $tierTickets->where('is_complimentary', true)->count(),
            'paid'          => $tierTickets->where('is_complimentary', false)->count(),
        ];
    });

    // Per-tier revenue (for revenue section of blade)
    $tierRevenue = $event->tiers->map(function ($tier) use ($tickets) {
        $tierTickets = $tickets->where('event_tier_id', $tier->id)
                               ->where('is_complimentary', false);
        return [
            'name'      => $tier->tier_name,
            'price'     => $tier->price,
            'sold'      => $tierTickets->count(),
            'expected'  => $tierTickets->sum('amount'),
            'collected' => $tierTickets->sum('amount_paid'),
        ];
    });

    $totalExpected   = $paid->sum('amount');
    $totalCollected  = $paid->sum('amount_paid');

    // Logo
    [$logoBase64, $logoWarning] = $this->resolveLogo($event->organization);

    return [
        'event'            => $event,
        'org'              => $event->organization,
        'currency'         => config('constants.currency.symbol', 'LSL'),
        'totalTickets'     => $tickets->count(),
        'checkedIn'        => $checkedIn->count(),
        'notCheckedIn'     => $tickets->count() - $checkedIn->count(),
        'attendanceRate'   => $tickets->count() > 0
            ? round(($checkedIn->count() / $tickets->count()) * 100, 1)
            : 0,
        'complimentary'    => $complimentary->count(),
        'paid'             => $paid->count(),
        'totalExpected'    => $totalExpected,
        'totalCollected'   => $totalCollected,
        'totalOutstanding' => $totalExpected - $totalCollected,
        'collectionRate'   => $totalExpected > 0
            ? round(($totalCollected / $totalExpected) * 100, 1)
            : 0,
        'compTickets'      => $complimentary->count(),
        'compValue'        => $complimentary->count() * ($event->tiers->first()?->price ?? 0),
        'paymentBreakdown' => [
            'completed' => $paid->where('payment_status', 'completed')->count(),
            'partial'   => $paid->where('payment_status', 'partial')->count(),
            'pending'   => $paid->where('payment_status', 'pending')->count(),
            'refunded'  => $paid->where('payment_status', 'refunded')->count(),
        ],
        'tierBreakdown'    => $tierBreakdown,
        'tierRevenue'      => $tierRevenue,
        'logoBase64'       => $logoBase64,
        'logoWarning'      => $logoWarning,
        'generatedAt'      => now()->format('d M Y, H:i'),
        'generatedBy'      => auth()->user()?->name ?? 'System',
    ];
}

    protected function resolveLogo($org): array
    {
        if (!$org->logo_path) return [null, true];
        $path = storage_path('app/public/' . $org->logo_path);
        if (!file_exists($path)) return [null, true];
        $type = pathinfo($path, PATHINFO_EXTENSION);
        return ['data:image/' . $type . ';base64,' . base64_encode(file_get_contents($path)), false];
    }
}
