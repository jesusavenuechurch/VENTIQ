<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventTierResource;
use App\Models\EventTier;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * "Events → [select one] → Ticket Tiers" tab.
 *
 * Same shape as EventRegistrations: this page owns a table (hence
 * HasTable + InteractsWithTable) scoped to exactly one event (hence
 * InteractsWithRecord, resolved through EventResource's own scoped
 * query). EventTierResource itself stays hidden from navigation and
 * only supplies the create/edit forms this table links out to.
 */
class EventTiers extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = EventResource::class;
    protected static string $view = 'filament.resources.event-resource.pages.event-tiers';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return "{$this->record->name} — Ticket Tiers";
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(EventTier::query()->where('event_id', $this->record->id))
            ->defaultSort('price')
            ->columns([
                Tables\Columns\TextColumn::make('tier_name')
                    ->label('Tier')
                    ->badge()
                    ->color(fn ($record) => $record->is_active ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('price')
                    ->money('LSL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity_available')
                    ->label('Available')
                    ->getStateUsing(fn ($record) => $record->quantity_available ?? '∞'),

                Tables\Columns\TextColumn::make('quantity_sold')
                    ->label('Sold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('available_count')
                    ->label('Remaining')
                    ->getStateUsing(fn ($record) => $record->available_count)
                    ->color(fn ($record) => $record->available_count !== null && $record->available_count <= 5 ? 'danger' : 'success'),

                Tables\Columns\ColorColumn::make('color')
                    ->label('QR Color'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('add_tier')
                    ->label('Add Tier')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->url(fn () => EventTierResource::getUrl('create', ['event_id' => $this->record->id])),
            ])
            ->actions([
                // Quick fix for a ticket type's number, right here in the list.
                Tables\Actions\Action::make('change_number')
                    ->label('Change number')
                    ->icon('heroicon-o-hashtag')
                    ->color('warning')
                    ->modalHeading(fn (EventTier $record) => "Number of {$record->tier_name} tickets")
                    ->modalDescription(fn (EventTier $record) => \App\Support\TierCapacity::taken($record) . ' already taken (paid or held). Raising it reopens sales.')
                    ->fillForm(fn (EventTier $record) => ['quantity_available' => $record->quantity_available])
                    ->form(fn (EventTier $record) => [
                        \Filament\Forms\Components\TextInput::make('quantity_available')
                            ->label('Number of tickets')
                            ->numeric()
                            ->required()
                            ->minValue(max(1, \App\Support\TierCapacity::taken($record))),
                    ])
                    ->action(function (EventTier $record, array $data) {
                        $record->update(['quantity_available' => (int) $data['quantity_available']]);
                        \Filament\Notifications\Notification::make()->title("{$record->tier_name}: {$record->quantity_available} tickets")->success()->send();
                    }),

                Tables\Actions\Action::make('toggle_active')
                    ->label(fn ($record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn ($record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn ($record) => $record->is_active ? 'danger' : 'success')
                    ->action(fn (EventTier $record) => $record->update(['is_active' => !$record->is_active])),

                Tables\Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->url(fn ($record) => EventTierResource::getUrl('edit', ['record' => $record])),

                Tables\Actions\DeleteAction::make()
                        ->before(fn ($record, $action) => \App\Support\MoneyRecords::guardAction($action, $record)),
            ])
            ->emptyStateHeading('No ticket tiers yet')
            ->emptyStateDescription('Add a tier to start selling tickets for this event.')
            ->emptyStateIcon('heroicon-o-ticket');
    }
}