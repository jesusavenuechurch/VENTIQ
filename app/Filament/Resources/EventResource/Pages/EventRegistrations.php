<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\TicketResource;
use App\Models\Ticket;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * "Events → [select one] → Registrations" tab.
 *
 * Two things a plain Page doesn't give you for free, both needed here:
 *
 * 1. HasTable + InteractsWithTable — without these, Livewire has no
 *    $table property to resolve, which is exactly the error you hit.
 *    A Page only gets a table when it explicitly says it has one.
 *
 * 2. InteractsWithRecord — this is what EditEvent/EditRecord use under
 *    the hood to turn the `/{record}/registrations` URL segment into
 *    an actual Event model, resolved through EventResource's own
 *    getEloquentQuery(). That last part matters: it means an org admin
 *    can't view another org's tickets by guessing a URL, because the
 *    same scoping EventResource already enforces applies here too.
 */
class EventRegistrations extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = EventResource::class;
    protected static string $view = 'filament.resources.event-resource.pages.event-registrations';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return "{$this->record->name} — Registrations";
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Ticket::query()->where('event_id', $this->record->id))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Guest')
                    ->weight('bold')
                    ->searchable()
                    ->description(fn ($record) =>
                        $record->ticket_number . ' • ' . ($record->tier?->tier_name ?? '—') .
                        ($record->is_complimentary ? ' 🎁 COMP' : '')
                    ),

                Tables\Columns\TextColumn::make('voucher_code')
                    ->label('Entry Code')
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->money(config('constants.currency.code'))
                    ->alignment('right')
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
                    ->color(fn ($state) => match ($state) {
                        'active'     => 'info',
                        'checked_in' => 'success',
                        'refunded'   => 'danger',
                        default      => 'gray',
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->url(fn ($record) => TicketResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('No tickets yet')
            ->emptyStateDescription('Tickets created for this event will show up here.')
            ->emptyStateIcon('heroicon-o-ticket');
    }
}