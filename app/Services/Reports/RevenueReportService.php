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
        $event = $this->event->load(['organization', 'tiers']);

        // Expired, cancelled and refunded tickets aren't sales; money is
        // split by who collected it (see EventFinance).
        $finance = EventFinance::for($event);
        $summary = $finance->summary();

        $paidTickets = $event->tickets()
            ->whereIn('status', EventFinance::LIVE_STATUSES)
            ->where('is_complimentary', false)
            ->get();

        $totalExpected    = $summary['expected'];
        $totalCollected   = $summary['collected'];
        $totalOutstanding = $summary['outstanding'];
        $compValue = $summary['comp_tickets'] * ($event->tiers->first()?->price ?? 0); // estimated value

        $paymentBreakdown = [
            'completed' => $paidTickets->where('payment_status', 'completed')->count(),
            'partial'   => $paidTickets->where('payment_status', 'partial')->count(),
            'pending'   => $paidTickets->where('payment_status', 'pending')->count(),
            'refunded'  => $event->tickets()->where('payment_status', 'refunded')->count(),
        ];

        $tierRevenue = $finance->byTier()->map(fn ($tier) => $tier + ['sold' => $tier['tickets']]);

        [$logoBase64, $logoWarning] = $this->resolveLogo($event->organization);

        $currency = config('constants.currency.symbol');

        return [
            'event'            => $event,
            'org'              => $event->organization,
            'currency'         => $currency,
            'totalExpected'    => $totalExpected,
            'totalCollected'   => $totalCollected,
            'totalOutstanding' => $totalOutstanding,
            'collectionRate'   => $summary['collection_rate'],
            'compTickets'      => $summary['comp_tickets'],
            'finance'          => $summary,
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