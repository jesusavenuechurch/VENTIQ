<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class, // first: the roles everything else is given
            DemoAccountSeeder::class,
        ]);

        // The Sessions demo: two organizations and six months of meetings
        // (it refuses to run in production on its own, too).
        if (!app()->environment('production')) {
            $this->call(SessionsDemoSeeder::class);
        }
    }
}
