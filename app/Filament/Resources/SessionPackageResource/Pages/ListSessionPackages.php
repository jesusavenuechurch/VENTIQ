<?php

namespace App\Filament\Resources\SessionPackageResource\Pages;

use App\Filament\Resources\SessionPackageResource;
use Filament\Resources\Pages\ListRecords;

class ListSessionPackages extends ListRecords
{
    protected static string $resource = SessionPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
