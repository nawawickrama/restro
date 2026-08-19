<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * A sample menu and a room full of tables, for development and demonstrations.
 *
 * Never run as part of `db:seed`: a live restaurant should not have to delete
 * somebody else's burgers before entering its own. Run it explicitly:
 *
 *     php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            MenuSeeder::class,
            TableSeeder::class,
        ]);
    }
}
