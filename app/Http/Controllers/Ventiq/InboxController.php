<?php

namespace App\Http\Controllers\Ventiq;

use App\Http\Controllers\Controller;
use App\Models\ContactInquiry;
use App\Support\Admin\AdminTable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Messages sent through the contact form: read them, reply on WhatsApp, keep notes. */
class InboxController extends Controller
{
    public const STATUSES = ['new' => 'New', 'read' => 'Read', 'replied' => 'Replied', 'archived' => 'Archived'];

    public function index(Request $request)
    {
        $table = AdminTable::for(ContactInquiry::query(), $request)
            ->search(fn ($q, $term) => $q->where('name', 'like', AdminTable::like($term))
                ->orWhere('email', 'like', AdminTable::like($term))
                ->orWhere('phone', 'like', AdminTable::like($term))
                ->orWhere('subject', 'like', AdminTable::like($term))
                ->orWhere('message', 'like', AdminTable::like($term)))
            ->sorts(['created' => 'created_at'], 'created')
            ->filters(['status' => fn ($q, $v) => $q->where('status', $v)]);

        return Inertia::render('Ventiq/Inbox', [
            'rows'     => $table->paginate()->through(fn (ContactInquiry $m) => [
                'id'       => $m->id,
                'name'     => $m->name,
                'phone'    => $m->phone,
                'email'    => $m->email,
                'subject'  => $m->subject,
                'message'  => $m->message,
                'status'   => $m->status,
                'notes'    => $m->notes,
                'whatsapp' => $m->phone ? 'https://wa.me/' . preg_replace('/\D/', '', $m->phone) . '?text=' . rawurlencode("Hi {$m->name}, this is VENTIQ replying to your message" . ($m->subject ? " about \"{$m->subject}\"" : '') . '.') : null,
                'received' => $m->created_at?->diffForHumans(),
            ]),
            'state'    => $table->state(),
            'statuses' => collect(self::STATUSES)->map(fn ($label, $value) => compact('value', 'label'))->values(),
            'unread'   => ContactInquiry::where('status', 'new')->count(),
            'meta'     => ['title' => 'Inbox | VENTIQ'],
        ]);
    }

    public function update(Request $request, ContactInquiry $inquiry)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'notes'  => ['nullable', 'string', 'max:2000'],
        ]);
        $inquiry->update($data + ($data['status'] !== 'new' ? ['handled_by' => $request->user()->id, 'handled_at' => $inquiry->handled_at ?? now()] : []));

        return back();
    }

    public function destroy(ContactInquiry $inquiry)
    {
        $inquiry->delete();

        return back()->with('status', 'Message deleted.');
    }
}
