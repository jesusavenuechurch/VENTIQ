<?php

namespace App\Exports;

use App\Models\Event;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class AttendanceRegisterExport implements
    FromCollection,
    WithHeadings,
    WithStyles,
    WithColumnWidths,
    WithTitle
{
    public function __construct(protected Event $event) {}

    public function title(): string
    {
        return 'Attendance Register';
    }

    public function headings(): array
    {
        $base = [
            '#',
            'Full Name',
            'Phone',
            'Email',
            'Ticket Number',
            'Voucher Code',
            'Ticket Tier',
            'Check-in Status',
            'Check-in Time',
        ];

        // Workshop-specific columns
        if ($this->event->isWorkshop()) {
            $base = array_merge($base, [
                'Position / Title',
                'Institution',
                'District',
                'Signature Status',
                'Signed At',
            ]);
        }

        return $base;
    }

    public function collection(): Collection
    {
        $tickets = $this->event->tickets()
            ->with(['client', 'tier', 'workshopDetail'])
            ->orderByRaw("CASE WHEN checked_in_at IS NULL THEN 1 ELSE 0 END")
            ->orderBy('checked_in_at')
            ->get();

        return $tickets->map(function ($ticket, $index) {
            $row = [
                $index + 1,
                $ticket->client->full_name,
                $ticket->client->phone ?? '—',
                $ticket->client->email ?? '—',
                $ticket->ticket_number,
                $ticket->voucher_code ?? '—',
                $ticket->tier->tier_name,
                $ticket->checked_in_at ? 'Checked In' : 'Not Checked In',
                $ticket->checked_in_at
                    ? $ticket->checked_in_at->format('d M Y H:i')
                    : '—',
            ];

            if ($this->event->isWorkshop()) {
                $detail = $ticket->workshopDetail;
                $row = array_merge($row, [
                    $detail?->position      ?? '—',
                    $detail?->institution   ?? '—',
                    $detail?->district_label ?? '—',
                    $detail?->status_label  ?? 'Awaiting Signature',
                    $detail?->signed_at?->format('d M Y H:i') ?? '—',
                ]);
            }

            return $row;
        });
    }

    public function columnWidths(): array
    {
        $base = [
            'A' => 5,   // #
            'B' => 28,  // Full Name
            'C' => 16,  // Phone
            'D' => 28,  // Email
            'E' => 22,  // Ticket Number
            'F' => 14,  // Voucher Code
            'G' => 18,  // Tier
            'H' => 16,  // Check-in Status
            'I' => 20,  // Check-in Time
        ];

        if ($this->event->isWorkshop()) {
            $base = array_merge($base, [
                'J' => 22,  // Position
                'K' => 28,  // Institution
                'L' => 16,  // District
                'M' => 18,  // Signature Status
                'N' => 20,  // Signed At
            ]);
        }

        return $base;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastCol = $this->event->isWorkshop() ? 'N' : 'I';
        $lastRow = $this->event->tickets()->count() + 1;

        // Header row styling
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 10,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1D4069'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
        ]);

        // Data rows — alternating background
        for ($row = 2; $row <= $lastRow; $row++) {
            $color = $row % 2 === 0 ? 'F8FAFC' : 'FFFFFF';
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $color],
                ],
                'font'      => ['size' => 9],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
        }

        // Borders on all cells
        $sheet->getStyle("A1:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => 'E2E8F0'],
                ],
            ],
        ]);

        // Freeze header row
        $sheet->freezePane('A2');

        // Row height
        $sheet->getRowDimension(1)->setRowHeight(22);

        return [];
    }
}