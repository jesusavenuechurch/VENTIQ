<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\Sessions\ProgrammeProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Certificates for whoever on a programme now meets its rule. Run when one
 * of its days ends, and every evening (sessions:issue-certificates) for
 * days that were only checked in to.
 */
class IssueProgrammeCertificates implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public int $programmeId) {}

    public function uniqueId(): string
    {
        return (string) $this->programmeId;
    }

    public function handle(ProgrammeProgress $progress): void
    {
        $programme = Event::find($this->programmeId);
        if ($programme?->is_programme && $programme->certificates_enabled) {
            $progress->issueDue($programme);
        }
    }
}
