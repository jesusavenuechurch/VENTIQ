<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\OrganizationCluster;
use App\Models\OrganizationPackage;
use App\Models\AgentEarning;
use App\Models\Organization;
use App\Support\PackageDefinition;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Alignment;

class PackagePurchaseResource extends Resource
{
    protected static ?string $model = OrganizationPackage::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $cluster = OrganizationCluster::class;
    protected static ?string $navigationLabel = 'Subscription';
    protected static ?string $modelLabel = 'Package';
    protected static ?int $navigationSort = 4;

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

    // ── Shared purchase form & action ─────────────────────────────────
    // Used in both the header action and the row upgrade action so logic
    // is never duplicated.

    private static function purchaseForm(array $purchasableTypes, array $purchasableDescriptions, array $paymentMethods): array
    {
        return [
            Forms\Components\Radio::make('package_type')
                ->label('Select Package')
                ->options($purchasableTypes)
                ->descriptions($purchasableDescriptions)
                ->default('standard')
                ->columns(3)
                ->required()
                ->live(),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Section::make('Package Details')
                    ->columnSpan(1)
                    ->schema([
                        Forms\Components\Placeholder::make('summary')
                            ->label('')
                            ->content(function (Forms\Get $get) {
                                $type = $get('package_type');
                                if (!$type) return new HtmlString(
                                    '<p class="text-xs italic text-gray-400">Select a package above...</p>'
                                );

                                $def      = PackageDefinition::get($type);
                                $features = collect($def['features'])
                                    ->filter(fn ($v) => $v)
                                    ->keys()
                                    ->map(fn ($f) => '✓ ' . ucwords(str_replace('_', ' ', $f)))
                                    ->implode('<br>');

                                return new HtmlString("
                                    <div class='space-y-3'>
                                        <div class='p-4 bg-gray-50 dark:bg-white/5 rounded-2xl border border-gray-100 dark:border-white/10 space-y-2'>
                                            <div class='flex justify-between'>
                                                <span class='text-[10px] font-bold uppercase text-gray-400'>Tickets</span>
                                                <span class='font-bold'>{$def['tickets']}</span>
                                            </div>
                                            <div class='flex justify-between'>
                                                <span class='text-[10px] font-bold uppercase text-gray-400'>Comp Tickets</span>
                                                <span class='font-bold'>{$def['comp_tickets']}</span>
                                            </div>
                                            <div class='flex justify-between'>
                                                <span class='text-[10px] font-bold uppercase text-gray-400'>Scanners</span>
                                                <span class='font-bold'>{$def['max_scanners']}</span>
                                            </div>
                                            <div class='flex justify-between'>
                                                <span class='text-[10px] font-bold uppercase text-gray-400'>Team Members</span>
                                                <span class='font-bold'>{$def['max_users']}</span>
                                            </div>
                                            <div class='pt-2 border-t border-gray-200 dark:border-white/10 flex justify-between items-baseline'>
                                                <span class='text-xs font-black'>TOTAL COST</span>
                                                <span class='text-2xl font-black text-[#F07F22]'>M" . number_format($def['price'], 2) . "</span>
                                            </div>
                                        </div>
                                        <div class='text-xs text-gray-500 leading-relaxed'>{$features}</div>
                                    </div>
                                ");
                            }),
                    ]),

                Forms\Components\Section::make('Payment')
                    ->columnSpan(1)
                    ->schema([
                        Forms\Components\Radio::make('payment_mode')
                            ->label('How would you like to pay?')
                            ->options([
                                'online' => '💳 Pay Online (M-Pesa / EcoCash / Card)',
                                'manual' => '🏦 Manual Payment (Bank / Cash)',
                            ])
                            ->descriptions([
                                'online' => 'Instant. You\'ll be redirected to complete payment securely.',
                                'manual' => 'Pay via bank transfer or cash. Admin will activate your package after confirming payment.',
                            ])
                            ->default('online')
                            ->live()
                            ->required(),

                        Forms\Components\Select::make('payment_method')
                            ->label('Payment Method')
                            ->options($paymentMethods)
                            ->visible(fn (Forms\Get $get) => $get('payment_mode') === 'manual')
                            ->required(fn (Forms\Get $get) => $get('payment_mode') === 'manual'),

                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Reference / Transaction ID')
                            ->visible(fn (Forms\Get $get) => $get('payment_mode') === 'manual')
                            ->required(fn (Forms\Get $get) => $get('payment_mode') === 'manual'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes (optional)')
                            ->rows(2),
                    ]),
            ]),
        ];
    }

