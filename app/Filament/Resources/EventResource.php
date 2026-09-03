<?php

namespace App\Filament\Resources;

use App\Models\Event;
use App\Models\OrganizationPackage;
use App\Models\OrganizationPaymentMethod;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use App\Filament\Resources\EventResource\Pages;
use App\Filament\Resources\EventResource\RelationManagers\TicketsRelationManager;
use App\Filament\Resources\EventResource\Pages\EventRegistrations;
use App\Filament\Resources\EventResource\Pages\EventTiers;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $cluster = \App\Filament\Clusters\EventsCluster::class;
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Events';

    /* ------------------------------------------------------------
     | Permissions
     ------------------------------------------------------------ */

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if ($user?->isSalesAgent()) return false;
        return $user?->hasAnyPermission(['view_event', 'create_event']) ?? false;
    }

    public static function canCreate(): bool
    {
        // Ticketing packages are deprecated (flat 4.9% + M7.50 fee model
        // now) — availablePackages() always returns empty per
        // HasPackageEntitlements, so gating on it here blocked every
        // non-superadmin org from ever creating an event. Permission is
        // the only real gate now.
        return auth()->user()?->hasPermissionTo('create_event') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->isSuperAdmin()) return $user->hasPermissionTo('edit_event');
        return $user->hasPermissionTo('edit_event') && $record->organization_id === $user->organization_id;
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->isSuperAdmin()) return $user->hasPermissionTo('delete_event');
        return $user->hasPermissionTo('delete_event') && $record->organization_id === $user->organization_id;
    }

    /* ------------------------------------------------------------
     | Query Scope
     ------------------------------------------------------------ */

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();
        if ($user?->isSuperAdmin()) return $query;
        if ($user?->organization_id) return $query->where('organization_id', $user->organization_id);
        return $query->whereNull('id');
    }

    /* ------------------------------------------------------------
     | Form — used for EDIT only
     | Create uses CreateEvent wizard (HasWizard)
     ------------------------------------------------------------ */

    public static function form(Form $form): Form
    {
        $user         = auth()->user();
        $isSuperAdmin = $user?->isSuperAdmin();
        $org          = $user?->organization;

        $packageOptions = $isSuperAdmin
            ? OrganizationPackage::where('status', 'active')
                ->with('organization')
                ->get()
                ->mapWithKeys(fn ($p) => [
                    $p->id => "{$p->organization->name} — {$p->display_name} ({$p->remaining_tickets} tickets left)"
                ])
            : ($org?->availablePackages()->mapWithKeys(fn ($p) => [
                $p->id => "{$p->display_name} — {$p->remaining_tickets} tickets · {$p->remaining_comp_tickets} comp"
            ]) ?? collect());

        return $form->schema([

            // ── PACKAGE ───────────────────────────────────────────────
            Forms\Components\Section::make('Package')
                ->description('The package funding this event. Locked once tickets are created.')
                ->schema([
                    Forms\Components\Select::make('organization_package_id')
                        ->label('Event Package')
                        ->options($packageOptions)
                        ->required()
                        ->live()
                        ->disabled(fn ($record) => !$isSuperAdmin && $record !== null && $record->tickets()->exists())
                        ->hint(fn ($record) => !$isSuperAdmin && $record?->tickets()->exists()
                            ? '🔒 Package locked — tickets have been created for this event.'
                            : null
                        )
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            if ($state) {
                                $package = OrganizationPackage::find($state);
                                if ($package) $set('organization_id', $package->organization_id);
                            }
                        })
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make('package_summary')
                        ->label('')
                        ->content(function (Forms\Get $get) {
                            $package = OrganizationPackage::find($get('organization_package_id'));
                            if (!$package) return null;

                            $features    = collect($package->getEnabledFeatures())
                                ->map(fn ($f) => '✓ ' . ucwords(str_replace('_', ' ', $f)))
                                ->implode('  ·  ');
                            $ticketColor = $package->remaining_tickets > 20 ? '#10B981' : '#F07F22';

                            return new HtmlString("
                                <div class='p-3 bg-gray-50 dark:bg-white/5 rounded-xl border border-gray-100 dark:border-white/10 text-xs space-y-1'>
                                    <div class='flex gap-6'>
                                        <span><strong style='color:{$ticketColor}'>{$package->remaining_tickets}</strong> tickets remaining</span>
                                        <span><strong>{$package->remaining_comp_tickets}</strong> comp remaining</span>
                                        <span><strong>{$package->max_scanners}</strong> scanners</span>
                                        <span><strong>{$package->max_users}</strong> team members</span>
                                    </div>
                                    <div class='text-gray-400 pt-1'>{$features}</div>
                                </div>
                            ");
                        })
                        ->visible(fn (Forms\Get $get) => (bool) $get('organization_package_id'))
                        ->columnSpanFull(),
                ])
                ->hidden(fn ($record) => !$isSuperAdmin && $packageOptions->isEmpty()),

            // ── BASIC INFO ────────────────────────────────────────────
            Forms\Components\Section::make('Basic Information')
                ->schema([
                    Forms\Components\Select::make('organization_id')
                        ->relationship('organization', 'name')
                        ->required()
                        ->searchable()
                        ->disabled(fn () => !$isSuperAdmin)
                        ->default($org?->id)
                        ->dehydrated(true)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('name')
                        ->label('Event Name')
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('tagline')
                        ->label('Tagline')
                        ->helperText('Short catchy description.')
                        ->columnSpan(1),

                    Forms\Components\FileUpload::make('banner_image')
                        ->label('Event Poster / Flyer')
                        ->image()
                        ->disk('public')
                        ->directory('event-banners')
                        ->maxSize(10240)
                        ->imageEditor()
                        ->imageEditorAspectRatios([null, '16:9', '4:5'])
                        ->helperText('Upload the full event poster. Max 10MB.')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg', 'image/webp'])
                        ->previewable(true)
                        ->downloadable()
                        ->openable()
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('is_public')
                        ->label('Public Event')
                        ->default(true)
                        ->live()
                        ->disabled(function (Forms\Get $get) use ($isSuperAdmin) {
                            if ($isSuperAdmin) return false;
                            $package = OrganizationPackage::find($get('organization_package_id'));
                            return !($package?->hasFeature('private_events') ?? false);
                        })
                        ->hint(function (Forms\Get $get) use ($isSuperAdmin) {
                            if ($isSuperAdmin) return null;
                            $package = OrganizationPackage::find($get('organization_package_id'));
                            if (!($package?->hasFeature('private_events') ?? false)) {
                                return new HtmlString(
                                    '🔒 Not included in your package. ' .
                                    '<button type="button"
                                        onclick="window.dispatchEvent(new CustomEvent(\'open-upgrade-modal\'))"
                                        class="underline font-medium text-primary-600 hover:text-primary-700">
                                        Upgrade your package
                                    </button>'
                                );
                            }
                            return null;
                        })
                        ->helperText('Private events are only accessible via a shared link.')
                        ->columnSpanFull(),

                    Forms\Components\Select::make('event_type')
                        ->label('Event Type')
                        ->options(function () {
                            $user = auth()->user();
                            $types = [
                                'standard' => 'Standard Event',
                            ];
                            if ($user->isSuperAdmin() || $user->organization?->hasWorkshopAccess()) {
                                $types['workshop'] = 'Workshop / Training';
                            }
                            return $types;
                        })
                        ->default('standard')
                        ->required()
                        ->live()
                        ->helperText(fn (Forms\Get $get) =>
                            $get('event_type') === 'workshop'
                                ? 'Workshop events collect attendee details and signatures at check-in.'
                                : 'Standard ticketing and check-in.'
                        )
                        ->columnSpanFull(),
                ])
                ->columns(2),

            // ── DESCRIPTION ───────────────────────────────────────────
            Forms\Components\Section::make('Description')
                ->schema([
                    Forms\Components\Textarea::make('description')
                        ->label('Full Description')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),

            // ── SCHEDULE ──────────────────────────────────────────────
            // Using split date/time pickers so EditEvent mutate methods work correctly
            Forms\Components\Section::make('Schedule')
                ->schema([
                    Forms\Components\Section::make('Event Date & Time')
                        ->schema([
                            Forms\Components\DatePicker::make('event_date_only')
                                ->label('Event Date')
                                ->required()
                                ->native(false)
                                ->displayFormat('D, M j, Y')
                                ->prefixIcon('heroicon-o-calendar')
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                    $time = $get('event_time_only');
                                    if ($state && $time) {
                                        $set('event_date', \Carbon\Carbon::parse($state)
                                            ->setTimeFromTimeString($time)
                                            ->format('Y-m-d H:i:s'));
                                    }
                                })
                                ->columnSpan(1),

                            Forms\Components\TimePicker::make('event_time_only')
                                ->label('Event Time')
                                ->required()
                                ->native(false)
                                ->seconds(false)
                                ->prefixIcon('heroicon-o-clock')
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                    $date = $get('event_date_only');
                                    if ($state && $date) {
                                        $set('event_date', \Carbon\Carbon::parse($date)
                                            ->setTimeFromTimeString($state)
                                            ->format('Y-m-d H:i:s'));
                                    }
                                })
                                ->columnSpan(1),

                            Forms\Components\Hidden::make('event_date'),

                            Forms\Components\Placeholder::make('event_datetime_preview')
                                ->label('Confirmed Date & Time')
                                ->content(function (Forms\Get $get) {
                                    $date = $get('event_date_only');
                                    $time = $get('event_time_only');
                                    if (!$date || !$time || strlen((string) $time) < 4) {
                                        return new HtmlString('<span class="text-gray-400 text-sm">⏱️ Select date and time above</span>');
                                    }
                                    try {
                                        return new HtmlString('📅 <strong>' . \Carbon\Carbon::parse($date . ' ' . $time)->format('l, F j, Y @ g:i A') . '</strong>');
                                    } catch (\Exception $e) {
                                        return new HtmlString('<span class="text-gray-400 text-sm">⏱️ Select date and time above</span>');
                                    }
                                })
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->collapsible(),

                    Forms\Components\Section::make('Registration Deadline')
                        ->description('When should registration close?')
                        ->icon('heroicon-o-clock')
                        ->description(function (Forms\Get $get) {
                            return $get('registration_deadline_date')
                                ? '✅ Deadline set — ' . \Carbon\Carbon::parse($get('registration_deadline_date'))->format('M j, Y')
                                : 'Optional — leave blank to keep registration open until the event';
                        })
                        ->iconColor(function (Forms\Get $get) {
                            return $get('registration_deadline_date') ? 'success' : 'gray';
                        })
                        ->schema([
                            Forms\Components\DatePicker::make('registration_deadline_date')
                                ->label('Deadline Date')
                                ->nullable()
                                ->native(false)
                                ->displayFormat('D, M j, Y')
                                ->prefixIcon('heroicon-o-calendar')
                                ->minDate(today())
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                    $set('registration_deadline',
                                        $state && $get('registration_deadline_time')
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
                                ->prefixIcon('heroicon-o-clock')
                                ->default('23:59')
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                    $date = $get('registration_deadline_date');
                                    if ($state && $date) {
                                        $set('registration_deadline', $date . ' ' . $state);
                                    }
                                })
                                ->columnSpan(1),

                            Forms\Components\Hidden::make('registration_deadline'),

                            Forms\Components\Placeholder::make('deadline_preview')
                                ->label('Registration Closes')
                                ->content(function (Forms\Get $get) {
                                    $date = $get('registration_deadline_date');
                                    if (!$date) {
                                        return new HtmlString('<span class="text-gray-400 text-sm">♾️ No deadline — registration stays open until the event</span>');
                                    }
                                    try {
                                        $time = $get('registration_deadline_time') ?? '23:59';
                                        return new HtmlString('🔒 <strong>' . \Carbon\Carbon::parse($date . ' ' . $time)->format('l, F j, Y @ g:i A') . '</strong>');
                                    } catch (\Exception $e) {
                                        return new HtmlString('<span class="text-gray-400 text-sm">♾️ No deadline set</span>');
                                    }
                                })
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->collapsible()
                        ->collapsed(),
                ]),

            // ── LOCATION ──────────────────────────────────────────────
            Forms\Components\Section::make('Venue Details')
                ->description(function (Forms\Get $get) {
                    return $get('venue') ? '✅ ' . $get('venue') : 'Optional — where will the event take place?';
                })
                ->icon('heroicon-o-map-pin')
                ->iconColor(function (Forms\Get $get) {
                    return $get('venue') ? 'success' : 'gray';
                })
                ->schema([
                    Forms\Components\TextInput::make('venue')
                        ->label('Venue Name')
                        ->maxLength(255)
                        ->live(debounce: 500)
                        ->prefixIcon('heroicon-o-building-office')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('capacity')
                        ->label('Maximum Capacity')
                        ->numeric()
                        ->nullable()
                        ->helperText('Leave empty for unlimited')
                        ->prefixIcon('heroicon-o-users')
                        ->columnSpan(1),

                    Forms\Components\Textarea::make('location')
                        ->label('Full Address')
                        ->rows(3)
                        ->maxLength(500)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->collapsible(),

            // ── STATUS ────────────────────────────────────────────────
            Forms\Components\Section::make('Status')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->options(config('constants.event_statuses'))
                        ->default('draft')
                        ->required()
                        ->live(),

                    // Warn if still on draft
                    Forms\Components\Placeholder::make('draft_reminder')
                        ->label('')
                        ->content(new HtmlString('
                            <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
                                ⚠️ This event is still a <strong>Draft</strong> and not visible to the public.
                                Change status to <strong>Published</strong> when ready to go live.
                            </div>
                        '))
                        ->visible(fn (Forms\Get $get) => $get('status') === 'draft')
                        ->columnSpanFull(),
                ]),

            // ── PAYMENT METHODS ───────────────────────────────────────
            // Same fields the Create wizard offers, but this was missing
            // entirely from the Edit form — meaning enabled_payment_method_ids
            // was a permanent snapshot taken at creation time with no way to
            // add newly-created org payment methods to an existing event
            // afterward, even though they show as active at the org level.
            Forms\Components\Section::make('Payment Methods')
                ->description('Choose how attendees pay for this event.')
                ->schema([
                    Forms\Components\Checkbox::make('enable_online_payments')
                        ->label('💳 Online Payments')
                        ->helperText('Attendees pay via M-Pesa, EcoCash, or Card.')
                        ->live()
                        ->columnSpanFull(),

                    Forms\Components\CheckboxList::make('enabled_payment_method_ids')
                        ->label('Manual Payment Methods')
                        ->options(fn ($record) => OrganizationPaymentMethod::where('organization_id', $record?->organization_id)
                            ->where('is_active', true)
                            ->where('payment_method', '!=', 'online')
                            ->orderBy('display_order')
                            ->get()
                            ->mapWithKeys(fn ($m) => [$m->id => $m->label . ($m->account_number ? ' — ' . $m->account_number : '')])
                            ->toArray())
                        ->helperText('Attendees pay manually and submit a reference number. You approve payments in the admin panel.')
                        ->live()
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
                            ->action(function (array $data, Forms\Set $set, Forms\Get $get, $record) {
                                $method = OrganizationPaymentMethod::create([
                                    'organization_id' => $record->organization_id,
                                    'payment_method'  => $data['payment_method'],
                                    'account_name'    => $data['account_name'] ?? null,
                                    'account_number'  => $data['account_number'] ?? null,
                                    'instructions'    => $data['instructions'] ?? null,
                                    'is_active'       => true,
                                    'display_order'   => 0,
                                ]);

                                $current = $get('enabled_payment_method_ids') ?? [];
                                $set('enabled_payment_method_ids', array_values(array_unique([...$current, $method->id])));

                                Notification::make()->title('Payment method added')->success()->send();
                            }),
                    ])
                        ->visible(fn () => !$isSuperAdmin)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->collapsible(),

            // ── PAYMENT OPTIONS ───────────────────────────────────────
            Forms\Components\Section::make('Payment Options')
                ->description('Configure installment payment settings for this event.')
                ->schema([
                    Forms\Components\Toggle::make('allow_installments')
                        ->label('Allow Installment Payments')
                        ->default(false)
                        ->live()
                        ->disabled(function (Forms\Get $get) use ($isSuperAdmin) {
                            if ($isSuperAdmin) return false;
                            $package = OrganizationPackage::find($get('organization_package_id'));
                            return !($package?->hasFeature('installments') ?? false);
                        })
                        ->hint(function (Forms\Get $get) use ($isSuperAdmin) {
                            if ($isSuperAdmin) return null;
                            $package = OrganizationPackage::find($get('organization_package_id'));
                            if (!($package?->hasFeature('installments') ?? false)) {
                                return new HtmlString(
                                    '🔒 Available on Professional or as a Standard add-on. ' .
                                    '<button type="button"
                                        onclick="window.dispatchEvent(new CustomEvent(\'open-upgrade-modal\'))"
                                        class="underline font-medium text-primary-600">
                                        Upgrade
                                    </button>'
                                );
                            }
                            return null;
                        })
                        ->helperText('Enable clients to pay in multiple installments.'),

                    Forms\Components\TextInput::make('minimum_deposit_percentage')
                        ->label('Minimum Deposit (%)')
                        ->numeric()
                        ->default(30)
                        ->minValue(1)
                        ->maxValue(100)
                        ->suffix('%')
                        ->visible(fn (Forms\Get $get) => $get('allow_installments'))
                        ->required(fn (Forms\Get $get) => $get('allow_installments'))
                        ->helperText('Minimum percentage clients must pay as first deposit.'),

                    Forms\Components\Textarea::make('installment_instructions')
                        ->label('Installment Instructions')
                        ->rows(3)
                        ->visible(fn (Forms\Get $get) => $get('allow_installments'))
                        ->placeholder('Example: Pay minimum 30% deposit to secure your spot. Complete payment before event date.')
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed(),
        ]);
    }

    /* ------------------------------------------------------------
     | Table
     ------------------------------------------------------------ */

    public static function table(Table $table): Table
    {
        return $table
             ->recordUrl(fn ($record) => static::getUrl('registrations', ['record' => $record]))
            ->defaultSort('event_date')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap()
                    ->limit(40)
                    ->description(fn ($record) => $record->organizationPackage?->display_name),

                Tables\Columns\TextColumn::make('event_date')
                    ->label('Date')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->description(fn ($record) => $record->event_date?->format('g:i A')),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray'    => 'draft',
                        'info'    => 'published',
                        'success' => 'live',
                        'danger'  => 'closed',
                    ]),

                Tables\Columns\IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('allow_installments')
                    ->label('Installments')
                    ->boolean()
                    ->toggleable()
                    ->tooltip(fn ($record) => $record->allow_installments
                        ? "Min deposit: {$record->minimum_deposit_percentage}%"
                        : 'Installments disabled'
                    ),

                Tables\Columns\TextColumn::make('venue')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(25),

                Tables\Columns\TextColumn::make('capacity')
                    ->label('Capacity')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn ($state) => $state ? number_format($state) : '∞'),

                Tables\Columns\TextColumn::make('tiers_count')
                    ->label('Tiers')
                    ->counts('tiers')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('tickets_count')
                    ->label('Tickets')
                    ->counts('tickets')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organization')
                    ->visible(fn () => auth()->user()?->isSuperAdmin())
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('public_link')
                    ->label('View')
                    ->icon('heroicon-o-link')
                    ->color('primary')
                    ->visible(fn ($record) => $record->is_public && $record->slug && $record->organization?->slug)
                    ->modalHeading('Public Event URL')
                    ->modalContent(fn ($record) => view('filament.modals.event-url', [
                        'event'     => $record,
                        'url'       => $record->public_url,
                        'qrBase64'  => base64_encode(
                            \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size(300)->margin(1)->generate($record->public_url)
                        ),
                    ]))
                    ->modalSubmitAction(false),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\Action::make('toggle_installments')
                        ->label(fn ($record) => $record->allow_installments ? 'Disable Installments' : 'Enable Installments')
                        ->icon('heroicon-o-banknotes')
                        ->color(fn ($record) => $record->allow_installments ? 'warning' : 'success')
                        ->requiresConfirmation()
                        ->visible(fn ($record) => $record->organizationPackage?->hasFeature('installments') ?? false)
                        ->action(function ($record) {
                            $record->update(['allow_installments' => !$record->allow_installments]);
                            Notification::make()
                                ->title($record->allow_installments ? 'Installments Enabled' : 'Installments Disabled')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteAction::make(),
                ])
                ->icon('heroicon-o-ellipsis-vertical'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(config('constants.event_statuses')),

                Tables\Filters\TernaryFilter::make('allow_installments')
                    ->label('Installments')
                    ->placeholder('All events')
                    ->trueLabel('Enabled')
                    ->falseLabel('Disabled'),

                Tables\Filters\TernaryFilter::make('is_public')
                    ->label('Public')
                    ->placeholder('All events')
                    ->trueLabel('Public')
                    ->falseLabel('Private'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
           // TicketsRelationManager::class,
        ];
    }
    /* ------------------------------------------------------------
     | Pages
     ------------------------------------------------------------ */

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit'   => Pages\EditEvent::route('/{record}/edit'),
            'registrations' => Pages\EventRegistrations::route('/{record}/registrations'),
             'tiers'  => Pages\EventTiers::route('/{record}/tiers'),
        ];
    }

    public static function getRecordSubNavigation(\Filament\Resources\Pages\Page $page): array
    {
        return $page->generateNavigationItems([
            Pages\EventRegistrations::class,
            Pages\EventTiers::class,
            Pages\EditEvent::class,
        ]);
    }
}