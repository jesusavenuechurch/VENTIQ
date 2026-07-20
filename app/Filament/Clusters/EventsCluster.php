<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class EventsCluster extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'Events';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'events';
}