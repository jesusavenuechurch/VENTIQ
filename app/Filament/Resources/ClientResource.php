<?php

namespace App\Filament\Resources;

use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\ClientResource\Pages;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static ?string $navigationLabel = 'Clients';
    protected static ?string $cluster = \App\Filament\Clusters\EventsCluster::class;
    protected static ?int $navigationSort = 3;

    /* ------------------------------------------------------------
     | Permissions & Scoping
     ------------------------------------------------------------ */
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        // If they are an agent, they see NOTHING in the sidebar
        if ($user?->isSalesAgent()) {
            return false;
        }

        // Otherwise, allow admins/super-admins
        return $user?->isSuperAdmin() || $user?->organization_id !== null;
    }
    
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user?->isSuperAdmin()) {
            return $query;
        }

        if ($user?->organization_id) {
            return $query->where('organization_id', $user->organization_id);
        }

        return $query->whereNull('id');
    }

    /* ------------------------------------------------------------
     | Form
     ------------------------------------------------------------ */

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Client Details')
                ->schema([
                    Forms\Components\Select::make('organization_id')
                        ->label('Organization')
                        ->relationship('organization', 'name')
                        ->required()
                        ->searchable()
                        ->disabled(fn () => ! auth()->user()?->isSuperAdmin())
                        ->default(auth()->user()?->organization_id),

                    Forms\Components\TextInput::make('full_name')
                        ->label('Full Name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->nullable(),

                    Forms\Components\TextInput::make('phone')
                        ->tel()
                        ->nullable(),

                    Forms\Components\Select::make('status')
                        ->options([
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                            'archived' => 'Archived',
                        ])
                        ->default('active'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Additional Information')
                ->schema([
                    Forms\Components\Select::make('created_by')
                        ->label('Registered By')
                        ->relationship('createdBy', 'name')
                        ->nullable()
                        ->searchable()
                        ->helperText('Leave empty if self-registered'),

                    Forms\Components\Textarea::make('notes')
                        ->rows(3),
                ])
                ->collapsed(),
        ]);
    }

    /* ------------------------------------------------------------
     | Table (MINIMAL, SCANNABLE)
     ------------------------------------------------------------ */

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                // ALWAYS VISIBLE
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Client')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->limit(25),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'inactive',
                        'gray' => 'archived',
                    ]),

                // OPTIONAL (Hidden by default)
                Tables\Columns\TextColumn::make('phone')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organization')
                    ->visible(fn () => auth()->user()?->isSuperAdmin())
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Registered By')
                    ->default('Self-Registered')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->date('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])
                ->icon('heroicon-o-ellipsis-vertical'),
            ])

            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'archived' => 'Archived',
                    ]),
            ]);
    }

    /* ------------------------------------------------------------
     | Pages
     ------------------------------------------------------------ */

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}
