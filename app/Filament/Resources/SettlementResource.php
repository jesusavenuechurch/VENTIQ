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
    protected static ?string $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Settlements';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin();
    }

    public static function canCreate(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organisation')
                    ->weight(FontWeight::Bold)
                    ->searchable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Tickets')
                    ->counts('items')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('gross_paid')
                    ->label('Gross Collected')
                    ->money('LSL')
                    ->alignment(Alignment::Right),

                Tables\Columns\TextColumn::make('gateway_fees')
                    ->label('Gateway Fees')
                    ->money('LSL')
                    ->alignment(Alignment::Right)
                    ->color('danger'),

                Tables\Columns\TextColumn::make('amount_received')
                    ->label('Received by Ventiq')
                    ->money('LSL')
                    ->alignment(Alignment::Right)
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('amount_owed_to_org')
                    ->label('Owed to Org')
                    ->money('LSL')
                    ->alignment(Alignment::Right)
                    ->color('warning'),

                Tables\Columns\TextColumn::make('ventiq_revenue')
                    ->label('Ventiq Revenue')
                    ->money('LSL')
                    ->alignment(Alignment::Right)
                    ->color('success')
                    ->weight(FontWeight::Black),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => strtoupper($state))
                    ->color(fn ($state) => match ($state) {
                        'pending'  => 'warning',
                        'partial'  => 'info',
                        'settled'  => 'success',
                        default    => 'gray',
                    }),

                Tables\Columns\TextColumn::make('settled_at')
                    ->label('Settled')
                    ->dateTime('d M Y')
                    ->placeholder('—'),
            ])
            ->headerActions([
                // Create a new settlement batch for an org from unsettled items
                Tables\Actions\Action::make('create_settlement')
                    ->label('New Settlement Batch')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->button()
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
                                    ->whereNull('settlement_id')
                                    ->get();

                                if ($items->isEmpty()) {
                                    return new HtmlString('<p class="text-xs text-gray-400">No unsettled online payments found.</p>');
                                }

                                $grossPaid    = $items->sum('gross_paid');
                                $gatewayFees  = $items->sum('gateway_fee');
                                $amtReceived  = $items->sum('amount_received');
                                $amtOwed      = $items->sum('amount_owed_to_org');
                                $ventiqRev    = $amtReceived - $amtOwed;

                                return new HtmlString("
                                    <div class='space-y-2 text-sm'>
                                        <div class='flex justify-between'><span class='text-gray-500'>Tickets</span><span class='font-bold'>{$items->count()}</span></div>
                                        <div class='flex justify-between'><span class='text-gray-500'>Gross Collected</span><span class='font-bold'>M" . number_format($grossPaid, 2) . "</span></div>
                                        <div class='flex justify-between'><span class='text-gray-500'>Gateway Fees (info)</span><span class='text-red-500 font-bold'>−M" . number_format($gatewayFees, 2) . "</span></div>
                                        <div class='flex justify-between'><span class='text-gray-500'>Received by Ventiq</span><span class='font-bold'>M" . number_format($amtReceived, 2) . "</span></div>
                                        <div class='flex justify-between border-t pt-2'><span class='text-gray-500'>Owed to Org</span><span class='font-black text-orange-500'>M" . number_format($amtOwed, 2) . "</span></div>
                                        <div class='flex justify-between'><span class='text-gray-500'>Ventiq Revenue</span><span class='font-black text-green-600'>M" . number_format($ventiqRev, 2) . "</span></div>
                                    </div>
                                ");
                            }),

                        Forms\Components\Select::make('trigger_type')
                            ->label('Settlement Type')
                            ->options([
                                'manual'     => 'Manual',
                                'post_event' => 'Post Event',
                                'weekly'     => 'Weekly',
                                'monthly'    => 'Monthly',
                            ])
                            ->default('manual')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $items = SettlementItem::where('organization_id', $data['organization_id'])
                            ->whereNull('settlement_id')
                            ->get();

                        if ($items->isEmpty()) {
                            Notification::make()->title('No unsettled items found')->warning()->send();
                            return;
                        }

                        // Get the payment sessions from the items
                        $sessions = PaymentSession::whereIn('id', $items->pluck('payment_session_id'))->get();

                        $settlement = Settlement::createFromSessions(
                            $data['organization_id'],
                            $sessions,
                            $data['trigger_type']
                        );

                        // Link existing items to this settlement
                        SettlementItem::where('organization_id', $data['organization_id'])
                            ->whereNull('settlement_id')
                            ->update(['settlement_id' => $settlement->id]);

                        Notification::make()
                            ->title('Settlement batch created')
                            ->body("M" . number_format($settlement->amount_owed_to_org, 2) . " owed to " . Organization::find($data['organization_id'])->name)
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                // Mark settlement as paid (you've sent the money to the org)
                Tables\Actions\Action::make('mark_settled')
                    ->label('Mark as Settled')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->visible(fn ($record) => !$record->isSettled())
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
                                ->filter(fn ($m) => !in_array($m['label'], ['Free']))
                                ->mapWithKeys(fn ($m, $key) => [$key => $m['label']])
                                ->toArray()
                            )
                            ->required(),
                        Forms\Components\TextInput::make('settlement_reference')
                            ->label('Transaction Reference')
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->rows(2),
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

                // View breakdown of items in this settlement
                Tables\Actions\Action::make('view_items')
                    ->label('View Breakdown')
                    ->icon('heroicon-o-list-bullet')
                    ->color('gray')
                    ->modalWidth('5xl')
                    ->modalHeading(fn ($record) => 'Settlement Items — ' . $record->organization->name)
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