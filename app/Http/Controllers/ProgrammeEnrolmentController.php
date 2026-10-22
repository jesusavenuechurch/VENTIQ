<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\ProgrammeEnrolment;
use App\Services\Sessions\ProgrammeProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Who's on a programme, from the organizer's side: adding people, a pasted list, withdrawing, and the certificate rule. */
class ProgrammeEnrolmentController extends Controller
{
    public function __construct(private ProgrammeProgress $progress) {}

    public function store(Request $request, Event $programme)
    {
        $this->authorizeProgramme($programme);
        $data = $request->validate([
            'full_name'   => 'required|string|max:255',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'nullable|string|max:20',
            'institution' => 'nullable|string|max:255',
            'position'    => 'nullable|string|max:255',
        ]);

        $this->progress->enrol($programme, $data, 'desk');

        return back()->with('status', "{$data['full_name']} is enrolled.");
    }

    /** A list pasted from a spreadsheet or typed: one person a line. */
    public function import(Request $request, Event $programme)
    {
        $this->authorizeProgramme($programme);
        $request->validate(['lines' => 'required|string|max:100000'], ['lines.required' => 'Paste at least one person.']);

        $people = collect(preg_split('/\R/', $request->input('lines')))->map(fn ($l) => self::parseLine($l))->filter();
        abort_if($people->count() > 1000, 422, 'That is more than 1000 people. Split the list.');
        $people->each(fn ($p) => $this->progress->enrol($programme, $p, 'desk'));

        return back()->with('status', $people->count() === 1 ? '1 person enrolled.' : "{$people->count()} people enrolled.");
    }

    /**
     * "Mpho Sello, mpho@example.com, 5800 0000, Queen II Hospital, Nurse",
     * by commas, tabs or semicolons, in any order for the email and phone:
     * the first other part is the name, then the organisation, then the
     * position.
     */
    public static function parseLine(string $line): ?array
    {
        $parts = collect(preg_split('/\t|;|,/', $line))->map(fn ($p) => trim($p))->filter(fn ($p) => $p !== '')->values();
        if ($parts->isEmpty()) {
            return null;
        }
        $email = $parts->first(fn ($p) => filter_var($p, FILTER_VALIDATE_EMAIL));
        $phone = $parts->first(fn ($p) => $p !== $email && preg_match('/^\+?[\d\s()-]{7,20}$/', $p));
        $rest = $parts->reject(fn ($p) => $p === $email || $p === $phone)->values();
        if (!isset($rest[0])) {
            return null;
        }

        return [
            'full_name'   => mb_substr($rest[0], 0, 255),
            'email'       => $email,
            'phone'       => $phone ? mb_substr(preg_replace('/\s+/', ' ', $phone), 0, 20) : null,
            'institution' => isset($rest[1]) ? mb_substr($rest[1], 0, 255) : null,
            'position'    => isset($rest[2]) ? mb_substr($rest[2], 0, 255) : null,
        ];
    }

    public function withdraw(Event $programme, ProgrammeEnrolment $enrolment)
    {
        $this->authorizeEnrolment($programme, $enrolment);
        $enrolment->update(['withdrawn_at' => now()]);

        return back()->with('status', "{$enrolment->client?->full_name} is off the programme. Their attendance stays.");
    }

    public function restore(Event $programme, ProgrammeEnrolment $enrolment)
    {
        $this->authorizeEnrolment($programme, $enrolment);
        $enrolment->update(['withdrawn_at' => null]);

        return back()->with('status', "{$enrolment->client?->full_name} is back on the programme.");
    }

    /** The certificate rule and whether the enrolment link takes people. */
    public function settings(Request $request, Event $programme)
    {
        $this->authorizeProgramme($programme);
        $data = $request->validate([
            'certificates_enabled' => 'sometimes|boolean',
            'certificate_min_days' => 'sometimes|nullable|integer|min:1|max:365',
            'enrolment_open'       => 'sometimes|boolean',
        ]);
        $programme->update($data);

        // A looser rule may mean some people qualify now.
        $issued = $this->progress->issueDue($programme)->count();

        return back()->with('status', $issued ? "Saved. {$issued} " . ($issued === 1 ? 'certificate was' : 'certificates were') . ' issued to people who now meet it.' : 'Saved.');
    }

    private function authorizeProgramme(Event $programme): void
    {
        abort_unless($programme->organization_id === Auth::user()->organization_id, 403);
        abort_unless($programme->is_programme, 404);
    }

    private function authorizeEnrolment(Event $programme, ProgrammeEnrolment $enrolment): void
    {
        $this->authorizeProgramme($programme);
        abort_unless($enrolment->event_id === $programme->id, 404);
    }
}
