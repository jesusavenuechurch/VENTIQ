<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppReleaseResource\Pages;
use App\Models\AppRelease;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Builds of the VENTIQ Scanner app. The current one is what organizers
 * download from their dashboard (and its Play Store link, once listed).
 */
class AppReleaseResource extends Resource
{
    protected static ?string $model = AppRelease::class;
    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';
    protected static ?string $navigationGroup = 'System';
    protected static ?string $navigationLabel = 'Scanner app';
    protected static ?string $modelLabel = 'scanner app release';
    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Release')
                ->description('The current release is offered on every organizer\'s dashboard.')
                ->schema([
                    Forms\Components\TextInput::make('version')
                        ->required()->maxLength(30)->placeholder('1.2.0')
                        ->helperText('As in the app (app.json version), so people know which build they have.'),
                    Forms\Components\FileUpload::make('apk_path')
                        ->label('APK file')
                        ->disk(AppRelease::DISK)
                        ->directory('app-releases')
                        ->visibility('private')
                        ->acceptedFileTypes(['application/vnd.android.package-archive', 'application/octet-stream', 'application/zip', 'application/java-archive'])
                        ->maxSize(204800)
                        ->getUploadedFileNameForStorageUsing(fn ($file, Forms\Get $get) => 'ventiq-scanner-' . (preg_replace('/[^A-Za-z0-9.\-]/', '', (string) $get('version')) ?: 'build') . '-' . now()->format('YmdHis') . '.apk')
                        ->helperText('The preview build from EAS (eas build --profile preview). Up to 200 MB.'),
                    Forms\Components\TextInput::make('play_store_url')
                        ->label('Google Play link')
                        ->url()->maxLength(255)
                        ->placeholder('https://play.google.com/store/apps/details?id=ls.co.ventiq.scanner')
                        ->helperText('Leave empty until the app is live on Play.'),
                    Forms\Components\Textarea::make('notes')
                        ->label('What\'s new')->rows(3)->maxLength(1000),
                    Forms\Components\Toggle::make('is_current')
                        ->label('Offer this one to organizers')->default(true)
                        ->helperText('Only one release is current; this replaces the one before.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('version')->weight('bold')->searchable(),
                Tables\Columns\IconColumn::make('is_current')->label('Current')->boolean(),
                Tables\Columns\TextColumn::make('apk_size')->label('APK')
                    ->formatStateUsing(fn ($state, AppRelease $record) => $record->sizeLabel() ?? '—')
                    ->placeholder('No file'),
                Tables\Columns\IconColumn::make('play_store_url')->label('On Play')->boolean()
                    ->getStateUsing(fn (AppRelease $record) => filled($record->play_store_url)),
                Tables\Columns\TextColumn::make('created_at')->label('Uploaded')->since(),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (AppRelease $record) => route('scanner-app.download.release', $record))
                    ->visible(fn (AppRelease $record) => $record->hasApk()),
                Tables\Actions\Action::make('makeCurrent')
                    ->label('Make current')->icon('heroicon-o-check-circle')
                    ->visible(fn (AppRelease $record) => !$record->is_current)
                    ->action(fn (AppRelease $record) => $record->update(['is_current' => true])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAppReleases::route('/'),
            'create' => Pages\CreateAppRelease::route('/create'),
            'edit'   => Pages\EditAppRelease::route('/{record}/edit'),
        ];
    }
}
