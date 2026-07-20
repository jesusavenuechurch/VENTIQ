<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

/**
 * One sidebar link: "Organization." Every resource assigned to this
 * cluster (via `protected static ?string $cluster = OrganizationCluster::class;`)
 * shares a single destination with a tab bar across the top linking
 * between them — Profile / Subscription / Team / Payment Methods.
 *
 * IMPORTANT: assigning a resource to a cluster changes its route name
 * from `filament.admin.resources.{resource}.{page}` to
 * `filament.admin.{cluster-slug}.resources.{resource}.{page}`. The
 * $slug below pins that segment to a known value ("organization")
 * instead of leaving it to be derived from the class name, so route
 * names stay predictable and don't shift if this class is ever renamed.
 */
class OrganizationCluster extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Organization';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'organization';
}