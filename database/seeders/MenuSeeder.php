<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

/** A small sample menu so the POS is usable the moment it is installed. */
class MenuSeeder extends Seeder
{
    /** @var array<string, list<array{0: string, 1: float}>> */
    private array $menu = [
        'Burgers' => [
            ['Chicken Burger', 950],
            ['Beef Burger', 1150],
            ['Cheese Burger', 1250],
            ['Veggie Burger', 850],
        ],
        'Rice' => [
            ['Chicken Fried Rice', 1100],
            ['Egg Fried Rice', 850],
            ['Seafood Fried Rice', 1450],
            ['Chicken Biriyani', 1350],
        ],
        'Snacks' => [
            ['French Fries', 450],
            ['Chicken Wings', 890],
            ['Onion Rings', 500],
            ['Spring Rolls', 420],
        ],
        'Drinks' => [
            ['Coke', 250],
            ['Sprite', 250],
            ['Fresh Lime', 350],
            ['Iced Coffee', 550],
            ['Mineral Water', 150],
        ],
    ];

    public function run(): void
    {
        foreach (array_values($this->menu) as $categoryIndex => $items) {
            $categoryName = array_keys($this->menu)[$categoryIndex];

            $category = Category::query()->updateOrCreate(
                ['name' => $categoryName],
                ['is_active' => true, 'sort_order' => $categoryIndex + 1],
            );

            foreach ($items as $itemIndex => [$name, $price]) {
                MenuItem::query()->updateOrCreate(
                    ['category_id' => $category->id, 'name' => $name],
                    ['price' => $price, 'is_active' => true, 'sort_order' => $itemIndex + 1],
                );
            }
        }
    }
}
