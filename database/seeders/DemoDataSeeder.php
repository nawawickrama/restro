<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * A room full of numbered tables, for development and demonstrations.
 *
 * Never run as part of `db:seed`: a real dining room is laid out however it is
 * laid out, and eight invented tables are something somebody then has to
 * delete. Run it explicitly:
 *
 *     php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TableSeeder::class,
        ]);
    }
}
