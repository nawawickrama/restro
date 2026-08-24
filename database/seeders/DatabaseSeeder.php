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
     * The menu is here because it is the restaurant's own, taken from its
     * price list. The tables are not: nobody has said how the room is laid
     * out. Run {@see DemoDataSeeder} for a set of sample tables when a
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
            MenuSeeder::class,
        ]);
    }
}
