<?php

namespace App\Services\Assist;

use App\Jobs\GenerateAssistReply;
use App\Models\{AiGenerationResult, AssistConversation, AssistMessage, User};
use Illuminate\Support\Str;

/**
 * Ask Ventiq: a user's conversation about their organization's sessions,
 * answered from its own records (App\Services\Memory). Used by the React
 * panel (AssistController). Only for people whose role reads reports.
 */
class AssistChat
{
    // How long a reply may stay "thinking" before it's given up on. The
    // job's own retries only help a job that runs and fails; a worker that
    // dies or never picks it up would otherwise leave it pending forever.
    private const STALE_AFTER_SECONDS = 270;

    /** The user's latest conversation, or a new one. */
    public function conversation(User $user): AssistConversation
    {
        return AssistConversation::where('organization_id', $user->organization_id)
            ->where('user_id', $user->id)
            ->latest()
            ->first() ?? AssistConversation::create([
                'organization_id' => $user->organization_id,
                'user_id'         => $user->id,
            ]);
    }

    /** Ask: the question appears at once, the answer is worked out in the background. */
    public function ask(AssistConversation $conversation, User $user, string $text): void
    {
        AssistMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => $text,
            'status'          => 'completed',
        ]);

        if (!$conversation->title) {
            $conversation->update(['title' => Str::limit($text, 40)]);
        }

        // Reading the organization's records is what a role that reads reports may do.
        if (!\App\Support\SessionsAccess::can($user, 'view')) {
            AssistMessage::create([
                'conversation_id' => $conversation->id,
                'role'            => 'assistant',
                'content'         => "Your role on the team doesn't include reading reports, so I can't search them for you. Ask an Admin if you need it.",
                'status'          => 'completed',
            ]);

            return;
        }

        $jobId = (string) Str::uuid();
        $reply = AssistMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'assistant',
            'content'         => null,
            'status'          => 'pending',
            'job_id'          => $jobId,
        ]);

        AiGenerationResult::create(['job_id' => $jobId, 'user_id' => $user->id, 'type' => 'assist_chat', 'status' => 'pending']);

        GenerateAssistReply::dispatch(jobId: $jobId, messageId: $reply->id, userText: $text, organizationId: $user->organization_id);
    }

    /** Pick up answers that have finished, and give up on any stuck too long. */
    public function refresh(AssistConversation $conversation): void
    {
        foreach ($conversation->messages()->where('status', 'pending')->get() as $msg) {
            $result = AiGenerationResult::where('job_id', $msg->job_id)->first();

            if ($result?->status === 'completed') {
                $msg->update(['content' => $result->result, 'status' => 'completed']);
            } elseif ($result?->status === 'failed') {
                $msg->update(['content' => $result->error ?? 'Something went wrong.', 'status' => 'failed']);
            } elseif ($msg->created_at->diffInSeconds(now()) >= self::STALE_AFTER_SECONDS) {
                $msg->update(['status' => 'failed', 'content' => 'That took longer than expected. Please try asking again.']);
            }
        }

        $conversation->refresh();
    }
}
