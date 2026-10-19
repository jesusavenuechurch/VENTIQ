{{--
    What attendees of this event will be offered, in the same two groups the
    payment page uses. Inputs: $onlineMethods (driver keys, empty when online
    is off) and $accounts (OrganizationPaymentMethod collection).
--}}
@if(empty($onlineMethods) && $accounts->isEmpty())
    <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
        ⚠️ Choose at least one way to pay, or attendees won't be able to pay for their tickets.
    </div>
@else
    <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-800 space-y-3">
        <p class="font-semibold">Your attendees will be offered:</p>

        @if(!empty($onlineMethods))
            <div>
                <p class="font-semibold">Pay online</p>
                <ul class="list-disc ml-5">
                    @foreach($onlineMethods as $method)
                        <li>{{ config("constants.payment_methods.{$method}.label", ucfirst($method)) }} via VENTIQ</li>
                    @endforeach
                </ul>
                <p class="text-xs text-gray-500 mt-1">VENTIQ collects the payment and the ticket activates automatically.</p>
            </div>
        @endif

        @if($accounts->isNotEmpty())
            <div>
                <p class="font-semibold">Pay directly to you</p>
                <ul class="list-disc ml-5">
                    @foreach($accounts as $account)
                        <li>{{ $account->display_label }}@if($account->account_number) — {{ $account->account_number }}@endif</li>
                    @endforeach
                </ul>
                <p class="text-xs text-gray-500 mt-1">You receive the money and confirm each payment before the ticket activates.</p>
            </div>
        @endif
    </div>
@endif
