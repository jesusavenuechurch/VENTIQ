<?php

namespace App\Http\Controllers;

use App\Models\Session;
use App\Models\SessionSegment;
use App\Services\AI\AIService;
use App\Services\AI\Prompts\SegmentInsightPrompt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionSegmentController extends Controller
{
    public function log(Request $request, Session $session, SessionSegment $segment)
    {
        $this->authorizeSegment($session, $segment);

        $validated = $request->validate(['text' => 'required|string|max:2000']);

        $segment->appendLogLine($validated['text']);

        return response()->json(['status' => 'ok']);
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
        }

        return response()->json([
            'status'          => 'ok',
            'next_segment_id' => $next?->id,
        ]);
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

        $validated = $request->validate(['name' => 'required|string|max:255']);

        $nextOrder = ($session->segments()->max('order') ?? -1) + 1;
        $hasActive = $session->segments()->where('status', 'active')->exists();

        $segment = $session->segments()->create([
            'presenter_name' => $validated['name'],
            'order'          => $nextOrder,
            'status'         => 'upcoming',
        ]);

        // If the session is already live and nobody's currently on stage,
        // this new presenter is the one now speaking — no separate "start" click needed.
        if ($session->status === 'active' && !$hasActive) {
            $segment->start();
        }

        return response()->json([
            'status'  => 'ok',
            'segment' => [
                'id'     => $segment->id,
                'name'   => $segment->presenter_name,
                'status' => $segment->fresh()->status,
            ],
        ]);
    }
}