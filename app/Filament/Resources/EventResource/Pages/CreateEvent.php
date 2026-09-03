<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Models\OrganizationPaymentMethod;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Wizard\Step;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    use CreateRecord\Concerns\HasWizard;

    protected function getSteps(): array
    {
        $user         = auth()->user();
        $isSuperAdmin = $user?->isSuperAdmin();
        $org          = $user?->organization;
        $steps        = [];

        // Event Type step removed — workshop mode moved to Programmes, so
        // every Event created here is 'standard' now (set in
        // mutateFormDataBeforeCreate()). No choice left to make.

        // ── STEP 1: EVENT DETAILS ────────────────────────────────────────
        $steps[] = Step::make('Event Details')
            ->icon('heroicon-o-information-circle')
            ->description('Basic event information')
            ->schema([
                Forms\Components\Placeholder::make('assist_button')
                    ->label('')
                    ->content(new HtmlString('
                        <button type="button"
                            onclick="window.dispatchEvent(new CustomEvent(\'open-ventiq-assist-modal\'))"
                            style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background-color:#16a34a;color:#fff;font-size:14px;font-weight:700;border-radius:12px;border:none;cursor:pointer;box-shadow:0 2px 8px rgba(22,163,74,0.4);">
                            ✨ Fill with Ventiq Assist
                        </button>
                    '))
                    ->columnSpanFull(),

                Forms\Components\Hidden::make('tagline_locked')->default(false),

                Forms\Components\Select::make('organization_id')
                    ->relationship('organization', 'name')
                    ->required()
                    ->searchable()
                    ->disabled(fn () => !$isSuperAdmin)
                    ->default($org?->id)
                    ->dehydrated(true)
                    ->columnSpanFull(),

                Forms\Components\Select::make('category')
                    ->label('Category')
                    ->options(collect(config('constants.categories'))->map(fn ($c) => $c['label']))
                    ->searchable()
                    ->required()
                    ->columnSpan(1),

                Forms\Components\Select::make('city')
                    ->label('District')
                    ->options([
                        'Maseru' => 'Maseru', 'Leribe' => 'Leribe', 'Mafeteng' => 'Mafeteng',
                        "Mohale's Hoek" => "Mohale's Hoek", 'Butha-Buthe' => 'Butha-Buthe',
                        "Qacha's Nek" => "Qacha's Nek", 'Mokhotlong' => 'Mokhotlong',
                        'Quthing' => 'Quthing', 'Berea' => 'Berea', 'Thaba-Tseka' => 'Thaba-Tseka',
                    ])
                    ->default('Maseru')
                    ->searchable()
                    ->required()
                    ->columnSpan(1),

                Forms\Components\FileUpload::make('banner_image')
                    ->label('Event Poster / Flyer')
                    ->image()
                    ->disk('public')
                    ->directory('event-banners')
                    ->maxSize(10240)
                    ->imageEditor()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg', 'image/webp'])
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('name')
                    ->label('Event Name')
                    ->required()
                    ->live(debounce: 500)
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        if (!$get('tagline_locked')) $set('tagline', $state);
                    })
                    ->columnSpan(1),

                Forms\Components\TextInput::make('tagline')
                    ->label('Tagline')
                    ->afterStateUpdated(fn ($set) => $set('tagline_locked', true))
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('description')
                    ->label('Full Description')
                    ->rows(4)
                    ->columnSpanFull(),

                // No longer gated — every org can run a private event now.
                Forms\Components\Toggle::make('is_public')
                    ->label('Public Event')
                    ->default(true)
                    ->columnSpanFull(),
            ])
            ->columns(2);

        // ── STEP 2: SCHEDULE & LOCATION ──────────────────────────────────
        $steps[] = Step::make('Schedule & Location')
            ->icon('heroicon-o-calendar')
            ->description('When and where')
            ->schema([
                Forms\Components\Section::make('Event Date & Time')
                    ->schema([
                        Forms\Components\DatePicker::make('event_date_only')
                            ->label('Event Date')
                            ->required()
                            ->native(false)
                            ->displayFormat('D, M j, Y')
                            ->minDate(today())
                            ->default(today())
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $time = $get('event_time_only');
                                if ($state && $time) {
                                    $set('event_date', \Carbon\Carbon::parse($state)->setTimeFromTimeString($time)->format('Y-m-d H:i:s'));
                                }
                            })
                            ->columnSpan(1),

                        Forms\Components\TimePicker::make('event_time_only')
                            ->label('Event Time')
                            ->required()
                            ->native(false)
                            ->seconds(false)
                            ->default('18:00')
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $date = $get('event_date_only');
                                if ($state && $date) {
                                    $set('event_date', \Carbon\Carbon::parse($date)->setTimeFromTimeString($state)->format('Y-m-d H:i:s'));
                                }
                            })
                            ->columnSpan(1),

                        Forms\Components\Hidden::make('event_date'),

                        Forms\Components\Placeholder::make('event_datetime_preview')
                            ->label('Full Date & Time')
                            ->content(function (Forms\Get $get) {
                                $date = $get('event_date_only');
                                $time = $get('event_time_only');
                                if (!$date || !$time) return new HtmlString('<span class="text-gray-400 text-sm">Select date and time above</span>');
                                try {
                                    return new HtmlString('📅 <strong>' . \Carbon\Carbon::parse($date . ' ' . $time)->format('l, F j, Y @ g:i A') . '</strong>');
                                } catch (\Exception $e) {
                                    return new HtmlString('<span class="text-gray-400 text-sm">Select date and time above</span>');
                                }
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Registration Deadline')
                    ->schema([
                        Forms\Components\DatePicker::make('registration_deadline_date')
                            ->label('Deadline Date')
                            ->nullable()
                            ->native(false)
                            ->displayFormat('D, M j, Y')
                            ->minDate(today())
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $set('registration_deadline', $state && $get('registration_deadline_time')
                                    ? $state . ' ' . $get('registration_deadline_time')
                                    : null
                                );
                            })
                            ->columnSpan(1),

                        Forms\Components\TimePicker::make('registration_deadline_time')
                            ->label('Deadline Time')
                            ->nullable()
                            ->native(false)
                            ->seconds(false)
                            ->default('23:59')
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $date = $get('registration_deadline_date');
                                if ($state && $date) $set('registration_deadline', $date . ' ' . $state);
                            })
                            ->columnSpan(1),

                        Forms\Components\Hidden::make('registration_deadline'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('Venue Details')
                    ->schema([
                        Forms\Components\TextInput::make('venue')
                            ->label('Venue Name')
                            ->maxLength(255)
                            ->placeholder('e.g., Maseru Convention Center')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('capacity')
                            ->label('Maximum Capacity')
                            ->numeric()
                            ->nullable()
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('location')
                            ->label('Full Address')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);

        // ── STEP 3: REGISTRATION & PAYMENTS ─────────────────────────────
        $steps[] = Step::make('Registration & Payments')
            ->icon('heroicon-o-credit-card')
            ->description('How will attendees register?')
            ->schema([

                Forms\Components\Radio::make('payment_mode')
                    ->label('Will attendees pay for this event?')
                    ->options([
                        'free' => '🎁 Free — no payment required',
                        'paid' => '💳 Paid — attendees will purchase tickets',
                    ])
                    ->descriptions([
                        'free' => 'Attendees register for free. A Free ticket will be created automatically.',
                        'paid' => 'Attendees pay to attend. You control pricing in the next step.',
                    ])
                    ->default('free')
                    ->required()
                    ->live()
                    ->columnSpanFull(),

                Forms\Components\Section::make('Payment Methods')
                    ->description('Choose how attendees will pay for this event.')
                    ->visible(fn (Forms\Get $get) => $get('payment_mode') === 'paid')
                    ->schema([

                        // FIX: was "A 5% processing fee is added at checkout" —
                        // stale wording from the old MoPay-only 5% split. The
                        // real model now is 4.9% + R7.50/ticket, taken via
                        // Settlement, and PayLesotho is the primary gateway
                        // with MoPay as fallback — not a flat 5% either way.
                        Forms\Components\Checkbox::make('enable_online_payments')
                            ->label('💳 Online Payments (Recommended)')
                            ->helperText('Attendees pay via M-Pesa, EcoCash, or Card. Ventiq\'s ticketing fee (4.9% + M7.50/ticket) is settled separately — not added at checkout. Tickets activate instantly.')
                            ->default(true)
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\CheckboxList::make('enabled_payment_method_ids')
                            ->label('Manual Payment Methods')
                            ->options(function () use ($org, $isSuperAdmin) {
                                $orgId = $isSuperAdmin ? null : $org?->id;
                                if (!$orgId) return [];
                                return OrganizationPaymentMethod::where('organization_id', $orgId)
                                    ->where('is_active', true)
                                    ->where('payment_method', '!=', 'online')
                                    ->orderBy('display_order')
                                    ->get()
                                    ->mapWithKeys(fn ($m) => [$m->id => $m->label . ($m->account_number ? ' — ' . $m->account_number : '')])
                                    ->toArray();
                            })
                            ->helperText('Attendees pay manually and submit a reference number. You approve payments in the admin panel.')
                            ->columnSpanFull(),

                        Forms\Components\Actions::make([
                            FormAction::make('add_payment_method')
                                ->label('+ Add Payment Method')
                                ->icon('heroicon-o-plus-circle')
                                ->color('gray')
                                ->modalHeading('Add a Payment Method')
                                ->modalWidth('lg')
                                ->form([
                                    Forms\Components\Select::make('payment_method')
                                        ->label('Method')
                                        ->options(collect(config('constants.payment_methods'))
                                            ->only(['cash', 'ecocash', 'mpesa', 'bank_transfer'])
                                            ->mapWithKeys(fn ($m, $key) => [$key => $m['label'] ?? ucfirst($key)])
                                            ->toArray())
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(fn ($set) => $set('account_number', null)),

                                    Forms\Components\TextInput::make('account_name')
                                        ->label('Account Name / Label')
                                        ->maxLength(255)
                                        ->visible(fn (Forms\Get $get) =>
                                            (bool) config("constants.payment_methods.{$get('payment_method')}.requires_account", false)
                                        ),

                                    Forms\Components\TextInput::make('account_number')
                                        ->label(fn (Forms\Get $get) =>
                                            config("constants.payment_methods.{$get('payment_method')}.account_label", 'Account Number')
                                        )
                                        ->required(fn (Forms\Get $get) =>
                                            (bool) config("constants.payment_methods.{$get('payment_method')}.requires_account", false)
                                        )
                                        ->visible(fn (Forms\Get $get) =>
                                            (bool) config("constants.payment_methods.{$get('payment_method')}.requires_account", false)
                                        ),

                                    Forms\Components\Textarea::make('instructions')
                                        ->label('Payment Instructions (optional)')
                                        ->rows(2),
                                ])
                                ->action(function (array $data, Forms\Set $set, Forms\Get $get) use ($org, $isSuperAdmin) {
                                    if ($isSuperAdmin || !$org) return;

                                    // updateOrCreate, not create — the table
                                    // only allows one row per (org,
                                    // payment_method), so re-adding a type the
                                    // org already has updates that row instead
                                    // of colliding with it.
                                    $method = OrganizationPaymentMethod::updateOrCreate(
                                        [
                                            'organization_id' => $org->id,
                                            'payment_method'  => $data['payment_method'],
                                        ],
                                        [
                                            'account_name'   => $data['account_name'] ?? null,
                                            'account_number' => $data['account_number'] ?? null,
                                            'instructions'   => $data['instructions'] ?? null,
                                            'is_active'      => true,
                                        ]
                                    );

                                    $current = $get('enabled_payment_method_ids') ?? [];
                                    $set('enabled_payment_method_ids', array_values(array_unique([...$current, $method->id])));

                                    Notification::make()->title('Payment method added')->success()->send();
                                }),
                        ])
                            ->visible(fn () => !$isSuperAdmin)
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('no_manual_methods_notice')
                            ->label('')
                            ->content(new HtmlString('
                                <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-700">
                                    💡 No manual payment methods configured yet. Use the button above to add M-Pesa, bank transfer, or cash — it\'ll appear here and in <strong>Organization → Payment Methods</strong>.
                                </div>
                            '))
                            ->visible(function () use ($org, $isSuperAdmin) {
                                if ($isSuperAdmin) return false;
                                return !OrganizationPaymentMethod::where('organization_id', $org?->id)
                                    ->where('is_active', true)
                                    ->where('payment_method', '!=', 'online')
                                    ->exists();
                            })
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('payment_selection_notice')
                            ->label('')
                            ->content(function (Forms\Get $get) {
                                $online  = $get('enable_online_payments');
                                $manual  = $get('enabled_payment_method_ids');
                                $hasSome = $online || !empty($manual);

                                if (!$hasSome) {
                                    return new HtmlString('
                                        <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                                            ⚠️ Please enable at least one payment method so attendees can pay.
                                        </div>
                                    ');
                                }

                                $parts = [];
                                if ($online) $parts[] = 'Online (M-Pesa / EcoCash / Card)';
                                if (!empty($manual)) {
                                    $methods = OrganizationPaymentMethod::whereIn('id', $manual)->pluck('payment_method')->toArray();
                                    $parts = array_merge($parts, array_map('ucfirst', $methods));
                                }

                                return new HtmlString('
                                    <div class="p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
                                        ✅ Attendees can pay via: <strong>' . implode(', ', $parts) . '</strong>
                                    </div>
                                ');
                            })
                            ->live()
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('free_event_notice')
                    ->label('')
                    ->content(new HtmlString('
                        <div class="p-5 bg-emerald-50 border-2 border-emerald-200 rounded-xl">
                            <p class="font-bold text-emerald-900">✓ Free Event</p>
                            <p class="text-sm text-emerald-700 mt-1">
                                A <strong>Free</strong> ticket will be created automatically.
                                Attendees register at no cost and receive their ticket instantly.
                            </p>
                        </div>
                    '))
                    ->visible(fn (Forms\Get $get) => $get('payment_mode') === 'free')
                    ->columnSpanFull(),
            ]);

        // ── STEP 4: TICKET TIERS ─────────────────────────────────────────
        // No package quota — every org can add as many tiers as they want.
        $steps[] = Step::make('Ticket Tiers')
            ->icon('heroicon-o-ticket')
            ->description('Set up your tickets')
            ->schema([

                Forms\Components\Placeholder::make('free_tier_notice')
                    ->label('')
                    ->content(new HtmlString('
                        <div class="p-5 bg-emerald-50 border-2 border-emerald-200 rounded-xl">
                            <p class="font-bold text-emerald-900 text-lg">✓ Free ticket will be created automatically</p>
                            <p class="text-sm text-emerald-700 mt-1">
                                A single <strong>Free</strong> tier (M0.00) will be set up for this event.
                                You can rename or adjust it after creation.
                            </p>
                        </div>
                    '))
                    ->visible(fn (Forms\Get $get) => $get('payment_mode') === 'free')
                    ->columnSpanFull(),

                Forms\Components\Repeater::make('tiers')
                    ->label('Ticket Tiers')
                    ->relationship('tiers')
                    ->visible(fn (Forms\Get $get) => $get('payment_mode') === 'paid')
                    ->schema([
                        Forms\Components\TextInput::make('tier_name')
                            ->label('Tier Name')
                            ->required()
                            ->placeholder('e.g., VIP, General Admission')
                            ->columnSpan(['default' => 1, 'md' => 2]),

                        Forms\Components\TextInput::make('price')
                            ->label('Price')
                            ->numeric()
                            ->required()
                            ->prefix(config('constants.currency.symbol'))
                            ->columnSpan(['default' => 1, 'md' => 1]),

                        Forms\Components\TextInput::make('quantity_available')
                            ->label('Quantity')
                            ->numeric()
                            ->placeholder('∞')
                            ->columnSpan(['default' => 1, 'md' => 1]),

                        Forms\Components\Textarea::make('description')
                            ->label('Benefits / Description')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Checkbox::make('is_group_ticket')
                            ->label('Group / table ticket')
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => !$state && $set('quantity_per_purchase', 1))
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('quantity_per_purchase')
                            ->label('People per ticket')
                            ->numeric()
                            ->default(1)
                            ->minValue(2)
                            ->visible(fn (Forms\Get $get) => (bool) $get('is_group_ticket'))
                            ->required(fn (Forms\Get $get) => (bool) $get('is_group_ticket'))
                            ->columnSpan(['default' => 1, 'md' => 2]),

                        Forms\Components\ColorPicker::make('color')
                            ->label('QR Color')
                            ->columnSpan(['default' => 1, 'md' => 1]),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->columnSpan(['default' => 1, 'md' => 1]),

                        // No longer gated — installments available to everyone.
                        Forms\Components\Toggle::make('allow_installments')
                            ->label('Allow Installments')
                            ->default(false)
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('minimum_deposit_percentage')
                            ->label('Minimum Deposit (%)')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(1)
                            ->maxValue(100)
                            ->visible(fn (Forms\Get $get) => $get('allow_installments'))
                            ->columnSpan(['default' => 1, 'md' => 1]),
                    ])
                    ->columns(['default' => 1, 'md' => 4])
                    ->defaultItems(1)
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['tier_name'] ?? 'New Tier')
                    ->columnSpanFull(),
            ]);

        // ── STEP 5: REVIEW & PUBLISH ─────────────────────────────────────
        $steps[] = Step::make('Review & Publish')
            ->icon('heroicon-o-eye')
            ->description('Review before publishing')
            ->schema([
                Forms\Components\Radio::make('status')
                    ->label('What would you like to do?')
                    ->options(['draft' => 'Save as Draft', 'published' => 'Publish Now'])
                    ->descriptions([
                        'draft'     => 'Save privately — not visible to the public yet.',
                        'published' => 'Make this event live immediately.',
                    ])
                    ->default('draft')
                    ->required()
                    ->live()
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('draft_warning')
                    ->label('')
                    ->content(new HtmlString('
                        <div class="rounded-lg bg-amber-50 border-2 border-amber-400 p-4 flex items-start gap-3">
                            <span class="text-2xl">⚠️</span>
                            <div>
                                <p class="font-bold text-amber-900">Saving as Draft</p>
                                <p class="text-amber-800 text-sm mt-1">Not visible to the public until you publish it.</p>
                            </div>
                        </div>
                    '))
                    ->visible(fn (Forms\Get $get) => $get('status') === 'draft' || !$get('status'))
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('published_notice')
                    ->label('')
                    ->content(new HtmlString('
                        <div class="rounded-lg bg-green-50 border-2 border-green-400 p-4 flex items-start gap-3">
                            <span class="text-2xl">🚀</span>
                            <div>
                                <p class="font-bold text-green-900">Publishing Now</p>
                                <p class="text-green-800 text-sm mt-1">Event will be live immediately. Make sure all details are correct.</p>
                            </div>
                        </div>
                    '))
                    ->visible(fn (Forms\Get $get) => $get('status') === 'published')
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('review')
                    ->label('Event Summary')
                    ->content(function (Forms\Get $get) {
                        return view('filament.components.event-summary', [
                            'name'      => $get('name') ?? 'Not set',
                            'date'      => $get('event_date') ?? 'Not set',
                            'venue'     => $get('venue') ?? 'Not set',
                            'tierCount' => count($get('tiers') ?? []),
                            'tiers'     => $get('tiers') ?? [],
                            'package'   => null, // package concept removed — view should treat this as optional now
                        ]);
                    })
                    ->columnSpanFull(),
            ]);

        return $steps;
    }

    // ── MUTATE DATA BEFORE SAVE ───────────────────────────────────────
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Slug is never user-editable — generated here from the name so it
        // can't end up with spaces or other characters that break the
        // public event route. Appends -2, -3, ... on collision.
        $baseSlug = Str::slug($data['name']);
        $slug     = $baseSlug;
        $suffix   = 1;
        while (\App\Models\Event::where('slug', $slug)->exists()) {
            $suffix++;
            $slug = "{$baseSlug}-{$suffix}";
        }
        $data['slug'] = $slug;

        // Workshop mode moved to Programmes — every Event created through
        // this wizard is 'standard' now.
        $data['event_type'] = 'standard';

        if (isset($data['event_date_only'], $data['event_time_only'])) {
            $data['event_date'] = \Carbon\Carbon::parse($data['event_date_only'])
                ->setTimeFromTimeString($data['event_time_only'])
                ->format('Y-m-d H:i:s');
        }

        if (isset($data['registration_deadline_date'], $data['registration_deadline_time'])) {
            $data['registration_deadline'] = $data['registration_deadline_date'] . ' ' . $data['registration_deadline_time'];
        } else {
            $data['registration_deadline'] = null;
        }

        $enabledIds = $data['enabled_payment_method_ids'] ?? [];

        if (($data['payment_mode'] ?? 'free') === 'paid' && !empty($data['enable_online_payments'])) {
            $org   = auth()->user()->organization;
            $orgId = $org?->id ?? $data['organization_id'];

            $onlineMethod = OrganizationPaymentMethod::firstOrCreate(
                ['organization_id' => $orgId, 'payment_method' => 'online'],
                ['is_active' => true, 'display_order' => 0]
            );

            $enabledIds = array_merge([$onlineMethod->id], (array) $enabledIds);
        }

        $data['enabled_payment_method_ids'] = !empty($enabledIds) ? array_values(array_unique($enabledIds)) : null;

        unset(
            $data['event_date_only'],
            $data['event_time_only'],
            $data['registration_deadline_date'],
            $data['registration_deadline_time'],
            $data['tagline_locked'],
            $data['enable_online_payments'],
        );

        return $data;
    }

    // ── AFTER CREATE — auto-create tiers ─────────────────────────────
    protected function afterCreate(): void
    {
        $event = $this->record->fresh();

        // Package events-used counter removed — no packages to track.

        if ($event->payment_mode === 'free') {
            $event->tiers()->create([
                'tier_name'          => 'Free',
                'price'              => 0,
                'quantity_available' => null,
                'is_active'          => true,
                'quantity_per_purchase' => 1,
            ]);
        }

        Notification::make()
            ->title('Event Created!')
            ->body("'{$event->name}' is ready." . ($event->payment_mode === 'free' ? ' Free ticket created automatically.' : ''))
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}