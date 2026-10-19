<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\Khoebo\{KhoeboCustomers, KhoeboException};
use Illuminate\Console\Command;

/**
 * Make an organization a customer in Khoebo, or show its customer id if
 * it already is one. Also a quick check that VENTIQ can reach Khoebo.
 */
class KhoeboCustomer extends Command
{
    protected $signature = 'khoebo:customer {organization : The organization id}';
    protected $description = 'Make an organization a customer in Khoebo (or show its customer id)';

    public function handle(KhoeboCustomers $customers): int
    {
        $organization = Organization::find($this->argument('organization'));
        if (! $organization) {
            $this->error('No organization with that id.');
            return self::FAILURE;
        }

        $had = $organization->khoebo_customer_id;
        try {
            $id = $customers->ensure($organization);
        } catch (KhoeboException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info(($had ? 'Already a customer' : 'Made a customer') . " in Khoebo: {$organization->name} is customer {$id}.");

        return self::SUCCESS;
    }
}
