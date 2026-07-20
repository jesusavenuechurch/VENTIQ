<?php

namespace App\Filament\Resources\OrganizationalRecordResource\Pages;

use App\Filament\Resources\OrganizationalRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrganizationalRecords extends ListRecords
{
    protected static string $resource = OrganizationalRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()  
                ->label('New Record')
                ->icon('heroicon-o-plus'),
        ];
    }
}
