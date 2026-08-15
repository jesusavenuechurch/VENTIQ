<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Session;
use App\Models\User;
use Illuminate\Database\Seeder;

class SessionTestSeeder extends Seeder
{
    /**
     * Laravel's seeder runner calls run() automatically via the
     * container, which auto-resolves type-hinted parameters by
     * instantiating them fresh if nothing else supplies a value —
     * so accepting Organization/User here directly is a trap: you'd
     * silently get a brand-new, unsaved Organization instead of null,
     * which passes truthy checks but has no id. Keep run() argument-free.
     */
    public function run(): void
    {
        $organization = Organization::first();

        if (!$organization) {
            $this->command?->warn('No organization found — run DemoAccountSeeder instead.');
            return;
        }

        $this->seedForOrganization($organization, $organization->members()->first());
    }

    /**
     * Call this directly (never through $this->call()) when you want
     * to target an exact org/user, e.g. from DemoAccountSeeder:
     *   (new SessionTestSeeder)->seedForOrganization($organization, $user);
     */
    public function seedForOrganization(Organization $organization, ?User $user = null): void
    {
        $user ??= $organization->members()->first();

        // ── Draft: scheduled for later, nothing captured yet ──
        Session::create([
            'organization_id' => $organization->id,
            'created_by'      => $user?->id,
            'type'            => 'meeting',
            'title'           => 'Monthly Finance Review',
            'date'            => now()->addDays(3)->toDateString(),
            'status'          => 'draft',
        ]);

        // ── Active: currently "in progress" for testing the live view ──
        $active = Session::create([
            'organization_id' => $organization->id,
            'created_by'      => $user?->id,
            'type'            => 'training',
            'title'           => 'Customer Service Fundamentals',
            'date'            => now()->toDateString(),
            'status'          => 'active',
        ]);
        $this->seedSegments($active, [
            ['Naledi Mokoena', 'Facilitator', true, 'active'],
            ['Thabo Ramaili', 'Co-Facilitator', false, 'upcoming'],
        ]);

        // ── Completed but not yet reported: for testing the AI-pending state ──
        $completed = Session::create([
            'organization_id' => $organization->id,
            'created_by'      => $user?->id,
            'type'            => 'meeting',
            'title'           => 'Board Meeting — July',
            'date'            => now()->subDay()->toDateString(),
            'status'          => 'completed',
        ]);
        $this->seedSegments($completed, [
            ['Mamello Khoele', 'Chair', true, 'completed'],
            ['Retšelisitsoe Mots\'oene', 'Treasurer', true, 'completed'],
        ]);

        // ── Reported, unopened: shows up in "Ready to Review" ──
        $this->seedReported($organization, $user, 'Procurement Workshop', now()->subDays(2), false);

        // ── Reported, already opened: shows up in "Recently Finished" ──
        $this->seedReported($organization, $user, 'Weekly Team Standup', now()->subDays(5), true);
        $this->seedReported($organization, $user, 'Risk Committee Sync', now()->subDays(9), true);

        $this->command?->info('Seeded 6 test sessions across draft/active/completed/reported states.');
    }

    private function seedSegments(Session $session, array $people): void
    {
        foreach ($people as $i => [$name, $role, $presenting, $status]) {
            $session->segments()->create([
                'presenter_name' => $name,
                'role'           => $role,
                'is_presenting'  => $presenting,
                'order'          => $i,
                'status'         => $status,
            ]);
        }
    }

    private function seedReported($organization, $user, string $title, $date, bool $opened): void
    {
        $session = Session::create([
            'organization_id'        => $organization->id,
            'created_by'             => $user?->id,
            'type'                   => 'meeting',
            'title'                  => $title,
            'date'                   => $date->toDateString(),
            'status'                 => 'reported',
            'report_last_opened_at'  => $opened ? now() : null,
            'session_report'         => $this->sampleReport($title),
        ]);

        $started = $date->copy()->setTime(9, 0);

        foreach ([['Amohelang Thoabala', 'Lead'], ['Palesa Ntsane', 'Note-taker']] as $i => [$name, $role]) {
            $segStart = $started->copy()->addMinutes($i * 30);
            $segEnd = $segStart->copy()->addMinutes(25);

            $session->segments()->create([
                'presenter_name' => $name,
                'role'           => $role,
                'is_presenting'  => $i === 0,
                'order'          => $i,
                'status'         => 'completed',
                'started_at'     => $segStart,
                'ended_at'       => $segEnd,
                'paused_seconds' => 0,
                // NOTE: structure here is a best guess at what
                // SegmentSummaryPrompt actually outputs — adjust the
                // keys to match your real prompt schema if this
                // doesn't line up with what your report view expects.
                'raw_log' => [
                    ['time' => $segStart->format('H:i:s'), 'text' => "{$name} opened with an overview of {$title}."],
                    ['time' => $segStart->copy()->addMinutes(10)->format('H:i:s'), 'text' => 'Discussion moved to open questions from the floor.'],
                    ['time' => $segStart->copy()->addMinutes(20)->format('H:i:s'), 'text' => 'Wrapped up with agreed next steps.'],
                ],
                'ai_summary' => [
                    'summary'      => "{$name} covered the main points on {$title}, prompting discussion from attendees.",
                    'action_items' => ["Follow up on items raised during {$name}'s segment"],
                    'open_issues'  => [],
                ],
            ]);
        }
    }

    private function sampleReport(string $title): string
    {
        return <<<MD
        ## SUMMARY
        The {$title} covered the agreed agenda in full. Attendance was strong and discussion stayed focused on the stated objectives.

        ## KEY_DISCUSSION_POINTS
        - Reviewed progress since the previous session
        - Identified two blockers requiring follow-up
        - Agreed on next steps and ownership

        ## DECISIONS
        - Proceed with the proposed timeline
        - Revisit budget allocation next quarter

        ## ACTION_ITEMS
        - Amohelang Thoabala: circulate updated plan by Friday
        - Palesa Ntsane: schedule the follow-up session

        ## OPEN_ISSUES
        - Vendor contract terms still pending legal review
        MD;
    }
}