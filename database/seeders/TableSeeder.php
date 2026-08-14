<?php

namespace Database\Seeders;

use App\Models\RestaurantTable;
use Illuminate\Database\Seeder;

class TableSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 8) as $number) {
            RestaurantTable::query()->updateOrCreate(
                ['name' => "Table {$number}"],
                ['seats' => $number <= 6 ? 4 : 6, 'is_active' => true, 'sort_order' => $number],
            );
        }
    }
}
