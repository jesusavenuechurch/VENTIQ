<?php

namespace App\Filament\Resources;

use App\Models\OrganizationPaymentMethod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrganizationPaymentMethodResource extends Resource
{
    protected static ?string $model = OrganizationPaymentMethod::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Payment Methods';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 3;

    /* ------------------------------------------------------------
     | Permissions
     ------------------------------------------------------------ */

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyPermission([
            'view_payment_method',
            'create_payment_method',
        ]) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasPermissionTo('create_payment_method') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return $user->hasPermissionTo('edit_payment_method');
        }

        return $user->hasPermissionTo('edit_payment_method')
            && $record->organization_id === $user->organization_id;
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return $user->hasPermissionTo('delete_payment_method');
        }

        return $user->hasPermissionTo('delete_payment_method')
            && $record->organization_id === $user->organization_id;
    }

    /* ------------------------------------------------------------
     | Query Scope
     ------------------------------------------------------------ */

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
            Forms\Components\Section::make('Payment Method Details')
                ->schema([
                    Forms\Components\Select::make('organization_id')
                        ->relationship('organization', 'name')
                        ->required()
                        ->disabled(fn () => !auth()->user()?->isSuperAdmin())
                        ->default(auth()->user()?->organization_id)
                        ->dehydrated(true),

                    Forms\Components\Select::make('payment_method')
                        ->label('Payment Method')
                        ->options(function () {
                            // ✅ Get from constants and format for dropdown
                            return collect(config('constants.payment_methods'))
                                ->mapWithKeys(fn ($config, $key) => [$key => $config['label'] ?? ucfirst($key)])
                                ->toArray();
                        })
                        ->required()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn ($set) => $set('account_number', null)),

                    Forms\Components\TextInput::make('account_name')
                        ->label('Account Name / Label')
                        ->maxLength(255)
                        ->placeholder('e.g., Expressions Conference')
                        ->helperText('Optional: Name shown on payment account')
                        ->visible(fn (Forms\Get $get) =>
                            (bool) config("constants.payment_methods.{$get('payment_method')}.requires_account", false)
                        ),

                    Forms\Components\TextInput::make('account_number')
                        ->label(fn (Forms\Get $get) =>
                            config("constants.payment_methods.{$get('payment_method')}.account_label", 'Account Number')
                        )
                        ->placeholder(function (Forms\Get $get) {
                            $method = $get('payment_method');
                            return match($method) {
                                'mpesa', 'ecocash' => 'e.g., +266 5949 4756',
                                'bank_transfer' => 'e.g., 1234567890',
                                'card', 'online' => 'e.g., MERCHANT-ABC123',
                                default => 'Enter payment details',
                            };
                        })
                        ->helperText(function (Forms\Get $get) {
                            $method = $get('payment_method');
                            $label = config("constants.payment_methods.{$method}.account_label");
                            return $label ? "The {$label} customers should use" : 'Where customers should send payments';
                        })
                        ->required(fn (Forms\Get $get) =>
                            (bool) config("constants.payment_methods.{$get('payment_method')}.requires_account", false)
                        )
                        ->maxLength(255)
                        ->visible(fn (Forms\Get $get) =>
                            (bool) config("constants.payment_methods.{$get('payment_method')}.requires_account", false)
                        ),

                    Forms\Components\Textarea::make('instructions')
                        ->label('Payment Instructions')
                        ->rows(4)
                        ->placeholder(function (Forms\Get $get) {
                            return match($get('payment_method')) {
                                'cash' => 'e.g., "Pay at the venue reception desk during business hours"',
                                'mpesa', 'ecocash' => 'e.g., "Send payment with reference: EVENT-[Your Name]"',
                                'bank_transfer' => 'e.g., "Use your ticket number as reference"',
                                default => 'Additional instructions for customers',
                            };
                        })
                        ->helperText('Instructions shown to customers during registration')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('display_order')
                        ->label('Display Order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Lower numbers appear first'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Only active payment methods are shown to customers'),
                ])
                ->columns(2),
        ]);
    }

    /* ------------------------------------------------------------
     | Table
     ------------------------------------------------------------ */

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'mpesa', 'ecocash' => 'success',
                        'bank_transfer' => 'info',
                        'card', 'online' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) =>
                        config("constants.payment_methods.{$state}.label", strtoupper($state))
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('account_name')
                    ->label('Account Name')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('account_number')
                    ->label('Account #')
                    ->searchable()
                    ->limit(20),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('display_order')
                    ->label('Order')
                    ->sortable(),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organization')
                    ->visible(fn () => auth()->user()?->isSuperAdmin())
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options(function () {
                        return collect(config('constants.payment_methods'))
                            ->mapWithKeys(fn ($config, $key) => [$key => $config['label'] ?? ucfirst($key)])
                            ->toArray();
                    }),

                Tables\Filters\Filter::make('active_only')
                    ->label('Active Only')
                    ->query(fn (Builder $query) => $query->where('is_active', true))
                    ->default(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('toggle_active')
                    ->label(fn ($record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn ($record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn ($record) => $record->is_active ? 'danger' : 'success')
                    ->action(function ($record) {
                        $record->update(['is_active' => !$record->is_active]);
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('display_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\OrganizationPaymentMethodResource\Pages\ListOrganizationPaymentMethods::route('/'),
            'create' => \App\Filament\Resources\OrganizationPaymentMethodResource\Pages\CreateOrganizationPaymentMethod::route('/create'),
            'edit' => \App\Filament\Resources\OrganizationPaymentMethodResource\Pages\EditOrganizationPaymentMethod::route('/{record}/edit'),
        ];
    }
}
