<?php

namespace App\Filament\Widgets;

use App\Models\Ticket;
use App\Services\Payments\TicketActivationService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Forms;
use Filament\Notifications\Notification;

class PendingApprovalsWidget extends BaseWidget
{
    protected static ?string $heading = 'Payments to Confirm';
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Ticket::query()
                    ->where('payment_status', 'pending')
                    ->whereHas('event', function ($query) {
                        $user = auth()->user();
                        if (!$user?->isSuperAdmin()) {
                            $query->where('organization_id', $user?->organization_id);
                        }
                    })
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('M d, H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Client')
                    ->searchable(),

                Tables\Columns\TextColumn::make('event.name')
                    ->label('Event')
                    ->limit(30),

                Tables\Columns\TextColumn::make('tier.tier_name')
                    ->label('Tier')
                    ->badge(),

                Tables\Columns\TextColumn::make('amount')
                    ->money(config('constants.currency.code')) // ✅ Use config directly
                    ->label('Amount'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Activate Ticket')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Has this payment been received?')
                    ->form([
                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Payment Reference')
                            ->default(fn (Ticket $record) => $record->payment_reference)
                            ->required()
                            ->placeholder('e.g., ECOCASH-ABC123'),

                        Forms\Components\Select::make('payment_method')
                            ->label('Payment Method')
                            ->options(collect(config('constants.payment_methods'))
                                ->mapWithKeys(fn ($m, $key) => [$key => $m['label'] ?? ucfirst($key)])
                                ->toArray())
                            ->default(fn (Ticket $record) => $record->payment_method ?? 'ecocash')
                            ->required(),
                    ])
                    ->action(function (Ticket $record, array $data) {
                        $activated = app(TicketActivationService::class)->activate(
                            ticket: $record,
                            source: TicketActivationService::SOURCE_ORGANIZER_DIRECT,
                            paymentMethod: $data['payment_method'],
                            paymentReference: $data['payment_reference'],
                            confirmedBy: auth()->id(),
                        );

                        Notification::make()
                            ->title($activated ? 'Ticket activated' : 'Ticket was already active')
                            ->body("Ticket {$record->ticket_number}")
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No pending approvals')
            ->emptyStateDescription('All tickets have been processed')
            ->emptyStateIcon('heroicon-o-check-badge');
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasPermissionTo('approve_payment') ?? false;
    }
}