    private static function handlePurchase(array $data, \Livewire\Component $livewire): void
    {
        $def = PackageDefinition::get($data['package_type']);

        if ($data['payment_mode'] === 'online') {
            $package = OrganizationPackage::create([
                'organization_id'       => auth()->user()->organization_id,
                'package_type'          => $data['package_type'],
                'price_paid'            => $def['price'],
                'events_included'       => $def['events'],
                'tickets_included'      => $def['tickets'],
                'comp_tickets_included' => $def['comp_tickets'],
                'overage_ticket_rate'   => $def['overage_rate'],
                'status'                => 'pending',
                'purchased_at'          => now(),
                'payment_method'        => 'online',
                'purchased_by'          => auth()->id(),
                'notes'                 => $data['notes'] ?? null,
            ]);

            $livewire->redirect(route('online-payment.package.initiate', [
                'package_id' => $package->id,
            ]));

            return;
        }

        OrganizationPackage::create([
            'organization_id'       => auth()->user()->organization_id,
            'package_type'          => $data['package_type'],
            'price_paid'            => $def['price'],
            'events_included'       => $def['events'],
            'tickets_included'      => $def['tickets'],
            'comp_tickets_included' => $def['comp_tickets'],
            'overage_ticket_rate'   => $def['overage_rate'],
            'status'                => 'pending',
            'purchased_at'          => now(),
            'payment_method'        => $data['payment_method'],
            'payment_reference'     => $data['payment_reference'],
            'notes'                 => $data['notes'] ?? null,
            'purchased_by'          => auth()->id(),
        ]);

        Notification::make()
            ->title('Purchase Submitted')
            ->body('Your package is pending approval. You will be notified once activated.')
            ->success()
            ->send();
    }

