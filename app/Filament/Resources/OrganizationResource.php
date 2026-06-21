<?php

namespace App\Filament\Resources;

use App\Models\Organization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'System';

    // Super admin only
    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('slug_locked')->default(false),
            Forms\Components\Hidden::make('tagline_locked')->default(false),

            Forms\Components\TextInput::make('name')
                ->required()
                ->live() // updates on every keystroke
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    if (! $get('slug_locked')) {
                        $set('slug', Str::slug($state));
                    }

                    if (! $get('tagline_locked')) {
                        $set('tagline', $state);
                    }
                }),


            Forms\Components\FileUpload::make('logo_path')
                ->label('Organisation Logo')
                ->image()
                ->disk('public')
                ->directory('organization-logos')
                ->maxSize(2048)
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'])
                ->imageEditor()
                ->helperText('Used on PDF reports. Recommended: square PNG or SVG, min 200×200px.')
                ->columnSpanFull(),

            Forms\Components\TextInput::make('slug')
                ->required()
                ->unique(ignoreRecord: true)
                ->afterStateUpdated(fn ($set) => $set('slug_locked', true)),

            Forms\Components\TextInput::make('tagline')
                ->afterStateUpdated(fn ($set) => $set('tagline_locked', true))
                ->helperText('Auto-generated from name, editable'),

            Forms\Components\TextInput::make('email')
                ->email()
                ->required(),

            Forms\Components\TextInput::make('contact_email')
                ->email(),

            Forms\Components\TextInput::make('phone')
                ->tel(),

            Forms\Components\TextInput::make('website')
                ->url(),

            Forms\Components\Textarea::make('description')
                ->rows(4),

            Forms\Components\Toggle::make('is_active')
                ->default(true),
            
            Forms\Components\Toggle::make('workshop_enabled')
                ->label('Workshop Mode Enabled')
                ->helperText('Grants access to workshop events, signatures, and attendance registers.')
                ->visible(fn () => auth()->user()->isSuperAdmin()),
        ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\OrganizationResource\Pages\ListOrganizations::route('/'),
            'create' => \App\Filament\Resources\OrganizationResource\Pages\CreateOrganization::route('/create'),
            'edit' => \App\Filament\Resources\OrganizationResource\Pages\EditOrganization::route('/{record}/edit'),
        ];
    }
}