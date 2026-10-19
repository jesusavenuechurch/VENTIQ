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
    $event = $this->event->load(['organization', 'tiers']);

    // Counts people, not tickets (a group ticket for 3 is 3 people), and
    // leaves out expired, cancelled and refunded tickets.
    $tickets = $event->tickets()->whereIn('status', EventFinance::LIVE_STATUSES)->get();
    $people  = fn ($set) => (int) $set->sum(fn ($t) => $t->admissions ?? 1);

    $tierBreakdown = $event->tiers->map(function ($tier) use ($tickets, $people) {
        $tierTickets = $tickets->where('event_tier_id', $tier->id);
        return [
            'name'          => $tier->tier_name,
            'price'         => $tier->price,
            'total'         => $people($tierTickets),
            'checked_in'    => (int) $tierTickets->sum('admitted_count'),
            'complimentary' => $people($tierTickets->where('is_complimentary', true)),
            'paid'          => $people($tierTickets->where('is_complimentary', false)),
        ];
    });

    $totalPeople    = $people($tickets);
    $admittedPeople = (int) $tickets->sum('admitted_count');

    // The revenue section reuses the revenue report's figures, so the two
    // documents can't disagree.
    $revenue = (new RevenueReportService($event))->buildData();

    return array_merge($revenue, [
        'totalTickets'   => $totalPeople,
        'checkedIn'      => $admittedPeople,
        'notCheckedIn'   => $totalPeople - $admittedPeople,
        'attendanceRate' => $totalPeople > 0 ? round($admittedPeople / $totalPeople * 100, 1) : 0,
        'complimentary'  => $people($tickets->where('is_complimentary', true)),
        'paid'           => $people($tickets->where('is_complimentary', false)),
        'tierBreakdown'  => $tierBreakdown,
    ]);
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
