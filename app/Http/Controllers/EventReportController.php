<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\Reports\{AttendanceReportService, RegistrationSummaryService, RevenueReportService};
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;

/**
 * PDF reports for one event. Only the event's own organization (with the
 * view_reports permission) or a super admin may download them: these
 * hold attendee names, phone numbers and revenue.
 */
class EventReportController extends Controller
{
    public function revenue(Request $request, Event $event)
    {
        $this->authorizeEvent($request, $event);

        return (new RevenueReportService($event))->downloadPdf();
    }

    public function attendance(Request $request, Event $event)
    {
        $this->authorizeEvent($request, $event);

        return (new AttendanceReportService(
            $event->load(['organization', 'tiers', 'tickets.client', 'tickets.tier', 'tickets.workshopDetail'])
        ))->downloadPdf();
    }

    /** The same register as a spreadsheet, for sorting, mail merges or printing name tags. */
    public function attendanceExcel(Request $request, Event $event)
    {
        $this->authorizeEvent($request, $event);

        return (new AttendanceReportService(
            $event->load(['organization', 'tiers', 'tickets.client', 'tickets.tier', 'tickets.workshopDetail'])
        ))->downloadExcel();
    }

    public function registrationSummary(Request $request, Event $event)
    {
        $this->authorizeEvent($request, $event);

        return (new RegistrationSummaryService($event))->downloadPdf();
    }

    private function authorizeEvent(Request $request, Event $event): void
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless($event->organization_id === CurrentOrganization::id() && $user->can('view_reports'), 404);
    }
}
