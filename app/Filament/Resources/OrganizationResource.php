<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\OrganizationCluster;
use App\Models\Organization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $cluster = OrganizationCluster::class;
    protected static ?string $navigationLabel = 'Profile';
    protected static ?int $navigationSort = 1;

    /* ------------------------------------------------------------
     | Permissions — same resource for everyone, scoped by role.
     | Super admin: sees/manages every organization.
     | Org admin: sees/edits only their own — can't create a second
     | one (they already have one from registration) and can't delete it.
     ------------------------------------------------------------ */

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user?->isSuperAdmin() || $user?->organization_id !== null;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

public static function canEdit($record): bool
{
    $user = auth()->user();
    if (! $user) return false;
    if ($user->isSuperAdmin()) return true;

    return (int) $record->id === (int) $user->organization_id;
}

    public static function canDelete($record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user?->isSuperAdmin()) {
            return $query;
        }

        // Org admins only ever see their own organization — the query
        // itself enforces this, so there's no risk of them browsing
        // or editing anyone else's record even if a URL is guessed.
        return $query->where('id', $user?->organization_id);
    }

    public static function form(Form $form): Form
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;

        return $form->schema([
            Forms\Components\Hidden::make('slug_locked')->default(false),
            Forms\Components\Hidden::make('tagline_locked')->default(false),

            Forms\Components\TextInput::make('name')
                ->required()
                ->live()
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
                ->disabled(! $isSuperAdmin)
                ->dehydrated($isSuperAdmin)
                ->helperText($isSuperAdmin
                    ? null
                    : 'This is your public event URL. Contact support if you need it changed.')
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
                ->default(true)
                ->disabled(! $isSuperAdmin)
                ->helperText($isSuperAdmin ? null : 'Managed by VENTIQ.'),

            Forms\Components\Toggle::make('workshop_enabled')
                ->label('Workshop Mode Enabled')
                ->helperText('Grants access to workshop events, signatures, and attendance registers.')
                ->visible($isSuperAdmin),
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