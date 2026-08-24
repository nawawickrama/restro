<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The restaurant's own price list, as loaded into the till. */
class MenuSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MenuSeeder::class);
    }

    public function test_it_loads_the_whole_price_list(): void
    {
        $this->assertSame(16, Category::query()->count());
        $this->assertSame(173, MenuItem::query()->count());
    }

    /**
     * An order line holds one price, so a dish with a small and a large price
     * becomes two items the cashier can tap.
     */
    public function test_a_dish_sold_in_two_sizes_becomes_two_items(): void
    {
        $small = MenuItem::query()->where('name', 'Chicken Fried Rice (Small)')->sole();
        $large = MenuItem::query()->where('name', 'Chicken Fried Rice (Large)')->sole();

        $this->assertSame('800.00', $small->price);
        $this->assertSame('1200.00', $large->price);
        $this->assertSame($small->category_id, $large->category_id);
        $this->assertSame('Fried Rice', $small->category->name);

        // A dish with one price keeps its name unqualified.
        $this->assertSame('350.00', MenuItem::query()->where('name', 'Chicken Shawarma')->sole()->price);
        $this->assertSame(0, MenuItem::query()->where('name', 'like', 'Chicken Shawarma (%')->count());
    }

    /**
     * The price list left some pork dishes blank. They exist so they can be
     * priced, and business rule 8 keeps them off an order until they are.
     */
    public function test_dishes_with_no_price_are_created_switched_off(): void
    {
        $unpriced = MenuItem::query()->where('is_active', false)->get();

        $this->assertCount(7, $unpriced);

        foreach ($unpriced as $item) {
            $this->assertStringContainsString('Pork', $item->name);
            $this->assertSame('0.00', $item->price);
            $this->assertFalse($item->isSellable());
        }
    }

    /** Nothing unpriced can reach a customer's screen. */
    public function test_unpriced_dishes_stay_off_the_public_menu(): void
    {
        $this->get(route('menu'))
            ->assertOk()
            ->assertSee('Chicken Fried Rice (Small)')
            ->assertSee('Rs. 800.00')
            ->assertDontSee('Pork Kottu');
    }

    /** The row the price list itself flagged as unverified is not on the menu. */
    public function test_the_unverified_row_was_left_out(): void
    {
        $this->assertSame(0, MenuItem::query()->where('name', 'like', '%Unclear%')->count());

        // Seven soups on the list, each in two sizes, and nothing else.
        $this->assertSame(14, MenuItem::query()->whereRelation('category', 'name', 'Soup')->count());
    }

    /**
     * Once trading, the back office owns prices. A seeder re-run during a
     * deploy must not quietly put them back.
     */
    public function test_re_running_it_neither_duplicates_nor_overwrites(): void
    {
        $item = MenuItem::query()->where('name', 'Chicken Fried Rice (Small)')->sole();
        $item->update(['price' => 875]);

        $this->seed(MenuSeeder::class);

        $this->assertSame(173, MenuItem::query()->count());
        $this->assertSame(16, Category::query()->count());
        $this->assertSame('875.00', $item->fresh()->price);
    }

    public function test_categories_keep_the_order_of_the_price_list(): void
    {
        $ordered = Category::query()->ordered()->pluck('name')->all();

        $this->assertSame('Fried Rice', $ordered[0]);
        $this->assertSame('Biriyani', $ordered[1]);
        $this->assertSame('Roast Chicken', end($ordered));
    }
}
