<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** The organizer's event form, for both creating and editing. */
class OrganizerEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the organizer middleware and controller scope the event
    }

    protected function prepareForValidation(): void
    {
        // Checkboxes that are unticked aren't sent at all.
        // Online methods the event offers; a plain "online" (older form)
        // means every method that's live.
        $methods = $this->has('online_methods')
            ? array_values(array_intersect(array_keys(\App\Services\Payments\PaymentGatewayFactory::CATALOG), (array) $this->input('online_methods')))
            : ($this->boolean('online') ? \App\Services\Payments\PaymentGatewayFactory::enabledMethods() : []);

        $this->merge([
            'is_public'          => $this->boolean('is_public'),
            'online_methods'     => $methods,
            'online'             => !empty($methods),
            'allow_installments' => $this->boolean('allow_installments'),
            'tiers'              => collect($this->input('tiers', []))
                ->map(fn ($tier) => array_merge($tier, ['is_active' => filter_var($tier['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN)]))
                ->values()
                ->all(),
        ]);
    }

    public function rules(): array
    {
        $editing = $this->route('event') !== null;

        return [
            'name'                       => 'required|string|max:255',
            'tagline'                    => 'nullable|string|max:255',
            'description'                => 'nullable|string|max:5000',
            'category'                   => ['required', Rule::in(array_keys(config('constants.categories')))],
            'city'                       => ['required', Rule::in(config('constants.districts'))],
            'banner'                     => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
            'is_public'                  => 'boolean',
            'event_date'                 => $editing ? 'required|date' : 'required|date|after_or_equal:today',
            'event_time'                 => 'required|date_format:H:i',
            'registration_deadline_date' => 'nullable|date',
            'registration_deadline_time' => 'nullable|date_format:H:i',
            'venue'                      => 'nullable|string|max:255',
            'capacity'                   => 'nullable|integer|min:1',
            'location'                   => 'nullable|string|max:1000',

            'payment_mode'               => [$editing ? 'sometimes' : 'required', Rule::in(['free', 'paid'])],
            'online'                     => 'boolean',
            'online_methods'             => ['array'],
            'online_methods.*'           => [\Illuminate\Validation\Rule::in(\App\Services\Payments\PaymentGatewayFactory::enabledMethods())],
            'account_ids'                => 'array',
            'account_ids.*'              => 'integer',
            'allow_installments'         => 'boolean',
            'minimum_deposit_percentage' => 'nullable|numeric|min:1|max:100',
            'installment_instructions'   => 'nullable|string|max:1000',
            'payment_window_hours'       => 'nullable|integer|min:1|max:720',

            'tiers'                          => 'array',
            'tiers.*.id'                     => 'nullable|integer',
            'tiers.*.tier_name'              => 'required|string|max:255|distinct:ignore_case',
            'tiers.*.price'                  => 'required|numeric|min:0',
            'tiers.*.quantity_available'     => 'required|integer|min:1|max:100000',
            // Free events: how many people they expect (the Free ticket type's number).
            'expected_attendance'            => [Rule::requiredIf(fn () => $this->paymentMode() === 'free'), 'nullable', 'integer', 'min:1', 'max:100000'],
            'tiers.*.description'            => 'nullable|string|max:1000',
            'tiers.*.quantity_per_purchase'  => 'nullable|integer|min:1|max:100',
            'tiers.*.color'                  => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'tiers.*.is_active'              => 'boolean',

            'status' => ['required', Rule::in($editing ? array_keys(config('constants.event_statuses')) : ['draft', 'published'])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $this->checkNumbersAboveBooked($validator);

            if ($this->paymentMode() !== 'paid') {
                return;
            }

            if (empty($this->input('tiers'))) {
                $validator->errors()->add('tiers', 'Add at least one ticket type.');
            }

            if (!$this->boolean('online') && empty($this->input('account_ids'))) {
                $validator->errors()->add('account_ids', 'Choose at least one way for attendees to pay.');
            }
        }];
    }

    /** A ticket type's number can't go below the places already taken. */
    private function checkNumbersAboveBooked(Validator $validator): void
    {
        $event = $this->route('event');
        if (!$event) {
            return;
        }

        $tiers = $event->tiers()->get()->keyBy('id');
        $check = function (?\App\Models\EventTier $tier, $number, string $field) use ($validator) {
            $taken = $tier ? \App\Support\TierCapacity::taken($tier) : 0;
            if ($tier && is_numeric($number) && (int) $number < $taken) {
                $validator->errors()->add($field, "{$taken} {$tier->tier_name} " . ($taken === 1 ? 'ticket is' : 'tickets are') . " already taken, so the number can't be lower than {$taken}.");
            }
        };

        if ($this->paymentMode() === 'free') {
            $check($tiers->first(), $this->input('expected_attendance'), 'expected_attendance');
            return;
        }
        foreach ((array) $this->input('tiers', []) as $i => $row) {
            $check($tiers->get((int) ($row['id'] ?? 0)), $row['quantity_available'] ?? null, "tiers.{$i}.quantity_available");
        }
    }

    public function messages(): array
    {
        return [
            'tiers.*.quantity_available.required' => 'Enter how many tickets of each type you expect to sell.',
            'tiers.*.quantity_available.min'      => 'Each ticket type needs at least 1 ticket.',
            'expected_attendance.required'        => 'Enter how many people you expect.',
            'online_methods.*.in' => 'That online payment method isn\'t available yet.',
            'tiers.*.tier_name.required' => 'Every ticket type needs a name.',
            'tiers.*.tier_name.distinct' => 'Two ticket types have the same name.',
            'tiers.*.price.required'     => 'Every ticket type needs a price.',
            'event_date.after_or_equal'  => 'The event date can\'t be in the past.',
        ];
    }

    /** The event's free/paid mode as it will be after saving. */
    public function paymentMode(): string
    {
        $event = $this->route('event');

        if ($event && $event->tickets()->exists()) {
            return $event->payment_mode;
        }

        return $this->input('payment_mode', $event?->payment_mode ?? 'free');
    }
}
