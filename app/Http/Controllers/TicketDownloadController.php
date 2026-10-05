<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TicketDownloadController extends Controller
{
    /**
     * Show ticket download page
     */
    public function show($qrCode)
    {
        $ticket = Ticket::where('qr_code', $qrCode)
            ->with(['client', 'event', 'tier'])
            ->firstOrFail();

        // Attendees get their ticket at registration, before it's paid for:
        // an inactive, expired or cancelled ticket still has a page, which
        // says plainly that it won't scan yet (or any more) and what to do.
        if (!in_array($ticket->status, ['active', 'checked_in'], true)) {
            $ticket->loadMissing(['event.organization', 'payments']);

            return view('tickets.inactive', [
                'ticket'     => $ticket,
                'submitted'  => $ticket->payments->contains(fn ($p) => $p->status === 'pending' && $p->submitted_at)
                    || \App\Models\PaymentSession::where('payable_type', 'ticket')->where('payable_id', $ticket->id)
                        ->where('status', 'pending')->where('created_at', '>', now()->subDay())->exists(),
                'paymentUrl' => route('ticket.pay', $ticket->qr_code),
            ]);
        }

            // Generate QR code if not exists (on-demand)
        if (!$ticket->qr_code_path) {
            try {
                $ticket->generateQrCode();
            } catch (\Exception $e) {
                \Log::error("Failed to generate QR on-demand: {$e->getMessage()}");
                // Continue anyway - show page without QR
            }
        }

        // Generate avatar if not exists
        if (!$ticket->avatar_path) {
            try {
                $ticket->generateAvatar();
            } catch (\Exception $e) {
                \Log::error("Failed to generate avatar on-demand: {$e->getMessage()}");
                // Continue anyway
            }
        }

        return view('tickets.download', [
            'ticket' => $ticket,
            'qrCode' => $qrCode,
        ]);
    }

    /**
     * Update print preference
     */
    public function updatePreference(Request $request, $qrCode)
    {
        $ticket = Ticket::where('qr_code', $qrCode)->firstOrFail();

        // Check if already printed - can't change preference
        if ($ticket->isPrinted()) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket has already been printed. Cannot change preference.',
            ], 403);
        }

        $preference = $request->input('preference');

        // Validate preference
        if (!in_array($preference, ['digital', 'print'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid preference',
            ], 422);
        }

        $ticket->update(['ticket_preference' => $preference]);

        return response()->json([
            'success' => true,
            'message' => 'Preference updated',
            'preference' => $preference,
        ]);
    }

    /**
     * Download avatar PDF
     */
    public function download($qrCode)
    {
        $ticket = Ticket::where('qr_code', $qrCode)->firstOrFail();

        // A pass is only handed out for a ticket that will scan.
        abort_unless(in_array($ticket->status, ['active', 'checked_in'], true), 404);
        if (!$ticket->avatar_path || !Storage::disk(Ticket::FILES_DISK)->exists($ticket->avatar_path)) {
            abort_unless($ticket->generateAvatar(), 404, 'Ticket not ready for download');
        }

        return Storage::disk(Ticket::FILES_DISK)->download($ticket->avatar_path, "ticket_{$ticket->ticket_number}.pdf", ['Cache-Control' => 'private, no-store']);
    }

    /** The ticket's QR image, for its own page and messages. */
    public function qr($qrCode)
    {
        $ticket = Ticket::with('event', 'tier')->where('qr_code', $qrCode)->firstOrFail();

        abort_unless(in_array($ticket->status, ['active', 'checked_in'], true), 404);
        if ((!$ticket->qr_code_path || !Storage::disk(Ticket::FILES_DISK)->exists($ticket->qr_code_path)) && !$ticket->generateQrCode()) {
            // PNGs need imagick; an SVG doesn't, so the ticket still shows a code.
            $svg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(300)->margin(2)->generate(route('ticket.download', $ticket->qr_code));

            return response((string) $svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, max-age=3600']);
        }

        return Storage::disk(Ticket::FILES_DISK)->response($ticket->qr_code_path, null, ['Cache-Control' => 'private, max-age=3600']);
    }
}

