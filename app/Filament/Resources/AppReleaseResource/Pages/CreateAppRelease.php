<?php

namespace App\Filament\Resources\AppReleaseResource\Pages;

use App\Filament\Resources\AppReleaseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAppRelease extends CreateRecord
{
    protected static string $resource = AppReleaseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploaded_by'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
