<?php

namespace App\Services\Sessions;

use App\Mail\CertificateIssuedMail;
use App\Mail\ProgrammeEnrolledMail;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\Event;
use App\Models\Participant;
use App\Models\ProgrammeEnrolment;
use App\Models\Session;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * A programme as a course: who's enrolled, which of its days each person
 * came to, and their certificate. The rule is set per programme (every
 * day by default, or at least so many days); once someone meets it their
 * certificate is issued and emailed without anyone pressing a button,
 * after the day they met it (see IssueProgrammeCertificates).
 */
class ProgrammeProgress
{
    /** Someone enrolling, or being enrolled: by their email (or phone), the same person each time. */
    public function enrol(Event $programme, array $data, string $source): ProgrammeEnrolment
    {
        $client = $this->client($programme, $data);
        $enrolment = $this->enrolClient($programme, $client, $data['institution'] ?? null, $data['position'] ?? null, $source);

        if ($enrolment->wasRecentlyCreated && $source === 'self' && $client->email) {
            Mail::to($client->email)->queue(new ProgrammeEnrolledMail($enrolment));
        }

        return $enrolment;
    }

    /**
     * On the programme. Checking in to one of its days enrols too, but
     * doesn't undo a withdrawal; the organizer or the person enrolling
     * again does.
     */
    public function enrolClient(Event $programme, Client $client, ?string $institution, ?string $position, string $source): ProgrammeEnrolment
    {
        $enrolment = ProgrammeEnrolment::firstOrNew(['event_id' => $programme->id, 'client_id' => $client->id]);
        $enrolment->organization_id = $programme->organization_id;
        $enrolment->institution = $institution ?: $enrolment->institution;
        $enrolment->position = $position ?: $enrolment->position;
        if (!$enrolment->exists) {
            $enrolment->source = $source;
        } elseif ($source !== 'checkin') {
            $enrolment->withdrawn_at = null;
        }
        $enrolment->save();

        return $enrolment;
    }

    /** The programme's own enrolment link, made the first time it's asked for. */
    public function link(Event $programme): string
    {
        if (!$programme->enrol_token) {
            $programme->update(['enrol_token' => Str::random(40)]);
        }

        return route('public.programme-enrol.form', $programme->enrol_token);
    }

    /** @return Collection<int, Session> */
    public function days(Event $programme): Collection
    {
        return Session::where('event_id', $programme->id)->orderBy('date')->orderBy('start_time')->get();
    }

    /** Days to attend for a certificate: every day, or the programme's number (never more than it has). */
    public function required(Event $programme, ?Collection $days = null): ?int
    {
        $total = ($days ?? $this->days($programme))->count();
        if ($total === 0) {
            return null;
        }

        return $programme->certificate_min_days ? min($programme->certificate_min_days, $total) : $total;
    }

    /** Everyone on the programme, the days they came to, and where they stand. */
    public function board(Event $programme): array
    {
        $days = $this->days($programme);
        $required = $this->required($programme, $days);
        // A day still to come: not started, and today or later.
        $upcoming = $days->filter(fn (Session $d) => $d->status === 'draft' && $d->date && $d->date->gte(today()))->pluck('id');
        $attended = $this->attendance($days);
        $certificates = Certificate::where('event_id', $programme->id)->get()->keyBy('client_id');
        $enrolments = ProgrammeEnrolment::with('client')->where('event_id', $programme->id)->get()
            ->sortBy(fn ($e) => mb_strtolower($e->client?->full_name ?? ''))->values();

        $people = $enrolments->map(function (ProgrammeEnrolment $e) use ($days, $required, $upcoming, $attended, $certificates) {
            $came = $attended[$e->client_id] ?? collect();
            $certificate = $certificates[$e->client_id] ?? null;
            $stillToCome = $upcoming->diff($came)->count();

            return [
                'id'           => $e->id,
                'name'         => $e->client?->full_name ?? 'Unknown',
                'email'        => $e->client?->email,
                'phone'        => $e->client?->phone,
                'organization' => $e->institution,
                'position'     => $e->position,
                'source'       => $e->source,
                'days'         => $days->map(fn (Session $d) => $came->contains($d->id) ? 'came' : ($upcoming->contains($d->id) ? 'ahead' : 'missed'))->values(),
                'attended'     => $came->count(),
                'status'       => match (true) {
                    $e->isWithdrawn()                                      => 'withdrawn',
                    (bool) $certificate                                    => 'certified',
                    $required === null                                     => 'enrolled',
                    $came->count() >= $required                            => 'qualified',
                    $came->count() + $stillToCome >= $required             => 'on_track',
                    default                                                => 'short',
                },
                'certificate'  => $certificate ? ['issued' => $certificate->issued_at->format('j M Y'), 'number' => $certificate->certificate_number, 'url' => route('certificates.verify', $certificate->token)] : null,
            ];
        });

        return [
            'days'     => $days->values()->map(fn (Session $d, $i) => [
                'id'    => $d->id,
                'label' => 'Day ' . ($i + 1),
                'date'  => $d->date?->format('D j M'),
                'ahead' => $upcoming->contains($d->id),
            ])->all(),
            'required' => $required,
            'people'   => $people->all(),
            'counts'   => $people->countBy('status')->all(),
        ];
    }

