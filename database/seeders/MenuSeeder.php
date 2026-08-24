<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

/**
 * K&D Foods & Catering's menu, as supplied on the price list.
 *
 * Two things about the shape of the source are worth knowing:
 *
 * The price list carries a small and a large price in separate columns. An
 * order line holds one price, so a dish sold in two sizes becomes two items —
 * "Chicken Fried Rice (Small)" and "(Large)" — which is what the cashier taps
 * and what a customer reads on the QR menu. Dishes with a single price stay a
 * single item with no suffix.
 *
 * A handful of pork dishes had no price on the list at all. They are created
 * switched off: they appear in the back office to be priced, and business rule
 * 8 stops a disabled item reaching an order in the meantime.
 *
 * Items are only created, never overwritten. Once the restaurant is trading,
 * the back office is where prices change, and a seeder re-run during a deploy
 * must not quietly undo that.
 */
class MenuSeeder extends Seeder
{
    /**
     * category => [ [name, small price, large price?] ]
     *
     * A null price means the price list did not carry one.
     *
     * @var array<string, list<array{0: string, 1: int|null, 2?: int}>>
     */
    private array $menu = [
        'Fried Rice' => [
            ['Vege Fried Rice', 500, 700],
            ['Egg Fried Rice', 600, 800],
            ['Fish Fried Rice', 800, 1000],
            ['Chicken Fried Rice', 800, 1200],
            ['Prawns Fried Rice', 950, 1400],
            ['Cuttlefish Fried Rice', 950, 1450],
            ['Seafood Fried Rice', 900, 1450],
            ['Mix Fried Rice', 1000, 1600],
            ['K&D Special Fried Rice', 1200, 1800],
        ],
        'Biriyani' => [
            ['Chicken Biriyani', 800, 1200],
            ['Chicken ST Biriyani', 800, 1200],
        ],
        'Rice & Curry' => [
            ['Vege Rice & Curry', 330, 430],
            ['Egg Rice & Curry', 380, 480],
            ['Fish Rice & Curry', 420, 520],
            ['Chicken Rice & Curry', 450, 550],
            ['Roast Chicken Rice & Curry', 550, 650],
        ],
        'Kottu Rotti' => [
            ['Vege Kottu', 600, 800],
            ['Egg Kottu', 650, 900],
            ['Fish Kottu', 750, 1050],
            ['Chicken Kottu', 750, 1050],
            ['Seafood Kottu', 850, 1300],
            ['Mix Kottu', 900, 1400],
            ['Pork Kottu', null],
        ],
        'Mongolian Rice' => [
            ['Vege Mongolian Rice', 750, 1300],
            ['Egg Mongolian Rice', 850, 1400],
            ['Fish Mongolian Rice', 1000, 1500],
            ['Chicken Mongolian Rice', 1000, 1500],
            ['Prawns Mongolian Rice', 1200, 1700],
            ['Seafood Mongolian Rice', 1200, 1700],
            ['Pork Mongolian Rice', null],
            ['Mix Mongolian Rice', 1300, 1850],
        ],
        'Chopsuey Rice' => [
            ['Vege Chopsuey Rice', 850, 1300],
            ['Egg Chopsuey Rice', 950, 1400],
            ['Fish Chopsuey Rice', 1100, 1700],
            ['Chicken Chopsuey Rice', 1100, 1700],
            ['Prawns Chopsuey Rice', 1350, 1800],
            ['Seafood Chopsuey Rice', 1350, 1800],
            ['Pork Chopsuey Rice', null],
            ['Mix Chopsuey Rice', 1400, 1950],
        ],
        'Noodles' => [
            ['Vege Noodles', 550, 750],
            ['Egg Noodles', 600, 800],
            ['Fish Noodles', 700, 850],
            ['Chicken Noodles', 700, 850],
            ['Prawns Noodles', 850, 1200],
            ['Seafood Noodles', 950, 1400],
            ['Pork Noodles', null],
            ['Mix Noodles', 950, 1500],
        ],
        'Chopsuey Noodles' => [
            ['Vege Chopsuey Noodles', 850, 1300],
            ['Egg Chopsuey Noodles', 950, 1400],
            ['Fish Chopsuey Noodles', 1100, 1700],
            ['Chicken Chopsuey Noodles', 1100, 1700],
            ['Prawns Chopsuey Noodles', 1350, 1800],
            ['Cuttlefish Chopsuey Noodles', 1350, 1800],
            ['Seafood Chopsuey Noodles', 1350, 1800],
        ],
        'Cheese Kottu' => [
            ['Vege Cheese Kottu', 950, 1300],
            ['Egg Cheese Kottu', 950, 1300],
            ['Fish Cheese Kottu', 1000, 1600],
            ['Chicken Cheese Kottu', 1000, 1600],
            ['Seafood Cheese Kottu', 1200, 1800],
            ['Mix Cheese Kottu', 1300, 1950],
            ['Pork Cheese Kottu', null],
        ],
        'Short Eats' => [
            ['Parata', 50],
            ['Godambba Rotti', 70],
            ['Egg Rotti', 120],
            ['Vege Rotti', 100],
            ['Fish Rotti', 120],
            ['Egg Roll', 120],
            ['Chicken Samosa', 130],
            ['Chicken Shawarma', 350],
        ],
        'Omelette' => [
            ['Bullseye Omelette', 100],
            ['Plain Omelette', 450],
            ['Cheese Omelette', 600],
            ['Chicken & Cheese Omelette', 800],
            ['Chicken Omelette', 700],
        ],
        'Soup' => [
            ['Clear Vege Soup', 250, 300],
            ['Chicken Soup', 300, 400],
            ['Brown Onion Soup', 300, 400],
            ['Chicken with Noodles', 350, 500],
            ['Cream & Chicken Soup', 350, 500],
            ['Sweet Corn Soup', 350, 500],
            ['Mushroom Soup', 350, 500],
        ],
        'Breakfast Rice & Curry' => [
            ['Vege Breakfast Rice & Curry', 200],
            ['Egg Breakfast Rice & Curry', 250],
            ['Fish Breakfast Rice & Curry', 300],
            ['Chicken Breakfast Rice & Curry', 350],
            ['Pork Breakfast Rice & Curry', null],
            ['Prawns Breakfast Rice & Curry', 450],
        ],
        'Breakfast Noodles' => [
            ['Vege Breakfast Noodles', 150],
            ['Egg Breakfast Noodles', 200],
            ['Fish Breakfast Noodles', 250],
            ['Chicken Breakfast Noodles', 300],
            ['Pork Breakfast Noodles', null],
            ['Prawns Breakfast Noodles', 450],
        ],
        'Chicken Bites' => [
            ['Devilled Chicken', 950],
            ['Fried Chicken', 950],
            ['Hot Butter Chicken', 1200],
            ['Black Pepper Chicken', 1450],
            ['Chicken Stew', 1450],
            ['Chilli Chicken', 1450],
            ['Chicken Parts', 550],
        ],
        'Roast Chicken' => [
            ['Chicken Leg', 250],
            ['Roast Chicken 1/4', 600],
            ['Roast Chicken 1/2', 1100],
            ['Roast Chicken Full', 2400],
            ['Chicken Masala 1/4', 850],
            ['Chicken Masala 1/2', 1350],
            ['Chicken Masala Full', 2700],
            ['Chicken Sambal 1/4', 850],
            ['Chicken Sambal 1/2', 1250],
            ['Chicken Sambal Full', 2700],
        ],    ];

