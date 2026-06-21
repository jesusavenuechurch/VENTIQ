<?php

namespace App\Filament\Resources;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'System';
    protected static ?string $navigationLabel = 'Users';

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user && ($user->isSuperAdmin() || $user->hasPermissionTo('manage_staff'));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->isSuperAdmin()) return true;
        if (!$user->hasPermissionTo('manage_staff')) return false;

        // Check current active package user limit
        $package = $user->organization?->activePackage();
        if (!$package) return false;

        $currentUsers = User::where('organization_id', $user->organization_id)->count();
        return $currentUsers < $package->max_users;
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }

        // Users can always edit their own profile
        if ($user->id === $record->id) {
            return true;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        // Org admins can only edit users in their org
        return $user->hasPermissionTo('manage_staff') && 
               $record->organization_id === $user->organization_id;
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }

        // Cannot delete yourself
        if ($user->id === $record->id) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermissionTo('manage_staff') && 
               $record->organization_id === $user->organization_id;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        // Super admin sees all users
        if ($user?->isSuperAdmin()) {
            return $query;
        }

        // Org admins only see users in their org
        if ($user?->organization_id) {
            return $query->where('organization_id', $user->organization_id);
        }

        return $query->whereNull('organization_id');
    }

    public static function form(Form $form): Form
    {
        $isEditingOwnProfile = $form->getRecord() && $form->getRecord()->id === auth()->id();
        
        return $form
            ->schema([
                Forms\Components\Section::make('User Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->confirmed()
                            ->helperText(fn (string $operation) => 
                                $operation === 'edit' ? 'Leave blank to keep current password' : null
                            ),

                        Forms\Components\TextInput::make('password_confirmation')
                            ->label('Confirm Password')
                            ->password()
                            ->visible(fn (Forms\Get $get) => filled($get('password'))),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Organization & Roles')
                    ->schema([
                        Forms\Components\Select::make('organization_id')
                            ->label('Organization')
                            ->relationship('organization', 'name')
                            ->required(fn () => !auth()->user()?->isSuperAdmin())
                            ->disabled(fn () => !auth()->user()?->isSuperAdmin() || $isEditingOwnProfile)
                            ->default(auth()->user()?->organization_id)
                            ->searchable()
                            ->visible(!$isEditingOwnProfile),

                        Forms\Components\CheckboxList::make('roles')
                            ->label('Assign Roles')
                            ->relationship('roles', 'name')
                            ->options(function () {
                                $roles = Role::query();
                                
                                // Non-super admins cannot assign super_admin role
                                if (!auth()->user()?->isSuperAdmin()) {
                                    $roles->where('name', '!=', 'super_admin');
                                }
                                
                                return $roles->pluck('name', 'id');
                            })
                            ->columns(3)
                            ->helperText(
                                'Super Admin: Full system access — see all organizations
                                Org Admin: Manage their organization and staff
                                Staff: Create clients, tickets, approve payments
                                Scanner: Only scan QR codes at events
                                Viewer: Read-only access to data'
                            )
                            ->required()
                            ->visible(!$isEditingOwnProfile)
                            ->disabled($isEditingOwnProfile),
                    ])
                    ->visible(!$isEditingOwnProfile),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organization')
                    ->visible(fn () => auth()->user()?->isSuperAdmin())
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(','),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('organization_id')
                    ->label('Organization')
                    ->relationship('organization', 'name')
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),

                Tables\Filters\SelectFilter::make('roles')
                    ->label('Role')
                    ->relationship('roles', 'name')
                    ->options(Role::all()->pluck('name', 'name')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->visible(fn (Model $record) => $record->id !== auth()->id()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('assign_role')
                        ->label('Assign Role')
                        ->icon('heroicon-o-pencil')
                        ->form([
                            Forms\Components\Select::make('role')
                                ->label('Role')
                                ->options(function () {
                                    $roles = Role::query();
                                    
                                    // Non-super admins cannot assign super_admin role
                                    if (!auth()->user()?->isSuperAdmin()) {
                                        $roles->where('name', '!=', 'super_admin');
                                    }
                                    
                                    return $roles->pluck('name', 'name');
                                })
                                ->required(),
                        ])
                        ->action(function ($records, array $data) {
                            foreach ($records as $record) {
                                // Don't allow changing your own role via bulk action
                                if ($record->id !== auth()->id()) {
                                    $record->syncRoles([$data['role']]);
                                }
                            }
                        }),

                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function ($records) {
                            // Filter out current user from deletion
                            $records->filter(fn ($record) => $record->id !== auth()->id())
                                    ->each(fn ($record) => $record->delete());
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\UserResource\Pages\ListUsers::route('/'),
            'create' => \App\Filament\Resources\UserResource\Pages\CreateUser::route('/create'),
            'edit' => \App\Filament\Resources\UserResource\Pages\EditUser::route('/{record}/edit'),
        ];
    }
}