    /**
     * Certificates for everyone who meets the rule and hasn't got one,
     * each emailed. Nothing when the programme doesn't give certificates.
     *
     * @return Collection<int, Certificate>
     */
    public function issueDue(Event $programme): Collection
    {
        $days = $this->days($programme);
        $required = $this->required($programme, $days);
        if (!$programme->certificates_enabled || $required === null) {
            return collect();
        }

        $attended = $this->attendance($days);
        $have = Certificate::where('event_id', $programme->id)->pluck('client_id');

        return ProgrammeEnrolment::where('event_id', $programme->id)
            ->whereNull('withdrawn_at')
            ->whereNotIn('client_id', $have)
            ->with('client')
            ->get()
            ->filter(fn (ProgrammeEnrolment $e) => ($attended[$e->client_id] ?? collect())->count() >= $required)
            ->map(function (ProgrammeEnrolment $e) use ($programme) {
                $certificate = DB::transaction(fn () => Certificate::firstOrCreate(
                    ['event_id' => $programme->id, 'client_id' => $e->client_id],
                    ['organization_id' => $programme->organization_id, 'issued_at' => now()],
                ));
                if ($certificate->wasRecentlyCreated && $e->client?->email) {
                    try {
                        Mail::to($e->client->email)->queue(new CertificateIssuedMail($certificate));
                    } catch (\Throwable $ex) {
                        Log::warning('Certificate email not queued', ['certificate' => $certificate->id, 'error' => $ex->getMessage()]);
                    }
                }

                return $certificate;
            })
            ->filter(fn (Certificate $c) => $c->wasRecentlyCreated)
            ->values();
    }

    /** @return Collection<int, Collection<int, int>> client id => the days (session ids) they checked in to */
    private function attendance(Collection $days): Collection
    {
        return Participant::whereIn('session_id', $days->pluck('id'))
            ->whereNotNull('attended_at')
            ->get(['client_id', 'session_id'])
            ->groupBy('client_id')
            ->map(fn ($rows) => $rows->pluck('session_id')->unique()->values());
    }

    /** The person by email, then phone, within the organization; made when new. */
    private function client(Event $programme, array $data): Client
    {
        $email = trim($data['email'] ?? '') ?: null;
        $phone = trim($data['phone'] ?? '') ?: null;
        $client = ($email ? Client::where('organization_id', $programme->organization_id)->where('email', $email)->first() : null)
            ?? ($phone ? Client::where('organization_id', $programme->organization_id)->where('phone', $phone)->first() : null);

        if (!$client) {
            return Client::create(['organization_id' => $programme->organization_id, 'full_name' => trim($data['full_name']), 'email' => $email, 'phone' => $phone, 'status' => 'active']);
        }

        $client->update(array_filter(['full_name' => trim($data['full_name'] ?? ''), 'email' => $client->email ?: $email, 'phone' => $phone ?? $client->phone]));

        return $client;
    }
}
