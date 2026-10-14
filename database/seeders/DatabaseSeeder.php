<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class, // Run this FIRST - creates roles & permissions
            UserSeeder::class,                 // Then users - can assign roles
            DemoDataSeeder::class, 
            DemoAccountSeeder::class, 
            SessionTestSeeder::class,
            // InstallmentTestSeeder::class,         // Finally demo data - uses users & roles
        ]);
    }
}