    public function run(): void
    {
        $skipped = [];

        foreach (array_values($this->menu) as $categoryIndex => $items) {
            $category = Category::query()->firstOrCreate(
                ['name' => array_keys($this->menu)[$categoryIndex]],
                ['is_active' => true, 'sort_order' => $categoryIndex + 1],
            );

            $position = 0;

            foreach ($items as $row) {
                [$name, $small] = $row;
                $large = $row[2] ?? null;

                if ($small === null) {
                    // No price on the list: created, but not sellable until
                    // somebody puts a figure against it.
                    $this->item($category, $name, 0, ++$position, active: false);
                    $skipped[] = "{$category->name} — {$name}";

                    continue;
                }

                if ($large === null) {
                    $this->item($category, $name, $small, ++$position);

                    continue;
                }

                $this->item($category, "{$name} (Small)", $small, ++$position);
                $this->item($category, "{$name} (Large)", $large, ++$position);
            }
        }

        if ($skipped !== []) {
            $this->command?->warn('  These items had no price on the list and were created switched off:');

            foreach ($skipped as $line) {
                $this->command?->line("    {$line}");
            }
        }
    }

    private function item(Category $category, string $name, int $price, int $position, bool $active = true): void
    {
        MenuItem::query()->firstOrCreate(
            ['category_id' => $category->id, 'name' => $name],
            ['price' => $price, 'is_active' => $active, 'sort_order' => $position],
        );
    }
}
