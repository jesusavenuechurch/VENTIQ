<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Models\OrganizationPackage;
use App\Models\OrganizationPaymentMethod;
use Filament\Forms;
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

        // ── Available packages ─────────────────────────────────────────
        $availablePackages = $isSuperAdmin
            ? OrganizationPackage::where('status', 'active')
                ->with('organization')
                ->get()
                ->mapWithKeys(fn ($p) => [
                    $p->id => "[{$p->organization->name}] {$p->display_name} — {$p->remaining_tickets} tickets left"
                ])
            : ($org?->availablePackages()->mapWithKeys(fn ($p) => [
                $p->id => "{$p->display_name} — {$p->remaining_tickets} tickets · {$p->remaining_comp_tickets} comp"
            ]) ?? collect());

        $singlePackage = $availablePackages->count() === 1
            ? OrganizationPackage::find($availablePackages->keys()->first())
            : null;

        // ── STEP: PACKAGE (only if more than one) ─────────────────────
        if ($availablePackages->count() > 1 || $isSuperAdmin) {
            $steps[] = Step::make('Package')
                ->icon('heroicon-o-cube')
                ->description('Choose which package funds this event')
                ->schema([
                    Forms\Components\Select::make('organization_package_id')
                        ->label('Select Package')
                        ->options($availablePackages)
                        ->required(!$isSuperAdmin)
                        ->searchable()
                        ->live()
                        ->helperText('Each package funds one event.')
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            if ($state) {
                                $package = OrganizationPackage::find($state);
                                if ($package) {
                                    $set('organization_id', $package->organization_id);
                                    $set('capacity', $package->package_type === 'enterprise'
                                        ? null
                                        : $package->remaining_tickets
                                    );
                                }
                            }
                        })
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make('package_summary')
                        ->label('')
                        ->content(function (Forms\Get $get) {
                            $packageId = $get('organization_package_id');
                            if (!$packageId) return new HtmlString(
                                '<div class="p-4 border border-dashed border-gray-200 rounded-xl text-center text-gray-400 text-sm">Select a package above</div>'
                            );
                            return $this->packageSummaryHtml(OrganizationPackage::find($packageId));
                        })
                        ->columnSpanFull(),
                ]);
        }

        // ── STEP 1: EVENT TYPE (only if org has workshop access) ───────
        $showEventTypeStep = $isSuperAdmin || ($org?->hasWorkshopAccess() ?? false);

        if ($showEventTypeStep) {
            $steps[] = Step::make('Event Type')
                ->icon('heroicon-o-squares-2x2')
                ->description('What are you creating?')
                ->schema([
                    Forms\Components\Radio::make('event_type')
                        ->label('What would you like to create?')
                        ->options([
                            'standard' => '📅 Standard Event',
                            'workshop' => '🎓 Workshop / Training',
                        ])
                        ->descriptions([
                            'standard' => 'Conferences, concerts, church events, galas, community gatherings.',
                            'workshop' => 'Donor workshops, ministry trainings, funded programmes, HR sessions.',
                        ])
                        ->default('standard')
                        ->required()
                        ->live()
                        ->columnSpanFull(),
                ]);
        }

        // ── STEP 2: EVENT DETAILS ──────────────────────────────────────
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

                Forms\Components\Hidden::make('slug_locked')->default(false),
                Forms\Components\Hidden::make('tagline_locked')->default(false),
                Forms\Components\Hidden::make('organization_package_id')->default($singlePackage?->id),

                // event_type default for orgs without workshop access
                Forms\Components\Hidden::make('event_type')->default('standard'),

                Forms\Components\Select::make('organization_id')
                    ->relationship('organization', 'name')
                    ->required()
                    ->searchable()
                    ->disabled(fn () => !$isSuperAdmin)
                    ->default($org?->id)
                    ->dehydrated(true)
                    ->columnSpanFull(),

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
                        if (!$get('slug_locked')) $set('slug', Str::slug($state));
                        if (!$get('tagline_locked')) $set('tagline', $state);
                    })
                    ->columnSpan(1),

                Forms\Components\TextInput::make('slug')
                    ->label('Public URL Slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->afterStateUpdated(fn ($set) => $set('slug_locked', true))
                    ->helperText('Auto-generated from event name')
                    ->columnSpan(1),

                Forms\Components\TextInput::make('tagline')
                    ->label('Tagline')
                    ->afterStateUpdated(fn ($set) => $set('tagline_locked', true))
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('description')
                    ->label('Full Description')
                    ->rows(4)
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_public')
                    ->label('Public Event')
                    ->default(true)
                    ->live()
                    ->disabled(function (Forms\Get $get) use ($isSuperAdmin, $singlePackage) {
                        if ($isSuperAdmin) return false;
                        $package = OrganizationPackage::find($get('organization_package_id')) ?? $singlePackage;
                        return !($package?->hasFeature('private_events') ?? false);
                    })
                    ->hint(function (Forms\Get $get) use ($isSuperAdmin, $singlePackage) {
                        if ($isSuperAdmin) return null;
                        $package = OrganizationPackage::find($get('organization_package_id')) ?? $singlePackage;
                        if (!($package?->hasFeature('private_events') ?? false)) {
                            return new HtmlString('🔒 Not included in your package. <button type="button" onclick="window.dispatchEvent(new CustomEvent(\'open-upgrade-modal\'))" class="underline font-medium text-primary-600">Upgrade</button>');
                        }
                        return null;
                    })
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('auto_package_notice')
                    ->label('')
                    ->content(fn () => $singlePackage ? $this->packageSummaryHtml($singlePackage) : null)
                    ->visible(fn () => $singlePackage !== null)
                    ->columnSpanFull(),
            ])
            ->columns(2);

        // ── STEP 3: SCHEDULE & LOCATION ───────────────────────────────
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

        // ── STEP 4: REGISTRATION & PAYMENTS ───────────────────────────
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

                // ── PAID: payment method selection ─────────────────────
                Forms\Components\Section::make('Payment Methods')
                    ->description('Choose how attendees will pay for this event.')
                    ->visible(fn (Forms\Get $get) => $get('payment_mode') === 'paid')
                    ->schema([

                        // Online payments toggle
                        Forms\Components\Checkbox::make('enable_online_payments')
                            ->label('💳 Online Payments (Recommended)')
                            ->helperText('Attendees pay via M-Pesa, EcoCash, or Card. A 5% processing fee is added at checkout. Tickets activate instantly.')
                            ->default(true)
                            ->live()
                            ->columnSpanFull(),

                        // Manual payment methods — checkboxes from org's configured methods
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

                        // Notice if no manual methods configured
                        Forms\Components\Placeholder::make('no_manual_methods_notice')
                            ->label('')
                            ->content(new HtmlString('
                                <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-700">
                                    💡 No manual payment methods configured yet. You can add M-Pesa, bank transfer, or cash options under <strong>Settings → Payment Methods</strong> and they\'ll appear here.
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

                        // Validation notice
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

                // ── FREE: confirmation message ─────────────────────────
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

        // ── STEP 5: TICKET TIERS ──────────────────────────────────────
        // Skip entirely for free events — tier is auto-created on save
        // For workshops — simplified single price input
        // For paid standard — full repeater
        $steps[] = Step::make('Ticket Tiers')
            ->icon('heroicon-o-ticket')
            ->description('Set up your tickets')
            ->schema([

                // FREE EVENT — no input needed
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

                // WORKSHOP + PAID — simplified single tier
                Forms\Components\Section::make('Workshop Ticket')
                    ->description('Workshops use a single ticket type.')
                    ->visible(fn (Forms\Get $get) =>
                        $get('event_type') === 'workshop' && $get('payment_mode') === 'paid'
                    )
                    ->schema([
                        Forms\Components\TextInput::make('workshop_ticket_price')
                            ->label('Ticket Price')
                            ->numeric()
                            ->prefix(config('constants.currency.symbol'))
                            ->required(fn (Forms\Get $get) =>
                                $get('event_type') === 'workshop' && $get('payment_mode') === 'paid'
                            )
                            ->live()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('workshop_capacity')
                            ->label('Total Capacity')
                            ->numeric()
                            ->placeholder('∞ Unlimited')
                            ->columnSpan(1),

                        Forms\Components\Placeholder::make('workshop_tier_preview')
                            ->label('What will be created')
                            ->content(function (Forms\Get $get) {
                                $price    = $get('workshop_ticket_price') ?? 0;
                                $capacity = $get('workshop_capacity') ?? '∞';
                                return new HtmlString("
                                    <div class='text-sm text-gray-600'>
                                        Tier: <strong>Workshop</strong> ·
                                        Price: <strong>" . config('constants.currency.symbol') . number_format((float)$price, 2) . "</strong> ·
                                        Capacity: <strong>{$capacity}</strong>
                                    </div>
                                ");
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // STANDARD + PAID — full tier repeater
                Forms\Components\Placeholder::make('tier_package_note')
                    ->label('')
                    ->content(function (Forms\Get $get) use ($singlePackage, $isSuperAdmin) {
                        if ($isSuperAdmin) return null;
                        $package = OrganizationPackage::find($get('organization_package_id')) ?? $singlePackage;
                        if (!$package) return null;
                        if (!$package->hasFeature('ticket_tiers')) {
                            return new HtmlString("
                                <div class='p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800'>
                                    🔒 Your package supports one ticket type only.
                                    <button type='button' onclick=\"window.dispatchEvent(new CustomEvent('open-upgrade-modal'))\" class='underline font-medium ml-1'>Upgrade →</button>
                                </div>
                            ");
                        }
                        return null;
                    })
                    ->visible(fn (Forms\Get $get) =>
                        $get('event_type') !== 'workshop' && $get('payment_mode') === 'paid'
                    )
                    ->columnSpanFull(),

                Forms\Components\Repeater::make('tiers')
                    ->label('Ticket Tiers')
                    ->relationship('tiers')
                    ->visible(fn (Forms\Get $get) =>
                        $get('event_type') !== 'workshop' && $get('payment_mode') === 'paid'
                    )
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

                        Forms\Components\Toggle::make('allow_installments')
                            ->label('Allow Installments')
                            ->default(false)
                            ->live()
                            ->disabled(function (Forms\Get $get) use ($isSuperAdmin, $singlePackage) {
                                if ($isSuperAdmin) return false;
                                $package = OrganizationPackage::find($get('../../organization_package_id')) ?? $singlePackage;
                                return !($package?->hasFeature('installments') ?? false);
                            })
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
                    ->maxItems(function (Forms\Get $get) use ($isSuperAdmin, $singlePackage) {
                        if ($isSuperAdmin) return null;
                        $package = OrganizationPackage::find($get('organization_package_id')) ?? $singlePackage;
                        if (!$package) return 1;
                        if (in_array($package->package_type, ['starter', 'free_trial'])) return 1;
                        return $package->hasFeature('ticket_tiers') ? null : 1;
                    })
                    ->columns(['default' => 1, 'md' => 4])
                    ->defaultItems(1)
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['tier_name'] ?? 'New Tier')
                    ->columnSpanFull(),
            ]);

        // ── STEP 6: REVIEW & PUBLISH ──────────────────────────────────
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
                    ->content(function (Forms\Get $get) use ($singlePackage) {
                        $packageId = $get('organization_package_id');
                        $package   = $packageId ? OrganizationPackage::find($packageId) : $singlePackage;
                        return view('filament.components.event-summary', [
                            'name'      => $get('name') ?? 'Not set',
                            'date'      => $get('event_date') ?? 'Not set',
                            'venue'     => $get('venue') ?? 'Not set',
                            'tierCount' => count($get('tiers') ?? []),
                            'tiers'     => $get('tiers') ?? [],
                            'package'   => $package,
                        ]);
                    })
                    ->columnSpanFull(),
            ]);

        return $steps;
    }

    // ── MUTATE DATA BEFORE SAVE ───────────────────────────────────────
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Combine date + time
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

        // Build enabled_payment_method_ids — merge online + manual selections
        $enabledIds = $data['enabled_payment_method_ids'] ?? [];

        if (($data['payment_mode'] ?? 'free') === 'paid' && !empty($data['enable_online_payments'])) {
            $org   = auth()->user()->organization;
            $orgId = $org?->id ?? $data['organization_id'];

            // Auto-create online payment method for this org if it doesn't exist
            $onlineMethod = OrganizationPaymentMethod::firstOrCreate(
                ['organization_id' => $orgId, 'payment_method' => 'online'],
                ['is_active' => true, 'display_order' => 0]
            );

            $enabledIds = array_merge([$onlineMethod->id], (array) $enabledIds);
        }

        $data['enabled_payment_method_ids'] = !empty($enabledIds) ? array_values(array_unique($enabledIds)) : null;

        // Clean up wizard-only fields
        unset(
            $data['event_date_only'],
            $data['event_time_only'],
            $data['registration_deadline_date'],
            $data['registration_deadline_time'],
            $data['slug_locked'],
            $data['tagline_locked'],
            $data['enable_online_payments'],
            $data['workshop_ticket_price'],
            $data['workshop_capacity'],
        );

        return $data;
    }

    // ── AFTER CREATE — auto-create tiers ─────────────────────────────
    protected function afterCreate(): void
    {
        $event = $this->record->fresh();

        // Increment package events used
        if ($event->organization_package_id) {
            OrganizationPackage::find($event->organization_package_id)?->incrementEventsUsed();
        }

        // Auto-create Free tier for free events
        if ($event->payment_mode === 'free') {
            $event->tiers()->create([
                'tier_name'          => 'Free',
                'price'              => 0,
                'quantity_available' => null,
                'is_active'          => true,
                'quantity_per_purchase' => 1,
            ]);
        }

        // Auto-create Workshop tier for workshop + paid events
        if ($event->event_type === 'workshop' && $event->payment_mode === 'paid') {
            $formData = $this->form->getRawState();
            $event->tiers()->create([
                'tier_name'          => 'Workshop',
                'price'              => $formData['workshop_ticket_price'] ?? 0,
                'quantity_available' => $formData['workshop_capacity'] ?? null,
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

    // ── PACKAGE SUMMARY HTML ──────────────────────────────────────────
    private function packageSummaryHtml(?OrganizationPackage $p): HtmlString|string
    {
        if (!$p) return '';

        $features = collect($p->getEnabledFeatures())
            ->map(fn ($f) => '<span class="inline-flex items-center gap-1 text-xs bg-white border border-gray-200 text-gray-600 px-2 py-0.5 rounded-full">✓ ' . ucwords(str_replace('_', ' ', $f)) . '</span>')
            ->implode(' ');

        $ticketColor  = $p->remaining_tickets > 20 ? '#10B981' : '#F07F22';
        $packageColor = match($p->package_type) {
            'starter'      => '#3B82F6',
            'standard'     => '#10B981',
            'professional' => '#8B5CF6',
            'enterprise'   => '#EF4444',
            default        => '#6B7280',
        };

        return new HtmlString("
            <div class='rounded-xl border border-gray-100 dark:border-white/10 overflow-hidden'>
                <div class='px-4 py-3 flex items-center gap-3' style='background:{$packageColor}15;border-bottom:1px solid {$packageColor}30'>
                    <span class='text-sm font-bold' style='color:{$packageColor}'>{$p->display_name}</span>
                </div>
                <div class='p-4 grid grid-cols-2 gap-3 sm:grid-cols-4'>
                    <div class='bg-gray-50 dark:bg-white/5 rounded-lg p-3 text-center'>
                        <div class='text-xl font-bold' style='color:{$ticketColor}'>{$p->remaining_tickets}</div>
                        <div class='text-xs text-gray-400 mt-1'>Tickets</div>
                    </div>
                    <div class='bg-gray-50 dark:bg-white/5 rounded-lg p-3 text-center'>
                        <div class='text-xl font-bold text-gray-700 dark:text-gray-200'>{$p->remaining_comp_tickets}</div>
                        <div class='text-xs text-gray-400 mt-1'>Comp</div>
                    </div>
                    <div class='bg-gray-50 dark:bg-white/5 rounded-lg p-3 text-center'>
                        <div class='text-xl font-bold text-gray-700 dark:text-gray-200'>{$p->max_scanners}</div>
                        <div class='text-xs text-gray-400 mt-1'>Scanners</div>
                    </div>
                    <div class='bg-gray-50 dark:bg-white/5 rounded-lg p-3 text-center'>
                        <div class='text-xl font-bold text-gray-700 dark:text-gray-200'>{$p->max_users}</div>
                        <div class='text-xs text-gray-400 mt-1'>Team</div>
                    </div>
                </div>
                <div class='px-4 pb-4 flex flex-wrap gap-2'>{$features}</div>
            </div>
        ");
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}