<?php

namespace App\Services\Reports;

use App\Models\Event;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AttendanceRegisterExport;

class AttendanceReportService
{
    public function __construct(protected Event $event) {}

    // ── PDF ───────────────────────────────────────────────────────────────────

    public function downloadPdf(): \Symfony\Component\HttpFoundation\Response
    {
        $data = $this->buildData();

        $pdf = Pdf::loadView('reports.attendance-register', $data)
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'defaultFont'          => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => false,
                'dpi'                  => 150,
            ]);

        $filename = $this->sluggedFilename('attendance-register', 'pdf');

        return $pdf->download($filename);
    }

    // ── Excel ─────────────────────────────────────────────────────────────────

    public function downloadExcel(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $filename = $this->sluggedFilename('attendance-register', 'xlsx');

        return Excel::download(
            new AttendanceRegisterExport($this->event),
            $filename
        );
    }

    // ── Data builder ──────────────────────────────────────────────────────────

    public function buildData(): array
    {
        $event = $this->event->load([
            'organization',
            'tiers',
            'tickets.client',
            'tickets.tier',
            'tickets.workshopDetail',
        ]);

        $tickets = $event->tickets()
            ->with(['client', 'tier', 'workshopDetail'])
            ->orderBy('checked_in_at')
            ->get();

        $checkedIn  = $tickets->whereNotNull('checked_in_at');
        $notCheckedIn = $tickets->whereNull('checked_in_at');

        // Organization logo — base64 for PDF embedding
        $logoBase64 = null;
        $logoWarning = false;

        if ($event->organization->logo_path) {
            $path = storage_path('app/public/' . $event->organization->logo_path);
            if (file_exists($path)) {
                $type = pathinfo($path, PATHINFO_EXTENSION);
                $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode(file_get_contents($path));
            } else {
                $logoWarning = true;
                Log::warning("Logo not found for org {$event->organization->id}: {$path}");
            }
        } else {
            $logoWarning = true;
        }

        return [
            'event'        => $event,
            'org'          => $event->organization,
            'tickets'      => $tickets,
            'checkedIn'    => $checkedIn,
            'notCheckedIn' => $notCheckedIn,
            'totalCount'   => $tickets->count(),
            'checkedCount' => $checkedIn->count(),
            'signedCount'  => $checkedIn->filter(fn ($t) => $t->workshopDetail?->isSigned())->count(),
            'isWorkshop'   => $event->isWorkshop(),
            'logoBase64'   => $logoBase64,
            'logoWarning'  => $logoWarning,
            'generatedAt'  => now()->format('d M Y, H:i'),
            'generatedBy'  => auth()->user()?->name ?? 'System',
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function sluggedFilename(string $type, string $ext): string
    {
        return implode('-', array_filter([
            str($this->event->name)->slug()->toString(),
            $this->event->event_date?->format('Y-m-d'),
            $type,
        ])) . '.' . $ext;
    }
}