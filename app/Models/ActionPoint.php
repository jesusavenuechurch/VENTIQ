<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something someone agreed to do in a session. Proposed by the AI from the
 * notes (or added by hand), confirmed when the report is reviewed, and
 * open until it's done: a series' next meetings show it until then.
 */
class ActionPoint extends Model
{
    public const STATUSES = ['proposed', 'open', 'done', 'dropped'];

    protected $fillable = [
        'organization_id', 'session_id', 'segment_id', 'series_id', 'task', 'owner_name', 'owner_user_id',
        'owner_client_id', 'due_date', 'status', 'source', 'done_at', 'done_in_session_id', 'done_by', 'reminded_at',
    ];

    protected $casts = [
        'due_date'    => 'date',
        'done_at'     => 'datetime',
        'reminded_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function ownerClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'owner_client_id');
    }

    /** Where a reminder goes: the team member's or the attendee's email. */
    public function ownerEmail(): ?string
    {
        return $this->ownerUser?->email ?: $this->ownerClient?->email ?: null;
    }

    public function ownerLabel(): ?string
    {
        return $this->ownerUser?->name ?: $this->ownerClient?->full_name ?: $this->owner_name;
    }

    public function isOverdue(): bool
    {
        return $this->status === 'open' && $this->due_date && $this->due_date->lt(today());
    }

    /** For pages: the point as the React pages show it. */
    public function toCard(): array
    {
        return [
            'id'        => $this->id,
            'task'      => $this->task,
            'owner'     => $this->ownerLabel(),
            'ownerKey'  => $this->owner_user_id ? "user:{$this->owner_user_id}" : ($this->owner_client_id ? "client:{$this->owner_client_id}" : ''),
            'ownerName' => $this->owner_name,
            'due'       => $this->due_date?->toDateString(),
            'dueLabel'  => $this->due_date?->format('j M'),
            'overdue'   => $this->isOverdue(),
            'status'    => $this->status,
            'source'    => $this->source,
            'session'   => $this->session ? ['id' => $this->session->id, 'title' => $this->session->resolved_title, 'date' => $this->session->date?->format('j M Y')] : null,
            'doneLabel' => $this->done_at?->format('j M'),
        ];
    }
}
