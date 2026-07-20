<?php

namespace App\Filament\Resources;

use App\Models\Organization;
use App\Models\PaymentSession;
use App\Models\Settlement;
use App\Models\SettlementItem;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Alignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class SettlementResource extends Resource
{
    protected static ?string $model = Settlement::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $cluster = \App\Filament\Clusters\FinanceCluster::class;
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Settlements';

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->isSuperAdmin() || $user->organization_id !== null;
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return auth()->user()?->isSuperAdmin() ?? false; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();
        if ($user?->isSuperAdmin()) return $query;
        return $query->where('organization_id', $user->organization_id);
    }

    public static function table(Table $table): Table
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;

        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No settlements yet')
            ->emptyStateDescription('Online ticket payments will appear here once processed.')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->columns([
                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organisation')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->visible($isSuperAdmin),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('trigger_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', $state)))
                    ->color('gray'),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Tickets')
                    ->counts('items')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('gross_paid')
                    ->label('Collected')
                    ->money('LSL')
                    ->alignment(Alignment::Right)
                    ->toggleable(isToggledHiddenByDefault: !$isSuperAdmin),

                Tables\Columns\TextColumn::make('gateway_fees')
                    ->label('Gateway Fees')
                    ->money('LSL')
                    ->alignment(Alignment::Right)
                    ->color('danger')
                    ->visible($isSuperAdmin)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('amount_received')
                    ->label('Received by Ventiq')
                    ->money('LSL')
                    ->alignment(Alignment::Right)
                    ->weight(FontWeight::Bold)
                    ->visible($isSuperAdmin),

                Tables\Columns\TextColumn::make('amount_owed_to_org')
                    ->label('Amount Due to You')
                    ->money('LSL')
                    ->alignment(Alignment::Right)
                    ->weight(FontWeight::Black)
                    ->color(fn ($record) => $record->isSettled() ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('ventiq_revenue')
                    ->label('Ventiq Revenue')
                    ->money('LSL')
                    ->alignment(Alignment::Right)
                    ->color('success')
                    ->weight(FontWeight::Black)
                    ->visible($isSuperAdmin),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => strtoupper($state))
                    ->color(fn ($state) => match ($state) {
                        'pending' => 'warning',
                        'partial' => 'info',
                        'settled' => 'success',
                        default   => 'gray',
                    }),

                Tables\Columns\TextColumn::make('settled_at')
                    ->label('Settled On')
                    ->dateTime('d M Y')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('settlement_method')
                    ->label('Method')
                    ->formatStateUsing(fn ($state) => $state
                        ? config("constants.payment_methods.{$state}.label", ucfirst($state))
                        : '—'
                    )
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create_settlement')
                    ->label('New Settlement Batch')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->button()
                    ->visible($isSuperAdmin)
                    ->modalWidth('2xl')
                    ->modalHeading('Create Settlement Batch')
                    ->form([
                        Forms\Components\Select::make('organization_id')
                            ->label('Organisation')
                            ->options(Organization::whereHas('settlementItems', fn ($q) =>
                                $q->whereNull('settlement_id')
                            )->pluck('name', 'id'))
                            ->required()
                            ->live()
                            ->searchable(),

                        Forms\Components\Placeholder::make('unsettled_summary')
                            ->label('Unsettled Items')
                            ->content(function (Forms\Get $get) {
                                $orgId = $get('organization_id');
                                if (!$orgId) return new HtmlString('<p class="text-xs text-gray-400">Select an organisation above.</p>');

                                $items = SettlementItem::where('organization_id', $orgId)
                                    ->whereNull('settlement_id')->get();

                                if ($items->isEmpty()) return new HtmlString('<p class="text-xs text-gray-400">No unsettled online payments found.</p>');

                                $grossPaid   = $items->sum('gross_paid');
                                $gatewayFees = $items->sum('gateway_fee');
                                $amtReceived = $items->sum('amount_received');
                                $amtOwed     = $items->sum('amount_owed_to_org');
                                $ventiqRev   = $amtReceived - $amtOwed;

                                return new HtmlString("
                                    <div class='space-y-2 text-sm p-4 bg-gray-50 rounded-xl'>
                                        <div class='flex justify-between'><span class='text-gray-500'>Tickets</span><span class='font-bold'>{$items->count()}</span></div>
                                        <div class='flex justify-between'><span class='text-gray-500'>Gross Collected</span><span class='font-bold'>M" . number_format($grossPaid, 2) . "</span></div>
                                        <div class='flex justify-between'><span class='text-gray-500'>Gateway Fees</span><span class='text-red-500 font-bold'>−M" . number_format($gatewayFees, 2) . "</span></div>
                                        <div class='flex justify-between'><span class='text-gray-500'>Received by Ventiq</span><span class='font-bold'>M" . number_format($amtReceived, 2) . "</span></div>
                                        <div class='flex justify-between border-t pt-2 mt-2'><span class='font-bold'>Owed to Org</span><span class='font-black text-orange-500 text-lg'>M" . number_format($amtOwed, 2) . "</span></div>
                                        <div class='flex justify-between'><span class='text-gray-500'>Ventiq Revenue</span><span class='font-black text-green-600'>M" . number_format($ventiqRev, 2) . "</span></div>
                                    </div>
                                ");
                            }),

                        Forms\Components\Select::make('trigger_type')
                            ->label('Settlement Type')
                            ->options(['manual' => 'Manual', 'post_event' => 'Post Event', 'weekly' => 'Weekly', 'monthly' => 'Monthly'])
                            ->default('manual')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $items = SettlementItem::where('organization_id', $data['organization_id'])
                            ->whereNull('settlement_id')->get();

                        if ($items->isEmpty()) {
                            Notification::make()->title('No unsettled items found')->warning()->send();
                            return;
                        }

                        $sessions   = PaymentSession::whereIn('id', $items->pluck('payment_session_id'))->get();
                        $settlement = Settlement::createFromSessions($data['organization_id'], $sessions, $data['trigger_type']);

                        SettlementItem::where('organization_id', $data['organization_id'])
                            ->whereNull('settlement_id')
                            ->update(['settlement_id' => $settlement->id]);

                        Notification::make()
                            ->title('Settlement batch created')
                            ->body('M' . number_format($settlement->amount_owed_to_org, 2) . ' owed to ' . Organization::find($data['organization_id'])->name)
                            ->success()->send();
                    }),

                // Org users — pending balance info button
                Tables\Actions\Action::make('pending_balance_info')
                    ->label(function () {
                        $pending = SettlementItem::where('organization_id', auth()->user()->organization_id)
                            ->whereNull('settlement_id')->sum('amount_owed_to_org');
                        return $pending > 0 ? 'M' . number_format($pending, 2) . ' Pending' : 'No Pending Balance';
                    })
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->button()
                    ->visible(fn () => !$isSuperAdmin)
                    ->modalWidth('md')
                    ->modalHeading('Your Pending Settlement Balance')
                    ->modalContent(function () {
                        $items = SettlementItem::where('organization_id', auth()->user()->organization_id)
                            ->whereNull('settlement_id')->get();

                        if ($items->isEmpty()) return new HtmlString('
                            <div class="p-6 text-center text-gray-400">
                                <p class="text-2xl mb-2">✅</p>
                                <p class="font-bold">No pending balance</p>
                                <p class="text-sm mt-1">All your online ticket revenue has been settled.</p>
                            </div>
                        ');

                        $amtOwed = $items->sum('amount_owed_to_org');
                        $count   = $items->count();

                        return new HtmlString("
                            <div class='p-6 space-y-4'>
                                <div class='text-center p-6 bg-orange-50 rounded-2xl border border-orange-100'>
                                    <p class='text-[10px] font-black text-orange-400 uppercase tracking-widest mb-1'>Ventiq is holding</p>
                                    <p class='text-4xl font-black text-orange-500'>M" . number_format($amtOwed, 2) . "</p>
                                    <p class='text-xs text-orange-400 mt-1'>from {$count} online ticket payment(s)</p>
                                </div>
                                <p class='text-sm text-gray-500 text-center leading-relaxed'>
                                    This amount will be settled to your account per your agreed schedule.
                                    Contact <a href='mailto:support@ventiq.co.ls' class='font-bold text-[#1D4069]'>support@ventiq.co.ls</a> for assistance.
                                </p>
                            </div>
                        ");
                    })
                    ->modalSubmitAction(false),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_settled')
                    ->label('Mark as Settled')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->visible(fn ($record) => $isSuperAdmin && !$record->isSettled())
                    ->modalWidth('lg')
                    ->modalHeading('Confirm Settlement')
                    ->form([
                        Forms\Components\Placeholder::make('amount_due')
                            ->label('Amount to pay org')
                            ->content(fn ($record) => new HtmlString(
                                '<span class="text-2xl font-black text-orange-500">M' . number_format($record->amount_owed_to_org, 2) . '</span>'
                            )),
                        Forms\Components\Select::make('settlement_method')
                            ->label('Payment Method')
                            ->options(collect(config('constants.payment_methods'))
                                ->filter(fn ($m) => !in_array($m['label'], ['Free', 'Online Payment']))
                                ->mapWithKeys(fn ($m, $key) => [$key => $m['label']])->toArray())
                            ->required(),
                        Forms\Components\TextInput::make('settlement_reference')->label('Transaction Reference')->required(),
                        Forms\Components\Textarea::make('notes')->rows(2),
                    ])
                    ->action(function (Settlement $record, array $data) {
                        $record->update([
                            'status'               => 'settled',
                            'settlement_method'    => $data['settlement_method'],
                            'settlement_reference' => $data['settlement_reference'],
                            'notes'                => $data['notes'] ?? null,
                            'settled_at'           => now(),
                            'settled_by'           => auth()->id(),
                        ]);
                        Notification::make()->title('Settlement marked as paid')->success()->send();
                    }),

                Tables\Actions\Action::make('view_items')
                    ->label('View Breakdown')
                    ->icon('heroicon-o-list-bullet')
                    ->color('gray')
                    ->modalWidth('5xl')
                    ->modalHeading(fn ($record) => 'Settlement — ' . $record->created_at->format('d M Y'))
                    ->modalContent(fn ($record) => view('filament.modals.settlement-items', ['settlement' => $record]))
                    ->modalSubmitAction(false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\SettlementResource\Pages\ListSettlements::route('/'),
        ];
    }
}