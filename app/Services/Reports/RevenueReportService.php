<?php

namespace App\Services\Reports;

use App\Models\Event;
use Barryvdh\DomPDF\Facade\Pdf;

class RevenueReportService
{
    public function __construct(protected Event $event) {}

public function downloadPdf(): \Symfony\Component\HttpFoundation\Response
{
    $data = $this->buildData();

    $pdf = Pdf::loadView('reports.revenue-report', $data)
        ->setPaper('a4', 'portrait')
        ->setOptions([
            'defaultFont'          => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => false,
        ]);

    $filename = str($this->event->name)->slug() . '-revenue-report.pdf';

    return $pdf->download($filename);
}

    public function buildData(): array
    {
        $event = $this->event->load(['organization', 'tiers', 'tickets.payments', 'tickets.tier']);

        $tickets     = $event->tickets;
        $paidTickets = $tickets->where('is_complimentary', false);

        // Revenue figures
        $totalExpected = $paidTickets->sum('amount');
        $totalCollected = $paidTickets->sum('amount_paid');
        $totalOutstanding = $totalExpected - $totalCollected;
        $compValue = $tickets->where('is_complimentary', true)->count()
            * ($event->tiers->first()?->price ?? 0); // estimated value

        // Payment status breakdown
        $paymentBreakdown = [
            'completed' => $paidTickets->where('payment_status', 'completed')->count(),
            'partial'   => $paidTickets->where('payment_status', 'partial')->count(),
            'pending'   => $paidTickets->where('payment_status', 'pending')->count(),
            'refunded'  => $paidTickets->where('payment_status', 'refunded')->count(),
        ];

        // Per-tier revenue
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

        [$logoBase64, $logoWarning] = $this->resolveLogo($event->organization);

        $currency = config('constants.currency.symbol');

        return [
            'event'            => $event,
            'org'              => $event->organization,
            'currency'         => $currency,
            'totalExpected'    => $totalExpected,
            'totalCollected'   => $totalCollected,
            'totalOutstanding' => $totalOutstanding,
            'collectionRate'   => $totalExpected > 0
                ? round(($totalCollected / $totalExpected) * 100, 1)
                : 0,
            'compTickets'      => $tickets->where('is_complimentary', true)->count(),
            'compValue'        => $compValue,
            'paymentBreakdown' => $paymentBreakdown,
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