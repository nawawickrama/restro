<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Everything a fresh install needs to start taking orders.
     * Every seeder below is idempotent, so `db:seed` can be re-run safely.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            SettingsSeeder::class,
            MenuSeeder::class,
            TableSeeder::class,
        ]);
    }
}
