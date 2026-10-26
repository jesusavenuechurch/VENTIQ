<?php

namespace App\Services\Sessions;

use App\Mail\SessionPlanRenewalMail;
use App\Models\Organization;
use App\Models\PaymentSession;
use App\Models\SessionPackage;
use App\Services\SessionQuotaService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Team and Business plans are paid month by month. A few days before a
 * month ends the Admins are asked to renew; an unpaid new month runs for
 * SessionPackage::GRACE_DAYS days (longer while a bank transfer they've
 * told us about waits to be confirmed), then the plan goes back to Free.
 * Nothing is deleted. Paying again for the plan adds the month
 * (SessionPackageService::changePlan).
 */
class PlanRenewals
{
    /** Days before the month ends that the Admins are asked to renew. */
    public const NOTICE_DAYS = 3;

    /** Past the grace, unpaid: the plan is ended and the organization is on Free. */
    public function lapseIfOverdue(SessionPackage $plan): ?SessionPackage
    {
        if (!$plan->renews() || $plan->status !== 'active' || $plan->isPaid() || now()->lt($plan->graceEndsAt())) {
            return null;
        }
        if ($this->transferWaiting($plan->organization_id)) {
            return null;
        }

        $plan->update(['status' => 'lapsed']);
        $free = SessionPackage::createFreePackage($plan->organization_id);
        $this->tell($plan, 'lapsed');
        Log::info('Sessions plan lapsed to Free', ['organization' => $plan->organization_id, 'plan' => $plan->id, 'tier' => $plan->tier]);

        return $free;
    }

    /** The daily run: months rolled over, plans past their grace ended, and the notices sent. */
    public function run(): array
    {
        $done = ['due' => 0, 'grace' => 0, 'lapsed' => 0];
        $quota = app(SessionQuotaService::class);

        $organizations = SessionPackage::where('status', 'active')->whereIn('tier', SessionPackage::RENEWING)->whereNotNull('paid_until')
            ->distinct()->pluck('organization_id');
        foreach (Organization::whereIn('id', $organizations)->get() as $organization) {
            $plan = $quota->currentPlan($organization);
            if (!$plan->renews()) {
                $done['lapsed']++;
                continue;
            }

            // In its grace: once for the month.
            if (!$plan->isPaid() && !$plan->grace_notice_for?->isSameDay($plan->period_start)) {
                $plan->update(['grace_notice_for' => $plan->period_start]);
                $this->tell($plan, 'grace');
                $done['grace']++;
            // Paid for this month, not the next, and the month ends soon.
            } elseif ($plan->nextUnpaid() && $this->endsSoon($plan)
                && !$plan->due_notice_for?->isSameDay($plan->period_end)) {
                $plan->update(['due_notice_for' => $plan->period_end]);
                $this->tell($plan, 'due');
                $done['due']++;
            }
        }

        return $done;
    }

    /** A bank transfer for a plan, told us about and not yet confirmed or rejected. */
    public function transferWaiting(int $organizationId): bool
    {
        return PaymentSession::where('organization_id', $organizationId)->where('gateway', BankTransfers::GATEWAY)
            ->where('payable_type', 'session_package')->where('status', 'pending')
            ->where('purchase_meta->type', 'plan')->exists();
    }

    /** What the plan page shows about paying for the next month, or null when there's nothing to do. */
    public function status(SessionPackage $plan): ?array
    {
        if (!$plan->renews()) {
            return null;
        }
        $waiting = $this->transferWaiting($plan->organization_id);
        if (!$plan->isPaid()) {
            return ['stage' => 'grace', 'by' => $plan->graceEndsAt()->format('j M Y'), 'waiting' => $waiting];
        }
        if ($plan->nextUnpaid() && $this->endsSoon($plan)) {
            return ['stage' => 'due', 'by' => $plan->period_end->format('j M Y'), 'waiting' => $waiting];
        }

        return ['stage' => 'paid', 'by' => $plan->paid_until->format('j M Y'), 'waiting' => false];
    }

    /** The month ends within NOTICE_DAYS days (by the calendar, not the hour). */
    private function endsSoon(SessionPackage $plan): bool
    {
        return now()->addDays(self::NOTICE_DAYS)->toDateString() >= $plan->period_end->toDateString();
    }

    private function tell(SessionPackage $plan, string $stage): void
    {
        $organization = Organization::find($plan->organization_id);
        $to = CorporateBilling::recipientsOf($organization);
        if ($to !== []) {
            Mail::to($to)->queue(new SessionPlanRenewalMail($plan, $organization, $stage));
        }
    }
}
