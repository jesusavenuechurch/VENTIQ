<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\OrganizationPaymentMethod;
use App\Services\Payments\PaymentAccountService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The organization's own accounts attendees can pay into: several per
 * method, one default each for new events. The rules (an account with
 * payments keeps its number and can't be deleted; one default per
 * method) live on the model, so this only shapes the requests.
 */
class PaymentAccountsController extends Controller
{
    /** Methods an organizer account can be; online is VENTIQ's, not theirs. */
    public const METHODS = ['ecocash', 'mpesa', 'bank_transfer', 'cash'];

    public function __construct(private PaymentAccountService $accounts) {}

    public function index(Request $request)
    {
        $organization = $request->attributes->get('organization');

        $accounts = OrganizationPaymentMethod::where('organization_id', $organization->id)
            ->where('payment_method', '!=', 'online')
            ->orderByDesc('is_active')->orderBy('payment_method')->orderBy('display_order')->orderBy('id')
            ->get()
            ->each(fn ($account) => $account->setAttribute('has_payments', $this->accounts->hasPayments($account)));

        return view('organizer.accounts', [
            'accounts' => $accounts,
            'methods'  => collect(self::METHODS)->mapWithKeys(fn ($m) => [$m => config("constants.payment_methods.{$m}.label", ucfirst($m))]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        OrganizationPaymentMethod::create($data + [
            'organization_id' => $request->attributes->get('organization')->id,
            'is_active'       => true,
        ]);

        return back()->with('status', 'Account added.');
    }

    public function update(Request $request, OrganizationPaymentMethod $account)
    {
        $this->authorizeAccount($request, $account);

        $data = $this->validated($request, $account);

        // The method and number of an account that has received payments
        // can't change (the model refuses); only its name and instructions.
        if ($this->accounts->hasPayments($account)) {
            unset($data['payment_method'], $data['account_number']);
        }

        $account->update($data);

        return back()->with('status', 'Account updated.');
    }

    public function makeDefault(Request $request, OrganizationPaymentMethod $account)
    {
        $this->authorizeAccount($request, $account);

        $account->update(['is_default' => true, 'is_active' => true]);

        return back()->with('status', "{$account->display_label} is now the default for new events.");
    }

    public function toggle(Request $request, OrganizationPaymentMethod $account)
    {
        $this->authorizeAccount($request, $account);

        $account->update(['is_active' => !$account->is_active, 'is_default' => $account->is_active ? false : $account->is_default]);

        return back()->with('status', $account->is_active
            ? "{$account->display_label} is switched on again."
            : "{$account->display_label} is switched off: attendees can no longer choose it. Payments already made to it are kept.");
    }

    public function destroy(Request $request, OrganizationPaymentMethod $account)
    {
        $this->authorizeAccount($request, $account);

        if ($this->accounts->hasPayments($account)) {
            return back()->with('status', 'This account has received payments, so it can only be switched off.');
        }

        $account->delete();

        return back()->with('status', 'Account removed.');
    }

    private function validated(Request $request, ?OrganizationPaymentMethod $account = null): array
    {
        $method = $request->input('payment_method', $account?->payment_method);
        $needsNumber = (bool) config("constants.payment_methods.{$method}.requires_account", false);

        return $request->validate([
            'payment_method' => [$account ? 'sometimes' : 'required', Rule::in(self::METHODS)],
            'account_name'   => 'nullable|string|max:255',
            'account_number' => [$needsNumber && !($account && $this->accounts->hasPayments($account)) ? 'required' : 'nullable', 'string', 'max:255'],
            'instructions'   => 'nullable|string|max:1000',
        ], [
            'account_number.required' => 'Enter the number attendees should pay to.',
        ]);
    }

    private function authorizeAccount(Request $request, OrganizationPaymentMethod $account): void
    {
        abort_unless(
            $account->organization_id === $request->attributes->get('organization')->id && $account->payment_method !== 'online',
            404,
        );
    }
}
