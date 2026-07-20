<?php

namespace App\Filament\Resources;

use App\Models\EventTier;
use App\Models\Event;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class EventTierResource extends Resource
{
    protected static ?string $model = EventTier::class;
    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    // No longer a nav item anywhere — same pattern as TicketResource.
    // Tiers only ever belong to one event, so they're reached through
    // Events → [an event] → Ticket Tiers (EventResource\Pages\EventTiers),
    // not through their own top-level or cluster-level list. This
    // resource still exists purely to provide the create/edit forms
    // and route helpers that page links out to.
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermissionTo('view_event_tier') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasPermissionTo('create_event_tier') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return $user->hasPermissionTo('edit_event_tier');
        }

        return $user->hasPermissionTo('edit_event_tier') &&
               $record->event->organization_id === $user->organization_id;
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return $user->hasPermissionTo('delete_event_tier');
        }

        return $user->hasPermissionTo('delete_event_tier') &&
               $record->event->organization_id === $user->organization_id;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user?->isSuperAdmin()) {
            return $query;
        }

        if ($user?->organization_id) {
            return $query->whereHas('event', fn ($q) => $q->where('organization_id', $user->organization_id));
        }

        return $query->whereNull('id');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Tier Information')
                    ->description('Basic tier details')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->label('Event')
                            ->relationship('event', 'name', function ($query) {
                                $user = auth()->user();

                                if ($user?->isSuperAdmin()) {
                                    return $query;
                                }

                                if ($user?->organization_id) {
                                    return $query->where('organization_id', $user->organization_id);
                                }

                                return $query->whereNull('id');
                            })
                            // Prefills from ?event_id= on the create URL, e.g. the
                            // "Add Tier" button on the event-scoped tab — falls
                            // back to null (user picks manually) if not present.
                            ->default(fn () => request()->query('event_id'))
                            ->required()
                            ->searchable()
                            ->disabledOn('edit'),

                        Forms\Components\TextInput::make('tier_name')
                            ->label('Tier Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., VIP, Gold, Early Bird')
                            ->helperText('Name that identifies this ticket tier'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description / Benefits')
                            ->rows(3)
                            ->placeholder('e.g., Includes: VIP seating, meet & greet, merchandise')
                            ->helperText('Describe what this tier includes'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Pricing & Availability')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('Price')
                            ->numeric()
                            ->required()
                            ->step(0.01)
                            ->minValue(0)
                            ->suffix('LSL')
                            ->helperText('Ticket price for this tier'),

                        Forms\Components\TextInput::make('quantity_available')
                            ->label('Quantity Available')
                            ->numeric()
                            ->nullable()
                            ->minValue(1)
                            ->helperText('Leave empty for unlimited tickets'),

                        Forms\Components\TextInput::make('quantity_per_purchase')
                            ->label('Max Quantity Per Purchase')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(1)
                            ->helperText('Maximum number of tickets a buyer can purchase at once for this tier'),

                        Forms\Components\TextInput::make('quantity_sold')
                            ->label('Quantity Sold')
                            ->numeric()
                            ->disabled()
                            ->visibleOn('edit')
                            ->helperText('Auto-updated when tickets are created'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Tier Styling')
                    ->description('Customize QR code appearance')
                    ->schema([
                        Forms\Components\ColorPicker::make('color')
                            ->label('QR Code Color')
                            ->nullable()
                            ->helperText('Color used for QR codes. Leave empty for auto-color based on tier name')
                            ->columnSpan(1),

                        Forms\Components\Select::make('color_preset')
                            ->label('Color Presets')
                            ->options([
                                '#C0C0C0' => '🥈 Silver',
                                '#FFD700' => '🥇 Gold',
                                '#0064C8' => '🎫 Blue',
                                '#800080' => '💠 Purple',
                                '#9B111E' => '💎 Ruby Red',
                                '#50C878' => '💚 Emerald Green',
                                '#E6E6FA' => '✨ Platinum',
                                '#8B4513' => '👑 VIP Brown',
                                '#FF6B6B' => '❤️ Red',
                                '#4ECDC4' => '🌊 Teal',
                            ])
                            ->nullable()
                            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('color', $state))
                            ->columnSpan(1)
                            ->helperText('Quick select a preset color'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->columnSpan(2)
                            ->helperText('Inactive tiers cannot be selected for new tickets'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event.name')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('tier_name')
                    ->label('Tier Name')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->is_active ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->money('LSL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity_available')
                    ->label('Available')
                    ->getStateUsing(fn ($record) => $record->quantity_available ? $record->quantity_available : '∞')
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity_per_purchase')
                    ->label('Max Per Purchase')
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity_sold')
                    ->label('Sold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('available_count')
                    ->label('Remaining')
                    ->getStateUsing(fn ($record) => $record->available_count)
                    ->color(fn ($record) => $record->available_count <= 5 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('availability_percentage')
                    ->label('Availability %')
                    ->badge()
                    ->color(fn ($record) => $record->availability_percentage > 50 ? 'success' : ($record->availability_percentage > 20 ? 'warning' : 'danger')),

                Tables\Columns\ColorColumn::make('color')
                    ->label('QR Color'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Event')
                    ->relationship('event', 'name'),

                Tables\Filters\Filter::make('is_active')
                    ->label('Active Only')
                    ->query(fn (Builder $query) => $query->where('is_active', true)),

                Tables\Filters\Filter::make('low_availability')
                    ->label('Low Availability')
                    ->query(fn (Builder $query) => $query->whereRaw('quantity_available IS NOT NULL AND quantity_sold >= (quantity_available * 0.8)')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('toggle_active')
                    ->label(fn ($record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn ($record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn ($record) => $record->is_active ? 'danger' : 'success')
                    ->action(function (EventTier $record) {
                        $record->update(['is_active' => !$record->is_active]);
                        Notification::make()
                            ->title('Updated')
                            ->body("Tier {$record->tier_name} is now " . ($record->is_active ? 'active' : 'inactive'))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('clone_tier')
                    ->label('Clone')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('target_event_id')
                            ->label('Clone to Event')
                            ->options(fn (EventTier $record) => Event::query()
                                ->where('organization_id', auth()->user()?->organization_id)
                                ->where('id', '!=', $record->event_id)
                                ->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function (EventTier $record, array $data) {
                        EventTier::create([
                            'event_id' => $data['target_event_id'],
                            'tier_name' => $record->tier_name . ' (Copy)',
                            'description' => $record->description,
                            'price' => $record->price,
                            'color' => $record->color,
                            'quantity_available' => $record->quantity_available,
                            'is_active' => true,
                        ]);

                        Notification::make()
                            ->title('Cloned')
                            ->body("Tier cloned to another event")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('update_price')
                        ->label('Update Price')
                        ->icon('heroicon-o-currency-dollar')
                        ->form([
                            Forms\Components\TextInput::make('new_price')
                                ->label('New Price')
                                ->numeric()
                                ->required()
                                ->step(0.01)
                                ->minValue(0),

                            Forms\Components\Radio::make('price_action')
                                ->label('Action')
                                ->options([
                                    'set' => 'Set to exact price',
                                    'increase' => 'Increase by %',
                                    'decrease' => 'Decrease by %',
                                ])
                                ->default('set')
                                ->inline(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            foreach ($records as $record) {
                                $newPrice = match($data['price_action']) {
                                    'set' => $data['new_price'],
                                    'increase' => $record->price * (1 + ($data['new_price'] / 100)),
                                    'decrease' => $record->price * (1 - ($data['new_price'] / 100)),
                                };

                                $record->update(['price' => $newPrice]);
                            }

                            Notification::make()
                                ->title('Prices Updated')
                                ->body("Updated " . $records->count() . " tier(s)")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('bulk_color')
                        ->label('Update Color')
                        ->icon('heroicon-o-paint-brush')
                        ->form([
                            Forms\Components\ColorPicker::make('color')
                                ->label('New Color')
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            foreach ($records as $record) {
                                $record->update(['color' => $data['color']]);
                            }

                            Notification::make()
                                ->title('Colors Updated')
                                ->body("Updated " . $records->count() . " tier(s)")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('bulk_toggle_active')
                        ->label('Toggle Active Status')
                        ->icon('heroicon-o-arrow-path')
                        ->action(function (Collection $records) {
                            foreach ($records as $record) {
                                $record->update(['is_active' => !$record->is_active]);
                            }

                            Notification::make()
                                ->title('Status Updated')
                                ->body("Updated " . $records->count() . " tier(s)")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('tier_templates')
                    ->label('📋 Manage Templates')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->url(route('filament.admin.resources.tier-templates.index'))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('tier_statistics')
                    ->label('📊 Tier Stats')
                    ->icon('heroicon-o-chart-bar')
                    ->color('success')
                    ->modalContent(fn () => view('filament.modals.tier-statistics', [
                        'stats' => self::getTierStatistics(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ]);
    }

    public static function getTierStatistics(): array
    {
        $user = auth()->user();
        $query = EventTier::query();

        if (!$user?->isSuperAdmin()) {
            $query->whereHas('event', fn ($q) => $q->where('organization_id', $user?->organization_id));
        }

        $tiers = $query->get();

        return [
            'total_tiers' => $tiers->count(),
            'total_revenue' => $tiers->sum(fn ($t) => $t->price * $t->quantity_sold),
            'total_tickets_sold' => $tiers->sum('quantity_sold'),
            'active_tiers' => $tiers->where('is_active', true)->count(),
            'by_event' => $tiers->groupBy('event.name')->map(fn ($group) => [
                'count' => $group->count(),
                'total_sold' => $group->sum('quantity_sold'),
                'revenue' => $group->sum(fn ($t) => $t->price * $t->quantity_sold),
            ])->toArray(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\EventTierResource\Pages\ListEventTiers::route('/'),
            'create' => \App\Filament\Resources\EventTierResource\Pages\CreateEventTier::route('/create'),
            'edit' => \App\Filament\Resources\EventTierResource\Pages\EditEventTier::route('/{record}/edit'),
        ];
    }
}