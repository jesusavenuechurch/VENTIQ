<?php
// app/Http/Controllers/OrganizationalRecordPdfController.php

namespace App\Http\Controllers;

use App\Models\OrganizationalRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class OrganizationalRecordPdfController extends Controller
{
    public function download(OrganizationalRecord $organizationalRecord): Response
    {
        $record = $organizationalRecord;

        // Gate: only org members or super admin can download
        $user = auth()->user();
        if (
            !$user->isSuperAdmin() &&
            $user->organization_id !== $record->organization_id
        ) {
            abort(403);
        }

        if ($record->status !== 'finalized') {
            abort(403, 'This record has not been finalized yet.');
        }

        $attendance = $record->event_id ? $record->attendance : null;

        $pdf = Pdf::loadView('organizational-records.pdf', [
            'record'     => $record,
            'attendance' => $attendance,
            'agenda'     => $record->agendaItems()->get(),
            'discussion' => $record->discussionPoints()->get(),
            'decisions'  => $record->decisions()->get(),
            'actions'    => $record->actionItems()->orderBy('status')->get(),
            'issues'     => $record->openIssues()->where('status', 'open')->get(),
        ])
        ->setPaper('a4', 'portrait')
        ->setOptions([
            'defaultFont' => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
        ]);

        $filename = str($record->resolved_title)
            ->slug('-')
            ->append('-')
            ->append($record->meeting_date?->format('Y-m-d') ?? now()->format('Y-m-d'))
            ->append('.pdf')
            ->toString();

        return $pdf->download($filename);
    }
}