<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\{Organization, User};
use App\Notifications\Organizer\PayoutDetailsChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * Where VENTIQ pays the organization its online ticket money. Admins only:
 * the rest of the team sees it masked. Every change emails all admins.
 */
class PayoutController extends Controller
{
    public function edit(Request $request)
    {
        $organization = $request->attributes->get('organization');

        $canEdit = $request->user()->can('edit_organization');
        $changedBy = $organization->payout_updated_by ? User::find($organization->payout_updated_by) : null;

        return \Inertia\Inertia::render('Organizer/Payout', [
            'canEdit' => $canEdit,
            'methods' => collect(Organization::PAYOUT_METHODS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            // The rest of the team sees it masked.
            'onFile'  => $organization->hasPayoutDetails() ? [
                'summary'   => $organization->payoutSummary(masked: !$canEdit),
                'changedAt' => $organization->payout_updated_at?->format('j M Y, H:i'),
                'changedBy' => $changedBy?->name,
            ] : null,
            'details' => $canEdit ? [
                'payout_method'         => $organization->payout_method ?? 'ecocash',
                'payout_account_name'   => (string) $organization->payout_account_name,
                'payout_account_number' => (string) $organization->payout_account_number,
                'payout_bank_name'      => (string) $organization->payout_bank_name,
            ] : null,
            'meta'    => ['title' => 'Payouts | VENTIQ'],
        ]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->can('edit_organization'), 403);
        $organization = $request->attributes->get('organization');

        $data = $request->validate([
            'payout_method'         => ['required', Rule::in(array_keys(Organization::PAYOUT_METHODS))],
            'payout_account_name'   => ['required', 'string', 'max:255'],
            'payout_account_number' => ['required', 'string', 'max:60', 'regex:/^[0-9 \-]+$/'],
            'payout_bank_name'      => ['nullable', 'required_if:payout_method,bank_transfer', 'string', 'max:255'],
        ], [
            'payout_account_number.regex'  => 'Use digits only (spaces are fine).',
            'payout_bank_name.required_if' => 'Which bank is the account with?',
        ]);
        $data['payout_account_number'] = trim(preg_replace('/\s+/', ' ', $data['payout_account_number']));
        if ($data['payout_method'] !== 'bank_transfer') {
            $data['payout_bank_name'] = null;
        }

        $organization->fill($data);
        if (!$organization->isDirty()) {
            return back()->with('status', 'Nothing changed.');
        }

        $organization->forceFill(['payout_updated_at' => now(), 'payout_updated_by' => $request->user()->id])->save();

        $admins = User::where('organization_id', $organization->id)->get()->filter(fn (User $u) => $u->can('edit_organization'));
        Notification::send($admins, new PayoutDetailsChanged($organization, $request->user()));

        return redirect()->route('organizer.payout.edit')
            ->with('status', 'Saved. Your admins have been emailed about the change, and VENTIQ checks new accounts before the first payout into them.');
    }
}
