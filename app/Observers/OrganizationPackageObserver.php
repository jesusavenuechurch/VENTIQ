<?php

namespace App\Observers;

use App\Models\OrganizationPackage;
use App\Support\PackageDefinition;

class OrganizationPackageObserver
{
    public function created(OrganizationPackage $package): void
    {
        $definition = PackageDefinition::get($package->package_type);

        $package->updateQuietly([
            'features'     => $definition['features'],
            'max_scanners' => $definition['max_scanners'],
            'max_users'    => $definition['max_users'],
        ]);
    }
}