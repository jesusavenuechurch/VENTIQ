<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TierTemplateResource\Pages;
use App\Filament\Resources\TierTemplateResource\RelationManagers;
use App\Models\TierTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Event;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class TierTemplateResource extends Resource
{
    protected static ?string $model = TierTemplate::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationLabel = 'Tier Templates';
    protected static ?string $cluster = \App\Filament\Clusters\EventsCluster::class;
    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermissionTo('create_event_tier') ?? false;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user?->isSuperAdmin()) {
            return $query;
        }

        if ($user?->organization_id) {
            return $query->where('organization_id', $user->organization_id);
        }

        return $query->whereNull('organization_id');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Template Info')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Template Name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g., Standard Conference, VIP Event'),

                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(2)
                        ->placeholder('Describe when to use this template'),

                    Forms\Components\Toggle::make('is_default')
                        ->label('Set as Default')
                        ->helperText('Use this template by default for new events'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Tier Configuration')
                ->description('Define the tiers included in this template')
                ->schema([
                    Forms\Components\Repeater::make('tiers_config')
                        ->label('Tiers')
                        ->schema([
                            Forms\Components\TextInput::make('tier_name')
                                ->label('Tier Name')
                                ->required()
                                ->columnSpan(2),

                            Forms\Components\Textarea::make('description')
                                ->label('Description')
                                ->rows(2)
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('price')
                                ->label('Price (UGX)')
                                ->numeric()
                                ->required()
                                ->step(0.01)
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('quantity_available')
                                ->label('Qty Available')
                                ->numeric()
                                ->nullable()
                                ->columnSpan(1)
                                ->helperText('Leave empty for unlimited'),

                            Forms\Components\ColorPicker::make('color')
                                ->label('QR Color')
                                ->nullable()
                                ->columnSpan(1),

                            Forms\Components\Select::make('color_preset')
                                ->label('Or Pick Preset')
                                ->options([
                                    '#C0C0C0' => '🥈 Silver',
                                    '#FFD700' => '🥇 Gold',
                                    '#0064C8' => '🎫 Blue',
                                    '#800080' => '💠 Purple',
                                    '#9B111E' => '💎 Ruby Red',
                                    '#50C878' => '💚 Emerald Green',
                                    '#E6E6FA' => '✨ Platinum',
                                    '#8B4513' => '👑 VIP Brown',
                                ])
                                ->nullable()
                                ->columnSpan(1)
                                ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('color', $state)),
                        ])
                        ->columns(2)
                        ->addActionLabel('Add Tier')
                        ->required()
                        ->minItems(1)
                        ->defaultItems(1),
                ])
                ->columnSpan('full'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->limit(50),

                Tables\Columns\TextColumn::make('tiers_config')
                    ->label('# Tiers')
                    ->getStateUsing(fn ($record) => count($record->tiers_config ?? []))
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('is_default')
                    ->label('Default Only')
                    ->query(fn ($query) => $query->where('is_default', true)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('apply_template')
                    ->label('Apply to Event')
                    ->icon('heroicon-o-arrow-right')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('event_id')
                            ->label('Select Event')
                            ->options(fn () => Event::query()
                                ->where('organization_id', auth()->user()?->organization_id)
                                ->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function (TierTemplate $record, array $data) {
                        $event = Event::find($data['event_id']);
                        $created = $record->applyToEvent($event);

                        Notification::make()
                            ->title('Template Applied')
                            ->body("Created {$created} tier(s) for {$event->name}")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('clone')
                    ->label('Clone')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->action(function (TierTemplate $record) {
                        $cloned = TierTemplate::create([
                            'organization_id' => $record->organization_id,
                            'name' => $record->name . ' (Copy)',
                            'description' => $record->description,
                            'tiers_config' => $record->tiers_config,
                            'is_default' => false,
                        ]);

                        Notification::make()
                            ->title('Cloned')
                            ->body("Template cloned successfully")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('set_default')
                        ->label('Set as Default')
                        ->icon('heroicon-o-star')
                        ->action(function (Collection $records) {
                            // Unset all other defaults
                            TierTemplate::where('organization_id', auth()->user()?->organization_id)
                                ->update(['is_default' => false]);

                            // Set selected as default
                            $records->first()?->update(['is_default' => true]);

                            Notification::make()
                                ->title('Default Set')
                                ->body("Default template updated")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\TierTemplateResource\Pages\ListTierTemplates::route('/'),
            'create' => \App\Filament\Resources\TierTemplateResource\Pages\CreateTierTemplate::route('/create'),
            'edit' => \App\Filament\Resources\TierTemplateResource\Pages\EditTierTemplate::route('/{record}/edit'),
        ];
    }
}