<?php

namespace App\Services\Sessions;

use App\Mail\SessionPilotMail;
use App\Models\SessionAgreement;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Pilots (corporate rates at M0) run for three months. A week before one
 * ends VENTIQ is told, to renew it or agree a rate; on its last day it
 * ends by itself, and the organization's Admins and VENTIQ are told.
 * The organization is back on its plan. Nothing is deleted.
 */
class Pilots
{
    /** @return array{ending: int, ended: int} */
    public function run(): array
    {
        $done = ['ending' => 0, 'ended' => 0];
        $pilots = SessionAgreement::with('organization')->where('rate', 0)->whereNull('ended_at')->whereNotNull('ends_on')->get();

        foreach ($pilots as $pilot) {
            if ($pilot->ends_on->lt(today())) {
                $pilot->update(['ended_at' => now()]);
                $to = array_values(array_unique(array_merge(CorporateBilling::recipientsOf($pilot->organization), [config('ventiq.billing.email')])));
                Mail::to(array_filter($to))->queue(new SessionPilotMail($pilot, 'ended'));
                Log::info('Sessions pilot ended', ['organization' => $pilot->organization_id, 'agreement' => $pilot->id]);
                $done['ended']++;
            } elseif (!$pilot->end_notice_at && today()->addDays(SessionAgreement::PILOT_NOTICE_DAYS)->gte($pilot->ends_on)) {
                $pilot->update(['end_notice_at' => now()]);
                Mail::to(config('ventiq.billing.email'))->queue(new SessionPilotMail($pilot, 'ending'));
                $done['ending']++;
            }
        }

        return $done;
    }
}
