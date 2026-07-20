<?php

namespace App\Filament\Resources\OrganizationResource\Pages;

use App\Filament\Resources\OrganizationResource;
use Filament\Resources\Pages\ListRecords;

class ListOrganizations extends ListRecords
{
    protected static string $resource = OrganizationResource::class;

    /**
     * Super admins see the real list (all organizations). Org admins
     * only ever have one record — send them straight to editing it
     * rather than showing a table with a single row to click into.
     */
    // public function mount(): void
    // {
    //     parent::mount();

    //     $user = auth()->user();

    //     if (! $user?->isSuperAdmin() && $user?->organization_id) {
    //         $this->redirect(
    //             OrganizationResource::getUrl('edit', ['record' => $user->organization_id])
    //         );
    //     }
    // }
}