<?php

namespace App\Filament\Resources;

use App\Models\Ticket;
use App\Models\Event;
use App\Models\Client;
use App\Models\EventTier;
use App\Models\OrganizationPackage;
use App\Models\OrganizationPaymentMethod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\TicketResource\Pages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use App\Jobs\SendTicketApprovedEmail;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Alignment;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\TicketsImport;
use Filament\Forms\Components\FileUpload;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;
    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationGroup = 'Events';
    protected static ?string $recordTitleAttribute = 'ticket_number';

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if ($user?->isSalesAgent()) return false;
        return $user?->isSuperAdmin() || $user?->organization_id !== null;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();
        if ($user?->isSuperAdmin()) return $query;
        return $query->whereHas('event', fn (Builder $q) => $q->where('organization_id', $user->organization_id));
    }

    // ── Helper: get the package bound to an event ─────────────────────
    protected static function packageForEvent(?int $eventId): ?OrganizationPackage
    {
        if (!$eventId) return null;
        $event = Event::find($eventId);
        return $event?->organizationPackage;
    }

    // ── Helper: upgrade hint HTML ──────────────────────────────────────
    protected static function upgradeHint(string $message): HtmlString
    {
        return new HtmlString(
            $message . ' <button type="button"
                onclick="window.dispatchEvent(new CustomEvent(\'open-upgrade-modal\'))"
                class="underline font-medium text-primary-600 hover:text-primary-700">
                Upgrade your package
            </button>'
        );
    }

    public static function form(Form $form): Form
    {
        $user         = auth()->user();
        $isSuperAdmin = $user?->isSuperAdmin();

        return $form->schema([
            Forms\Components\Hidden::make('organization_id')
                ->default(fn () => $user->organization_id),

            Forms\Components\Section::make('Ticket Details')
                ->description('Manage guest access and event tier assignment.')
                ->schema([
                    Forms\Components\Select::make('event_id')
                        ->relationship('event', 'name', modifyQueryUsing: fn (Builder $query) =>
                            $isSuperAdmin ? $query : $query->where('organization_id', $user->organization_id)
                        )
                        ->required()->live()->hidden(fn ($record) => $record !== null)
                        ->default(fn () => $isSuperAdmin ? null : Event::where('organization_id', $user->organization_id)->orderBy('created_at', 'desc')->value('id'))
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('event_tier_id', null)),

                    Forms\Components\Placeholder::make('event_name_label')
                        ->label('Event')
                        ->content(fn ($record) => $record?->event?->name)
                        ->visible(fn ($record) => $record !== null),

                    Forms\Components\Select::make('client_id')
                        ->relationship('client', 'full_name', modifyQueryUsing: fn (Builder $query) =>
                            $isSuperAdmin ? $query : $query->where('organization_id', $user->organization_id)
                        )
                        ->required()
                        ->searchable()
                        ->preload()
                        ->hidden(fn ($record) => $record !== null)
                        ->createOptionForm([
                            Forms\Components\TextInput::make('full_name')->required()->label('Full Name'),
                            Forms\Components\TextInput::make('phone')->tel()->nullable()->label('Phone'),
                            Forms\Components\TextInput::make('email')->email()->nullable()->label('Email'),
                            Forms\Components\Hidden::make('organization_id')->default($user->organization_id),
                            Forms\Components\Hidden::make('status')->default('active'),
                        ])
                        ->createOptionUsing(function (array $data) use ($user) {
                            $client = null;
                            if (!empty($data['phone'])) {
                                $client = Client::where('phone', $data['phone'])
                                    ->where('organization_id', $user->organization_id)->first();
                            }
                            if (!$client && !empty($data['email'])) {
                                $client = Client::where('email', $data['email'])
                                    ->where('organization_id', $user->organization_id)->first();
                            }
                            if (!$client) {
                                $client = Client::create([
                                    'full_name'       => $data['full_name'],
                                    'phone'           => $data['phone'] ?? null,
                                    'email'           => $data['email'] ?? null,
                                    'organization_id' => $user->organization_id,
                                    'status'          => 'active',
                                ]);
                            }
                            return $client->id;
                        })
                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->full_name}" . ($record->phone ? " • {$record->phone}" : '')),

                    Forms\Components\Placeholder::make('client_name_label')
                        ->label('Guest')
                        ->content(fn ($record) => $record?->client?->full_name)
                        ->visible(fn ($record) => $record !== null),

                    Forms\Components\Select::make('event_tier_id')
                        ->label('Access Tier')
                        ->relationship('tier', 'tier_name', modifyQueryUsing: fn (Builder $query, Forms\Get $get) =>
                            $query->when($get('event_id'), fn ($q) => $q->where('event_id', $get('event_id')))
                        )
                        ->required()->live()->hidden(fn ($record) => $record !== null)
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            if ($state) {
                                $tier = EventTier::find($state);
                                if ($tier) $set('amount', $tier->price);
                            }
                        }),

                    Forms\Components\Placeholder::make('tier_name_label')
                        ->label('Access Tier')
                        ->content(fn ($record) => $record?->tier?->tier_name)
                        ->visible(fn ($record) => $record !== null),

                    Forms\Components\TextInput::make('amount')
                        ->numeric()->prefix(config('constants.currency.symbol'))->required()->readOnly()
                        ->hidden(fn ($record) => $record !== null),

                    Forms\Components\Placeholder::make('amount_label')
                        ->label('Costing')
                        ->content(fn ($record) => $record?->is_complimentary
                            ? '🎁 Complimentary'
                            : config('constants.currency.symbol') . ' ' . number_format($record?->amount ?? 0, 2)
                        )
                        ->visible(fn ($record) => $record !== null),
                ])->columns(2),

            Forms\Components\Section::make('Complimentary Ticket Details')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Placeholder::make('issued_by')
                            ->label('Issued By')
                            ->content(fn ($record) => $record->complimentaryIssuedBy?->name ?? 'N/A'),
                        Forms\Components\Placeholder::make('reason')
                            ->label('Reason')
                            ->content(fn ($record) => $record->complimentary_reason ?? 'No reason provided'),
                    ]),
                ])
                ->visible(fn ($record) => $record?->is_complimentary ?? false),

            Forms\Components\Section::make('Payment & Access Management')
                ->visible(fn ($record) => $record !== null)
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\ToggleButtons::make('payment_status')
                            ->options(config('constants.payment_statuses'))
                            ->inline()
                            ->colors(['pending' => 'warning', 'partial' => 'info', 'completed' => 'success', 'failed' => 'danger']),
                        Forms\Components\ToggleButtons::make('status')
                            ->options(['active' => 'Active', 'checked_in' => 'Checked In', 'refunded' => 'Refunded', 'void' => 'Void'])
                            ->inline()
                            ->colors(['active' => 'info', 'checked_in' => 'success', 'refunded' => 'danger']),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $user         = auth()->user();
        $isSuperAdmin = $user?->isSuperAdmin();
        $org          = $user?->organization;

        // Check if org has bulk_upload on ANY of their active packages
        // (event-level check happens inside the action form itself)
        $canBulkUpload = $isSuperAdmin || ($org?->canUseFeature('bulk_upload') ?? false);

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Guest')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->description(fn ($record) =>
                        $record->ticket_number . " • " .
                        $record->tier->tier_name .
                        ($record->is_complimentary ? " 🎁 COMP" : "")
                    )->wrap(),

                Tables\Columns\TextColumn::make('voucher_code')
                    ->label('Entry Code')
                    ->copyable()
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('amount')
                    ->money(config('constants.currency.code'))
                    ->weight(FontWeight::Black)
                    ->color('primary')
                    ->alignment(Alignment::Right)
                    ->visible(fn ($record) => !$record?->is_complimentary),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn ($state, $record) => $record->is_complimentary ? 'Complimentary' : ucfirst($state))
                    ->color(fn ($state, $record) => $record->is_complimentary ? 'gray' : match ($state) {
                        'pending'   => 'warning',
                        'completed' => 'success',
                        'failed'    => 'danger',
                        default     => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Access')
                    ->badge()
                    ->icons(['heroicon-m-ticket' => 'active', 'heroicon-m-check-badge' => 'checked_in'])
                    ->color(fn ($state) => match ($state) {
                        'active'     => 'info',
                        'checked_in' => 'success',
                        'refunded'   => 'danger',
                        default      => 'gray',
                    }),
            ])
            ->headerActions([

                // ── ISSUE COMPLIMENTARY TICKET ────────────────────────
                Tables\Actions\Action::make('issue_complimentary')
                    ->label('Issue Comp Ticket')
                    ->icon('heroicon-o-gift')
                    ->color('warning')
                    ->button()
                    ->form([
                        Forms\Components\Section::make('Event & Tier')
                            ->schema([
                                Forms\Components\Select::make('event_id')
                                    ->label('Event')
                                    ->options(fn () => Event::where('status', 'published')
                                        ->when(!$isSuperAdmin, fn ($q) => $q->where('organization_id', $user->organization_id))
                                        ->pluck('name', 'id'))
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn ($set) => $set('tier_id', null))
                                    ->columnSpan(2),

                                // Package comp status for selected event
                                Forms\Components\Placeholder::make('comp_status')
                                    ->label('')
                                    ->content(function (Forms\Get $get) use ($isSuperAdmin) {
                                        if ($isSuperAdmin) return null;
                                        $package = static::packageForEvent($get('event_id'));
                                        if (!$package) return null;

                                        $remaining = $package->remaining_comp_tickets;

                                        if ($remaining <= 0) {
                                            return new HtmlString("
                                                <div class='p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700'>
                                                    ⚠️ <strong>No comp tickets remaining</strong> on this event's package.
                                                    " . ($package->package_type === 'starter'
                                                        ? static::upgradeHint('🔒')->toHtml()
                                                        : '') . "
                                                </div>
                                            ");
                                        }

                                        $color = $remaining <= 3 ? 'orange' : 'green';
                                        return new HtmlString("
                                            <div class='p-3 bg-{$color}-50 border border-{$color}-200 rounded-lg text-sm text-{$color}-700'>
                                                🎁 <strong>{$remaining}</strong> comp ticket(s) remaining on this event's package.
                                            </div>
                                        ");
                                    })
                                    ->visible(fn (Forms\Get $get) => (bool) $get('event_id') && !$isSuperAdmin)
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('tier_id')
                                    ->label('Ticket Tier')
                                    ->options(fn (Forms\Get $get) => EventTier::where('event_id', $get('event_id'))->pluck('tier_name', 'id'))
                                    ->required()
                                    ->disabled(fn (Forms\Get $get) => !$get('event_id'))
                                    ->columnSpan(2),
                            ])->columns(2),

                        Forms\Components\Section::make('Guest Info')
                            ->schema([
                                Forms\Components\TextInput::make('full_name')->required(),
                                Forms\Components\TextInput::make('email')->email(),
                                Forms\Components\TextInput::make('phone')->tel()->prefix('+266'),
                                Forms\Components\Toggle::make('has_whatsapp')->label('Send via WhatsApp')->default(true),
                            ])->columns(2),

                        Forms\Components\Textarea::make('reason')->label('Reason for Comp')->rows(2),
                    ])
                    ->action(function (array $data) use ($user, $isSuperAdmin) {
                        // Gate check — verify comp tickets still available
                        if (!$isSuperAdmin) {
                            $package = static::packageForEvent($data['event_id']);
                            if ($package && $package->remaining_comp_tickets <= 0) {
                                Notification::make()
                                    ->title('No Comp Tickets Remaining')
                                    ->body('This event\'s package has no complimentary tickets left.')
                                    ->danger()
                                    ->send();
                                return;
                            }
                        }

                        DB::beginTransaction();
                        try {
                            $client = null;

                            if (!empty($data['phone'])) {
                                $client = Client::where('phone', $data['phone'])
                                    ->where('organization_id', $user->organization_id)->first();
                            }
                            if (!$client && !empty($data['email'])) {
                                $client = Client::where('email', $data['email'])
                                    ->where('organization_id', $user->organization_id)->first();
                            }
                            if (!$client) {
                                $client = Client::create([
                                    'full_name'       => $data['full_name'],
                                    'phone'           => $data['phone'] ?? null,
                                    'email'           => $data['email'] ?? null,
                                    'organization_id' => $user->organization_id,
                                ]);
                            } else {
                                $client->update(['full_name' => $data['full_name']]);
                            }

                            $ticket = Ticket::create([
                                'event_id'           => $data['event_id'],
                                'client_id'          => $client->id,
                                'event_tier_id'      => $data['tier_id'],
                                'created_by'         => $user->id,
                                'is_complimentary'   => true,
                                'amount'             => 0,
                                'has_whatsapp'       => $data['has_whatsapp'] ?? false,
                                'preferred_delivery' => $data['has_whatsapp'] ? 'both' : 'email',
                            ]);

                            $ticket->markAsComplimentary($user->id, $data['reason'] ?? 'Admin Issued');
                            $ticket->generateQrCode();

                            // Increment comp counter on the event's package
                            if (!$isSuperAdmin) {
                                $package = static::packageForEvent($data['event_id']);
                                $package?->incrementCompTicketsUsed();
                            }

                            dispatch(fn () => $ticket->autoDeliverTicket())->afterResponse();

                            DB::commit();
                            Notification::make()->title('Comp Ticket Issued')->success()->send();

                        } catch (\Exception $e) {
                            DB::rollBack();
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),

                // ── BULK IMPORT ───────────────────────────────────────
                Tables\Actions\Action::make('bulk_import')
                    ->label(fn () => $canBulkUpload ? 'Bulk Import' : '🔒 Bulk Import')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color($canBulkUpload ? 'info' : 'gray')
                    ->button()
                    ->modalHeading('Bulk Import Tickets')
                    ->modalDescription(
                        $canBulkUpload
                            ? 'Upload a CSV/Excel file to create multiple tickets at once'
                            : null
                    )
                    ->modalWidth('2xl')
                    // If no bulk_upload feature at all — show upgrade prompt instead of form
                    ->modalContent(fn () => !$canBulkUpload
                        ? new HtmlString('
                            <div class="p-6 text-center space-y-4">
                                <div class="text-4xl">🔒</div>
                                <p class="font-semibold text-gray-900 dark:text-white">Bulk Import is not included in your package</p>
                                <p class="text-sm text-gray-500">Upgrade to Standard or Professional to import multiple tickets at once via CSV or Excel.</p>
                                <button type="button"
                                    onclick="window.dispatchEvent(new CustomEvent(\'open-upgrade-modal\')); document.querySelector(\'[data-modal-close]\')?.click()"
                                    class="inline-flex items-center px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-lg transition-colors">
                                    Upgrade Your Package
                                </button>
                            </div>
                        ')
                        : null
                    )
                    ->modalSubmitAction(fn () => $canBulkUpload ? null : false)
                    ->form($canBulkUpload ? [
                        Forms\Components\Section::make('Event & Tier Selection')
                            ->schema([
                                Forms\Components\Select::make('event_id')
                                    ->label('Event')
                                    ->options(fn () => Event::where('status', 'published')
                                        ->when(!$isSuperAdmin, fn ($q) => $q->where('organization_id', $user->organization_id))
                                        ->pluck('name', 'id'))
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn ($set) => $set('tier_id', null)),

                                Forms\Components\Select::make('tier_id')
                                    ->label('Ticket Tier')
                                    ->options(fn (Forms\Get $get) =>
                                        EventTier::where('event_id', $get('event_id'))->pluck('tier_name', 'id')
                                    )
                                    ->required()
                                    ->disabled(fn (Forms\Get $get) => !$get('event_id'))
                                    ->helperText('All imported tickets will be assigned to this tier'),
                            ])->columns(2),

                        Forms\Components\Section::make('Ticket Type')
                            ->schema([
                                Forms\Components\Toggle::make('is_complimentary')
                                    ->label('Mark all as Complimentary Tickets')
                                    ->default(true)
                                    ->helperText('If enabled, all tickets will be free and auto-approved')
                                    ->live(),

                                Forms\Components\Textarea::make('reason')
                                    ->label('Reason for Complimentary')
                                    ->visible(fn (Forms\Get $get) => $get('is_complimentary'))
                                    ->default('Bulk import - complimentary tickets')
                                    ->rows(2),
                            ])->columns(1),

                        // Package status — now scoped to the selected event's package
                        Forms\Components\Section::make('Package Status')
                            ->schema([
                                Forms\Components\Placeholder::make('package_status')
                                    ->label('')
                                    ->content(function (Forms\Get $get) use ($isSuperAdmin) {
                                        if ($isSuperAdmin) return null;

                                        $package = static::packageForEvent($get('event_id'));

                                        if (!$package) {
                                            return new HtmlString('
                                                <div class="p-3 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-500">
                                                    Select an event above to see package status.
                                                </div>
                                            ');
                                        }

                                        if (!$package->hasFeature('bulk_upload')) {
                                            return new HtmlString(
                                                '<div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-700">
                                                    🔒 This event\'s package does not include bulk upload. ' .
                                                static::upgradeHint('')->toHtml() .
                                                '</div>'
                                            );
                                        }

                                        if ($package->status === 'exhausted') {
                                            return new HtmlString("
                                                <div class='p-3 bg-orange-50 border border-orange-200 rounded-lg text-sm text-orange-700'>
                                                    ⚠️ <strong>Package Exhausted</strong> — overage rate applies: <strong>M" . number_format($package->overage_ticket_rate, 2) . "</strong> per ticket.
                                                </div>
                                            ");
                                        }

                                        $remaining = $package->remaining_tickets;
                                        $color     = $remaining < 20 ? 'orange' : 'green';
                                        return new HtmlString("
                                            <div class='p-3 bg-{$color}-50 border border-{$color}-200 rounded-lg text-sm text-{$color}-700'>
                                                ✅ <strong>{$remaining}</strong> ticket(s) remaining on this event's package.
                                            </div>
                                        ");
                                    }),
                            ])
                            ->visible(fn () => !$isSuperAdmin),

                        Forms\Components\Section::make('Upload File')
                            ->schema([
                                FileUpload::make('file')
                                    ->label('CSV/Excel File')
                                    ->acceptedFileTypes([
                                        'text/csv',
                                        'text/plain',
                                        'application/vnd.ms-excel',
                                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                    ])
                                    ->maxSize(5120)
                                    ->required()
                                    ->helperText('Upload a CSV or Excel file with columns: full_name, phone, email (optional), has_whatsapp (optional)'),

                                Forms\Components\Placeholder::make('template_download')
                                    ->label('Need a template?')
                                    ->content(new HtmlString('
                                        <a href="/download-bulk-ticket-template"
                                           class="text-blue-600 hover:underline font-medium"
                                           download>
                                            📥 Download CSV Template
                                        </a>
                                    ')),
                            ]),

                        Forms\Components\Section::make('Important Notes')
                            ->schema([
                                Forms\Components\Placeholder::make('notes')
                                    ->content(new HtmlString('
                                        <ul class="text-sm space-y-1 text-gray-600">
                                            <li>• <strong>Required columns:</strong> full_name, phone</li>
                                            <li>• <strong>Optional columns:</strong> email, has_whatsapp</li>
                                            <li>• Phone numbers will be auto-formatted to +266 format</li>
                                            <li>• Duplicate phone numbers will be skipped</li>
                                            <li>• Clients will be created if they don\'t exist</li>
                                            <li>• QR codes will be generated automatically</li>
                                            <li>• Complimentary tickets will be delivered immediately</li>
                                        </ul>
                                    ')),
                            ])
                            ->collapsible(),
                    ] : [])
                    ->action(function (array $data) use ($user, $isSuperAdmin) {
                        if (!$canBulkUpload) return;

                        // Event-level package gate
                        if (!$isSuperAdmin) {
                            $package = static::packageForEvent($data['event_id']);
                            if ($package && !$package->hasFeature('bulk_upload')) {
                                Notification::make()
                                    ->title('Feature Not Available')
                                    ->body('Bulk import is not included in this event\'s package.')
                                    ->danger()
                                    ->send();
                                return;
                            }
                        }

                        try {
                            $filePath = storage_path('app/public/' . $data['file']);

                            $import = new TicketsImport(
                                eventId:        $data['event_id'],
                                tierId:         $data['tier_id'],
                                isComplimentary: $data['is_complimentary'] ?? false,
                                reason:         $data['reason'] ?? null,
                                organizationId: $user->organization_id,
                                createdBy:      $user->id
                            );

                            Excel::import($import, $filePath);

                            $message = "✅ Successfully created {$import->successCount} ticket(s)";
                            if ($import->errorCount > 0) {
                                $message .= "\n⚠️ {$import->errorCount} row(s) had errors";
                                $errorDetails = collect($import->errors)->take(5)
                                    ->map(fn ($err) => "Row {$err['row']}: {$err['error']}")->join("\n");
                                $message .= "\n\n" . $errorDetails;
                                if ($import->errorCount > 5) {
                                    $message .= "\n... and " . ($import->errorCount - 5) . " more errors";
                                }
                            }

                            Notification::make()
                                ->title('Bulk Import Complete')
                                ->body($message)
                                ->success()
                                ->duration(10000)
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Import Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Event')
                    ->options(function () {
                        $user = auth()->user();
                        return Event::when(!$user->isSuperAdmin(), fn ($q) => $q->where('organization_id', $user->organization_id))
                            ->orderBy('event_date', 'desc')
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Payment')
                    ->options(config('constants.payment_statuses')),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Access')
                    ->options([
                        'active'     => 'Active',
                        'checked_in' => 'Checked In',
                        'refunded'   => 'Refunded',
                        'void'       => 'Void',
                    ]),

                Tables\Filters\TernaryFilter::make('is_complimentary')
                    ->label('Complimentary')
                    ->placeholder('All tickets')
                    ->trueLabel('Comp only')
                    ->falseLabel('Paid only'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve_payment')
                    ->label('Approve')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->button()
                    ->visible(fn ($record) =>
                        ($record->payment_status === 'pending' || $record->payment_status === 'partial')
                        && auth()->user()?->can('approve_payment')
                        && $record->hasPendingPayments()
                    )
                    ->form(fn ($record) => static::getOriginalApproveForm($record))
                    ->action(fn (Ticket $record, array $data) => static::handleOriginalApproval($record, $data)),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('preview_pass')
                        ->label('View Pass')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn ($record) => route('ticket.download', $record->qr_code))
                        ->openUrlInNewTab(),

                    Tables\Actions\Action::make('copy_link')
                        ->label('Share Ticket')
                        ->icon('heroicon-o-share')
                        ->modalHeading('Share Access Pass')
                        ->modalWidth('md')
                        ->modalContent(fn ($record) => view('filament.modals.ticket-link', [
                            'ticket' => $record,
                            'link'   => route('ticket.download', $record->qr_code),
                        ]))
                        ->modalSubmitAction(false),

                    Tables\Actions\Action::make('resend_whatsapp')
                        ->label('Resend WhatsApp')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('success')
                        ->visible(fn ($record) => $record->payment_status === 'completed' && $record->has_whatsapp)
                        ->action(fn ($record) => app(\App\Services\TicketDeliveryService::class)->deliver($record)),

                    Tables\Actions\EditAction::make()->slideOver(),
                    Tables\Actions\DeleteAction::make(),
                ])
                ->icon('heroicon-m-ellipsis-vertical')
                ->color('gray')
                ->button()
                ->label('Options'),
            ]);
    }

    protected static function getOriginalApproveForm($record): array
    {
        $pendingPayment = $record->payments()->pending()->latest()->first();
        if (!$pendingPayment) return [];

        $paymentOptions = OrganizationPaymentMethod::where('organization_id', $record->event->organization_id)
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(fn ($m) => [
                $m->payment_method => config('constants.payment_methods.' . $m->payment_method . '.label', $m->payment_method)
            ])
            ->toArray();

        return [
            Forms\Components\Section::make('Confirmation Details')->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Placeholder::make('pay')
                        ->label('To Approve')
                        ->content('M ' . number_format($pendingPayment->amount, 2)),
                    Forms\Components\ToggleButtons::make('target_ticket_status')
                        ->label('Set Ticket Status To')
                        ->options(config('constants.payment_statuses'))
                        ->default('completed')
                        ->inline()
                        ->required()
                        ->colors(['pending' => 'warning', 'partial' => 'info', 'completed' => 'success']),
                ]),
            ]),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Select::make('payment_method')
                    ->options($paymentOptions)
                    ->default($pendingPayment->payment_method)
                    ->required(),
                Forms\Components\TextInput::make('payment_reference')
                    ->default($pendingPayment->payment_reference)
                    ->required(),
            ]),
        ];
    }

    protected static function handleOriginalApproval(Ticket $record, array $data): void
    {
        $pendingPayment = $record->payments()->pending()->latest()->first();
        if (!$pendingPayment) return;

        DB::beginTransaction();
        try {
            $pendingPayment->update([
                'status'            => 'approved',
                'payment_method'    => $data['payment_method'],
                'payment_reference' => $data['payment_reference'],
                'approved_by'       => auth()->id(),
                'approved_at'       => now(),
            ]);

            $record->update([
                'payment_status' => 'completed',
                'status'         => 'active',
            ]);

            if ($record->payments()->approved()->count() === 1) {
                $record->tier->increment('quantity_sold');
            }

            if ($record->client->email) {
                dispatch(new SendTicketApprovedEmail($record->id))->afterResponse();
            }

            if ($record->has_whatsapp && $record->client->phone) {
                dispatch(fn () => app(\App\Services\TicketDeliveryService::class)->deliver($record))->afterResponse();
            }

            Notification::make()->title('Payment Approved')->success()->send();
            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->title('Approval Error')->body($e->getMessage())->danger()->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'edit'   => Pages\EditTicket::route('/{record}/edit'),
        ];
    }
}