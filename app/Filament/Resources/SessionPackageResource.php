<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\OrganizationCluster;
use App\Models\Organization;
use App\Models\SessionPackage;
use App\Services\SessionPackageService;
use App\Support\SessionPackageDefinition;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Enums\FontWeight;

class SessionPackageResource extends Resource
{
    protected static ?string $model = SessionPackage::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $cluster = OrganizationCluster::class;
    protected static ?string $navigationLabel = 'Session Plan';
    protected static ?string $modelLabel = 'Session Package';
    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool { return false; }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->isSuperAdmin() || $user->organization_id !== null;
    }

    public static function getEloquentQuery(): Builder
    {
        $user  = auth()->user();
        $query = parent::getEloquentQuery();
        if ($user->isSuperAdmin()) return $query;
        if (!$user->organization_id) return $query->whereRaw('1 = 0');
        return $query->where('organization_id', $user->organization_id);
    }

    // ── Shared bits between the two header actions ─────────────────────

    private static function organizationField(): Forms\Components\Component
    {
        return Forms\Components\Select::make('organization_id')
            ->label('Organization')
            ->options(fn () => Organization::pluck('name', 'id'))
            ->searchable()
            ->required()
            ->visible(fn () => auth()->user()->isSuperAdmin())
            ->default(fn () => auth()->user()->organization_id);
    }

    private static function resolveOrganizationId(array $data): int
    {
        return auth()->user()->isSuperAdmin()
            ? (int) $data['organization_id']
            : auth()->user()->organization_id;
    }

    public static function table(Table $table): Table
    {
        $tierOptions = collect(SessionPackageDefinition::tiers())
            ->mapWithKeys(fn ($def, $tier) => [$tier => $def['label']])
            ->toArray();

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organization')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),

                Tables\Columns\TextColumn::make('tier')
                    ->label('Plan')
                    ->formatStateUsing(fn ($state) => SessionPackageDefinition::get($state)['label'] ?? ucfirst($state))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'free'       => 'gray',
                        'payg'       => 'info',
                        'team'       => 'success',
                        'business'   => 'warning',
                        'enterprise' => 'danger',
                        default      => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => strtoupper($state))
                    ->color(fn (string $state): string => match ($state) {
                        'active'    => 'success',
                        'exhausted' => 'danger',
                        'expired'   => 'gray',
                        'cancelled' => 'gray',
                        default     => 'gray',
                    }),

                Tables\Columns\TextColumn::make('sessions_status')
                    ->label('Sessions')
                    ->getStateUsing(fn ($record) => "{$record->sessions_used} / {$record->sessions_included}")
                    ->icon('heroicon-m-video-camera')
                    ->badge()
                    ->color(fn ($record) => $record->sessions_used < $record->sessions_included ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('whatsapp_status')
                    ->label('WhatsApp')
                    ->getStateUsing(fn ($record) => "{$record->whatsapp_used} / {$record->whatsapp_included}")
                    ->icon('heroicon-m-chat-bubble-left-right')
                    ->badge()
                    ->color(fn ($record) => $record->whatsapp_used < $record->whatsapp_included ? 'info' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('period_end')
                    ->label('Renews / Expires')
                    ->formatStateUsing(fn ($state) => $state?->format('d M Y') ?? 'Never expires')
                    ->sortable(),

                Tables\Columns\TextColumn::make('price_paid')
                    ->label('Paid')
                    ->money('LSL')
                    ->weight(FontWeight::Black),
            ])
            ->headerActions([
                Tables\Actions\Action::make('change_plan')
                    ->label('Change Plan')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->button()
                    ->visible(fn () => auth()->user()->isSuperAdmin())
                    ->modalWidth('lg')
                    ->modalHeading('Change Session Plan')
                    ->form([
                        static::organizationField(),

                        Forms\Components\Select::make('tier')
                            ->label('Plan')
                            ->options($tierOptions)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                $def = SessionPackageDefinition::get($state);
                                if (!$def) return;
                                $set('price_paid', $def['price']);
                                $set('sessions_included', $def['sessions_included']);
                                $set('whatsapp_included', $def['whatsapp_included']);
                                $set('sms_included', $def['sms_included']);
                            }),

                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('sessions_included')
                                ->label('Sessions Included')
                                ->numeric()->minValue(0)->required(),
                            Forms\Components\TextInput::make('whatsapp_included')
                                ->label('WhatsApp Included')
                                ->numeric()->minValue(0)->required(),
                            Forms\Components\TextInput::make('sms_included')
                                ->label('SMS Included')
                                ->numeric()->minValue(0)->required(),
                        ]),

                        Forms\Components\TextInput::make('price_paid')
                            ->label('Price Paid (M)')
                            ->numeric()->minValue(0)->required(),

                        Forms\Components\Textarea::make('notes')->label('Notes (optional)')->rows(2),
                    ])
                    ->action(function (array $data, SessionPackageService $service) {
                        $service->changePlan(
                            organizationId: static::resolveOrganizationId($data),
                            tier: $data['tier'],
                            sessionsIncluded: (int) $data['sessions_included'],
                            whatsappIncluded: (int) $data['whatsapp_included'],
                            smsIncluded: (int) $data['sms_included'],
                            pricePaid: (float) $data['price_paid'],
                            notes: $data['notes'] ?? null,
                        );

                        Notification::make()->title('Plan Updated')->success()->send();
                    }),

                // ── Self-serve: org admin picks a tier, pays through PayLesotho ──
                Tables\Actions\Action::make('change_plan_online')
                    ->label('Change Plan')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->button()
                    ->visible(fn () => !auth()->user()->isSuperAdmin())
                    ->modalWidth('md')
                    ->modalHeading('Change Session Plan')
                    ->form([
                        Forms\Components\Select::make('tier')
                            ->label('Plan')
                            ->options(collect($tierOptions)->except('free'))
                            ->required()
                            ->live()
                            ->helperText(function (Forms\Get $get) {
                                $tier = $get('tier');
                                $def = $tier ? SessionPackageDefinition::get($tier) : null;
                                return $def ? "M{$def['price']}/month — {$def['sessions_included']} sessions, {$def['whatsapp_included']} WhatsApp, {$def['sms_included']} SMS included" : null;
                            }),
                    ])
                    ->action(function (array $data, \Livewire\Component $livewire) {
                        $livewire->redirect(route('organization.session-plan.payment', [
                            'type' => 'plan',
                            'tier' => $data['tier'],
                        ]));
                    }),

                Tables\Actions\Action::make('add_payg_sessions')
                    ->label('Add PAYG Sessions')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->button()
                    ->visible(fn () => auth()->user()->isSuperAdmin())
                    ->modalWidth('md')
                    ->modalHeading('Add PAYG Sessions')
                    ->form([
                        static::organizationField(),

                        Forms\Components\Select::make('quantity_preset')
                            ->label('Sessions to Add')
                            ->options([
                                1      => '1 session — M150',
                                3      => '3 sessions — M400',
                                10     => '10 sessions — M1,100',
                                20     => '20 sessions — M2,000',
                                'custom' => 'Custom amount',
                            ])
                            ->default(1)
                            ->live()
                            ->required(),

                        Forms\Components\TextInput::make('custom_quantity')
                            ->label('Quantity')
                            ->numeric()->minValue(1)
                            ->visible(fn (Forms\Get $get) => $get('quantity_preset') === 'custom')
                            ->required(fn (Forms\Get $get) => $get('quantity_preset') === 'custom'),

                        Forms\Components\TextInput::make('price_paid')
                            ->label('Price Paid (M)')
                            ->numeric()->minValue(0)
                            ->helperText('Auto-suggested from quantity — override if needed.')
                            ->default(fn (Forms\Get $get) => SessionPackageDefinition::paygBundlePrice(
                                $get('quantity_preset') === 'custom' ? (int) $get('custom_quantity') : (int) $get('quantity_preset')
                            ))
                            ->required(),

                        Forms\Components\Textarea::make('notes')->label('Notes (optional)')->rows(2),
                    ])
                    ->action(function (array $data, SessionPackageService $service) {
                        $quantity = $data['quantity_preset'] === 'custom'
                            ? (int) $data['custom_quantity']
                            : (int) $data['quantity_preset'];

                        $service->addPaygCredits(
                            organizationId: static::resolveOrganizationId($data),
                            quantity: $quantity,
                            pricePaid: (float) ($data['price_paid'] ?? SessionPackageDefinition::paygBundlePrice($quantity)),
                            notes: $data['notes'] ?? null,
                        );

                        Notification::make()->title('PAYG Sessions Added')->success()->send();
                    }),

                // ── Self-serve: org admin picks a quantity, pays through PayLesotho ──
                Tables\Actions\Action::make('add_payg_online')
                    ->label('Add PAYG Sessions')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->button()
                    ->visible(fn () => !auth()->user()->isSuperAdmin())
                    ->modalWidth('md')
                    ->modalHeading('Add PAYG Sessions')
                    ->form([
                        Forms\Components\Select::make('quantity_preset')
                            ->label('Sessions to Add')
                            ->options([
                                1        => '1 session — M150',
                                3        => '3 sessions — M400',
                                10       => '10 sessions — M1,100',
                                20       => '20 sessions — M2,000',
                                'custom' => 'Custom amount',
                            ])
                            ->default(1)
                            ->live()
                            ->required(),

                        Forms\Components\TextInput::make('custom_quantity')
                            ->label('Quantity')
                            ->numeric()->minValue(1)
                            ->visible(fn (Forms\Get $get) => $get('quantity_preset') === 'custom')
                            ->required(fn (Forms\Get $get) => $get('quantity_preset') === 'custom'),
                    ])
                    ->action(function (array $data, \Livewire\Component $livewire) {
                        $quantity = $data['quantity_preset'] === 'custom'
                            ? (int) $data['custom_quantity']
                            : (int) $data['quantity_preset'];

                        $livewire->redirect(route('organization.session-plan.payment', [
                            'type'     => 'payg',
                            'quantity' => $quantity,
                        ]));
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\SessionPackageResource\Pages\ListSessionPackages::route('/'),
        ];
    }
}
