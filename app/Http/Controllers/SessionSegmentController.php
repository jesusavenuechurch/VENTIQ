<?php

namespace App\Http\Controllers;

use App\Mail\SessionThankYouMail;
use App\Models\Participant;
use App\Models\Session;
use App\Models\SessionSegment;
use App\Services\AI\AIService;
use App\Services\AI\Prompts\SegmentInsightPrompt;
use App\Services\AttendanceCardImageService;
use App\Services\SessionQuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SessionSegmentController extends Controller
{
    public function __construct(private SessionQuotaService $quota) {}

    public function log(Request $request, Session $session, SessionSegment $segment)
    {
        $this->authorizeSegment($session, $segment);

        $validated = $request->validate(['text' => 'required|string|max:2000']);

        $segment->appendLogLine($validated['text']);

        return response()->json(['status' => 'ok']);
    }

    // "Undo" for an accidental Enter — pulls the last committed line back
    // off the record so it can be retyped.
    public function undoLog(Session $session, SessionSegment $segment)
    {
        $this->authorizeSegment($session, $segment);

        $popped = $segment->popLastLogLine();

        if (!$popped) {
            return response()->json(['status' => 'empty'], 404);
        }

        return response()->json(['status' => 'ok', 'text' => $popped['text']]);
    }

    public function finish(Session $session, SessionSegment $segment)
    {
        $this->authorizeSegment($session, $segment);

        $segment->finish();

        $next = $session->segments()
            ->where('order', '>', $segment->order)
            ->orderBy('order')
            ->first();

        if ($next) {
            $next->start();
        } else {
            $session->update(['status' => 'completed']);
            // No button, no waiting — this is what makes "Ready to Review"
            // something that's just there next time someone opens Ventiq,
            // not something they had to remember to ask for.
            $session->queueReportGeneration();
            $this->notifyParticipantsSessionEnded($session);
        }

        return response()->json([
            'status'          => 'ok',
            'next_segment_id' => $next?->id,
        ]);
    }

    // Fires the moment the session ends, not when the report is reviewed —
    // attendees shouldn't wait hours/days for a "thanks for coming." The
    // AI report notification is a separate, later touch (see
    // SessionController::markReviewed()).
    private function notifyParticipantsSessionEnded(Session $session): void
    {
        $organization = $session->organization;
        $cardService = app(AttendanceCardImageService::class);

        $participants = Participant::where('session_id', $session->id)
            ->whereNull('notified_at')
            ->with('client')
            ->get();

        foreach ($participants as $participant) {
            // Email is a base feature on every tier, never quota-gated.
            if ($participant->client?->email) {
                $cardPath = $cardService->generate($participant);
                Mail::to($participant->client->email)
                    ->send(new SessionThankYouMail($participant, $cardPath));
            }

            // No WhatsApp provider configured yet — simulate on the
            // frontend/logs so the flow is visible and testable now,
            // swap this line for a real provider call later without
            // touching anything else in this method.
            if ($participant->client?->phone) {
                if ($this->quota->hasWhatsappQuota($organization)) {
                    Log::info("[SIMULATED WHATSAPP] To {$participant->client->phone}: Thank you for attending {$session->resolved_title}. Here's your attendance card.");
                    $this->quota->consumeWhatsapp($organization);
                } else {
                    Log::info("[SIMULATED WHATSAPP SKIPPED — quota exhausted] Would have notified {$participant->client->phone}");
                }
            }

            $participant->update(['notified_at' => now()]);
        }
    }

    // Synchronous, no queue — this is the live "AI is following along"
    // call, deliberately kept separate from the heavier queued report
    // generation. A miss (no insight) is a normal, expected outcome,
    // not an error.
    public function tag(Request $request, Session $session, SessionSegment $segment, AIService $ai)
    {
        $this->authorizeSegment($session, $segment);

        $validated = $request->validate(['line' => 'required|string|max:2000']);

        $recentLines = collect($segment->raw_log ?? [])
            ->pluck('text')
            ->slice(-4, -1)
            ->implode("\n");

        $prompt = (new SegmentInsightPrompt)->with([
            'line'         => $validated['line'],
            'presenter'    => $segment->presenter_name,
            'topic'        => $segment->title,
            'recent_lines' => $recentLines,
        ]);

        $result = $ai->generate($prompt);

        if (!$result->success) {
            return response()->json(['category' => 'none']);
        }

        $parsed = $this->parseTagResponse($result->content);

        if ($parsed['category'] === 'none' || $parsed['text'] === '') {
            return response()->json(['category' => 'none']);
        }

        $segment->insights()->create([
            'category'        => $parsed['category'],
            'content'         => $parsed['text'],
            'is_ai_generated' => true,
            'display_order'   => $segment->insights()->count(),
        ]);

        return response()->json($parsed);
    }

    private function parseTagResponse(string $content): array
    {
        preg_match('/CATEGORY:\s*(\w+)/i', $content, $catMatch);
        preg_match('/TEXT:\s*(.+)/i', $content, $textMatch);

        $category = strtolower(trim($catMatch[1] ?? 'none'));
        if (!in_array($category, ['theme', 'decision', 'action', 'question'])) {
            $category = 'none';
        }

        return [
            'category' => $category,
            'text'     => trim($textMatch[1] ?? ''),
        ];
    }

    private function authorizeSegment(Session $session, SessionSegment $segment): void
    {
        abort_unless($segment->session_id === $session->id, 404);
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);
    }
   
    public function pause(Session $session, SessionSegment $segment)
    {
        $this->authorizeSegment($session, $segment);
        $segment->pause();

        return response()->json(['status' => 'ok']);
    }

    public function resume(Session $session, SessionSegment $segment)
    {
        $this->authorizeSegment($session, $segment);
        $segment->resume();

        return response()->json([
            'status' => 'ok',
            'duration_seconds' => $segment->fresh()->duration_seconds,
        ]);
    }

    public function store(Request $request, Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'nullable|string|max:255',
            'presenting' => 'nullable|boolean',
        ]);

        $nextOrder = ($session->segments()->max('order') ?? -1) + 1;
        $hasActive = $session->segments()->where('status', 'active')->exists();
        $isPresenting = array_key_exists('presenting', $validated)
            ? filter_var($validated['presenting'], FILTER_VALIDATE_BOOLEAN)
            : \App\Support\SessionType::roleIsPresenting($session->type, $validated['role'] ?? null);

        $segment = $session->segments()->create([
            'presenter_name' => $validated['name'],
            'role'           => $validated['role'] ?? null,
            'is_presenting'  => $isPresenting,
            'order'          => $nextOrder,
            'status'         => 'upcoming',
        ]);

        // Only presenting roles auto-take-the-stage when added mid-session —
        // a Secretary added mid-meeting shouldn't suddenly become "live."
        if ($isPresenting && $session->status === 'active' && !$hasActive) {
            $segment->start();
        }

        return response()->json([
            'status'  => 'ok',
            'segment' => [
                'id'            => $segment->id,
                'name'          => $segment->presenter_name,
                'role'          => $segment->role,
                'is_presenting' => $segment->is_presenting,
                'status'        => $segment->fresh()->status,
            ],
        ]);
    }

    // A lineup changes — someone drops out before their turn, or turns out
    // never to have actually presented. Only removable while there's
    // nothing captured under their name yet: once real notes exist against
    // a segment it's a source for the report, not a placeholder, and
    // deleting it would silently drop that content. Not removable while
    // actively live either — finish or skip them first, same as any other
    // segment transition.
    public function destroy(Session $session, SessionSegment $segment)
    {
        $this->authorizeSegment($session, $segment);

        abort_if($segment->status === 'active', 409, 'Finish or skip this presenter before removing them.');
        abort_unless(empty($segment->raw_log), 409, 'This presenter already has notes captured — they can\'t be removed.');

        $segment->delete();

        return response()->json(['status' => 'ok']);
    }
}