    public static function table(Table $table): Table
    {
        $purchasableTypes = collect(PackageDefinition::all())
            ->except(['free_trial', 'enterprise'])
            ->map(fn ($def) => $def['name'])
            ->toArray();

        $purchasableDescriptions = collect(PackageDefinition::all())
            ->except(['free_trial', 'enterprise'])
            ->map(fn ($def) => $def['description'])
            ->toArray();

        $paymentMethods = collect(config('constants.payment_methods'))
            ->filter(fn ($m) => !in_array($m['label'], ['Free', 'Online Payment']))
            ->mapWithKeys(fn ($m, $key) => [$key => $m['label']])
            ->toArray();

        return $table
            ->defaultSort(function (Builder $query) {
                if (auth()->user()?->isSuperAdmin()) {
                    return $query->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->orderBy('created_at', 'desc');
                }
                return $query->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")->orderBy('purchased_at', 'desc');
            })
            ->columns([
                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organization')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),

                Tables\Columns\TextColumn::make('package_type')
                    ->label('Package')
                    ->formatStateUsing(fn ($record) => $record->is_free_trial
                        ? '🎁 FREE TRIAL'
                        : strtoupper($record->display_name)
                    )
                    ->badge()
                    ->color(fn ($record) => $record->is_free_trial ? 'success' : match($record->package_type) {
                        'starter'      => 'info',
                        'standard'     => 'success',
                        'professional' => 'warning',
                        'enterprise'   => 'danger',
                        default        => 'gray',
                    }),

                Tables\Columns\TextColumn::make('events_status')
                    ->label('Slots')
                    ->getStateUsing(fn ($record) => "{$record->events_used} / {$record->events_included}")
                    ->icon('heroicon-m-cpu-chip')
                    ->badge()
                    ->color(fn ($record) => $record->events_used < $record->events_included ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('tickets_status')
                    ->label('Throughput')
                    ->html()
                    ->getStateUsing(function ($record) {
                        $rawPct    = $record->tickets_included > 0
                            ? ($record->tickets_used / $record->tickets_included) * 100
                            : 0;
                        $visualPct = min($rawPct, 100);
                        $color     = $rawPct >= 100 ? '#ef4444' : ($rawPct >= 80 ? '#F07F22' : '#10B981');
                        return new HtmlString("
                            <div class='w-full max-w-[200px] py-1'>
                                <div class='flex justify-between items-end mb-1'>
                                    <span class='text-[9px] font-black text-gray-400 uppercase tracking-widest'>Usage</span>
                                    <span class='text-[10px] font-bold' style='color:{$color}'>" . number_format($rawPct, 0) . "%</span>
                                </div>
                                <div class='w-full bg-gray-100 dark:bg-white/5 rounded-full h-1 overflow-hidden'>
                                    <div class='h-full rounded-full transition-all duration-1000' style='width:{$visualPct}%;background:{$color};'></div>
                                </div>
                            </div>
                        ");
                    }),

                Tables\Columns\TextColumn::make('scanners_status')
                    ->label('Scanners')
                    ->getStateUsing(fn ($record) => "{$record->scanners_used} / {$record->max_scanners}")
                    ->icon('heroicon-m-qr-code')
                    ->badge()
                    ->color(fn ($record) => $record->scanners_used < $record->max_scanners ? 'info' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => strtoupper($state))
                    ->color(fn (string $state): string => match ($state) {
                        'active'    => 'success',
                        'pending'   => 'warning',
                        'exhausted' => 'danger',
                        'expired'   => 'gray',
                        default     => 'gray',
                    }),

                Tables\Columns\TextColumn::make('price_paid')
                    ->label('Value')
                    ->money('LSL')
                    ->alignment(Alignment::Right)
                    ->weight(FontWeight::Black),

                Tables\Columns\TextColumn::make('payment_reference')
                    ->label('Verification')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),
            ])
            ->headerActions([
                Tables\Actions\Action::make('start_free_trial')
                    ->label('Start Free Trial')
                    ->icon('heroicon-o-gift')
                    ->color('success')
                    ->button()
                    ->visible(fn () =>
                        !auth()->user()->isSuperAdmin() &&
                        !OrganizationPackage::where('organization_id', auth()->user()->organization_id)->exists()
                    )
                    ->action(function () {
                        OrganizationPackage::createFreeTrialPackage(auth()->user()->organization_id);
                        Notification::make()->title('Free Trial Activated!')->success()->send();
                    }),

                Tables\Actions\Action::make('purchase_package_header')
                    ->label('Buy a Package')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->button()
                    ->visible(fn () => !auth()->user()->isSuperAdmin())
                    ->modalWidth('4xl')
                    ->modalHeading('Purchase a Package')
                    ->form(static::purchaseForm($purchasableTypes, $purchasableDescriptions, $paymentMethods))
                    ->action(fn (array $data, \Livewire\Component $livewire) =>
                        static::handlePurchase($data, $livewire)
                    ),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('upgrade_package')
                        ->label('Upgrade / Buy Another')
                        ->icon('heroicon-o-arrow-trending-up')
                        ->color('primary')
                        ->visible(fn () => !auth()->user()->isSuperAdmin())
                        ->modalWidth('4xl')
                        ->modalHeading('Purchase a Package')
                        ->form(static::purchaseForm($purchasableTypes, $purchasableDescriptions, $paymentMethods))
                        ->action(fn (array $data, \Livewire\Component $livewire) =>
                            static::handlePurchase($data, $livewire)
                        ),

                    Tables\Actions\Action::make('approve_package')
                        ->label('Approve')
                        ->icon('heroicon-m-check-badge')
                        ->color('success')
                        ->visible(fn ($record) => $record->status === 'pending' && auth()->user()?->isSuperAdmin())
                        ->requiresConfirmation()
                        ->action(function (OrganizationPackage $record) {
                            $record->update([
                                'status'       => 'active',
                                'purchased_at' => $record->purchased_at ?? now(),
                            ]);

                            $hasPaidPackage = OrganizationPackage::where('organization_id', $record->organization_id)
                                ->where('id', '!=', $record->id)
                                ->where('is_free_trial', false)
                                ->where('status', 'active')
                                ->exists();

                            if (!$hasPaidPackage) {
                                OrganizationPackage::where('organization_id', $record->organization_id)
                                    ->where('is_free_trial', true)
                                    ->where('status', 'active')
                                    ->update(['status' => 'converted']);
                            }

                            $org = $record->organization;
                            if ($org && $org->agent_id) {
                                $commission = AgentEarning::calculateCommission($record->price_paid);

                                AgentEarning::create([
                                    'agent_id'                => $org->agent_id,
                                    'organization_id'         => $org->id,
                                    'organization_package_id' => $record->id,
                                    'type'                    => 'commission',
                                    'amount'                  => $commission,
                                    'package_price'           => $record->price_paid,
                                    'package_type'            => $record->package_type,
                                    'status'                  => 'approved',
                                    'approved_by'             => auth()->id(),
                                    'approved_at'             => now(),
                                    'notes'                   => "Commission for {$org->name}",
                                ]);

                                $paidCount = Organization::where('agent_id', $org->agent_id)
                                    ->whereHas('activePackages', fn ($q) =>
                                        $q->where('is_free_trial', false)->where('status', 'active')
                                    )
                                    ->count();

                                if ($paidCount > 0 && $paidCount % 5 === 0) {
                                    $tier = AgentEarning::getMilestoneTier($paidCount);
                                    AgentEarning::firstOrCreate(
                                        ['organization_package_id' => $record->id, 'type' => 'milestone_bonus'],
                                        [
                                            'agent_id'            => $org->agent_id,
                                            'type'                => 'milestone_bonus',
                                            'amount'              => AgentEarning::calculateMilestoneBonus($tier),
                                            'milestone_tier'      => $tier,
                                            'milestone_org_count' => $paidCount,
                                            'status'              => 'approved',
                                            'approved_by'         => auth()->id(),
                                            'approved_at'         => now(),
                                        ]
                                    );
                                }
                            }

                            Notification::make()->title('Package Approved')->success()->send();
                        }),

                    Tables\Actions\DeleteAction::make()
                        ->visible(fn () => auth()->user()?->isSuperAdmin()),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\PackagePurchaseResource\Pages\ListPackagePurchases::route('/'),
        ];
    }
}