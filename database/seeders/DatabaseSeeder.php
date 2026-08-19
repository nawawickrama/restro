<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * What a real restaurant needs to open the till for the first time: the
     * roles and permissions, an account to sign in with, and the restaurant's
     * own details.
     *
     * The menu and the tables are deliberately not here. Those belong to the
     * restaurant, and a sample menu shipped into a live install is something
     * somebody then has to find and delete. Run {@see DemoDataSeeder} when a
     * populated system is wanted for development or a demonstration.
     *
     * Every seeder below is idempotent, so `db:seed` can be re-run safely.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            SettingsSeeder::class,
        ]);
    }
}
