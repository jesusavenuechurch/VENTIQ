<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

/**
 * "Finance" as its own cluster, deliberately separate from both
 * Organization (identity/config) and Events (operations). Settlement
 * is about money Ventiq owes the organization — a distinct concern
 * from either of those. Only one resource lives here today, but
 * building it as a cluster now means Invoices, Payouts, or Reports
 * can join later without ever needing to restructure this again.
 */
class FinanceCluster extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Finance';
    protected static ?int $navigationSort = 3;
    protected static ?string $slug = 'finance';
}