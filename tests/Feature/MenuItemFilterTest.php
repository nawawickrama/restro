<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finding an item on the menu screen — most often to check whether something
 * is still being sold.
 */
class MenuItemFilterTest extends TestCase
{
    use RefreshDatabase;

    private function menu(): void
    {
        $category = Category::factory()->create(['name' => 'Rice']);

        MenuItem::factory()->create(['category_id' => $category->id, 'name' => 'On Sale Today']);
        MenuItem::factory()->inactive()->create(['category_id' => $category->id, 'name' => 'Taken Off Sale']);
    }

    public function test_it_shows_everything_by_default(): void
    {
        $this->menu();

        $this->actingAs($this->admin())->get(route('menu-items.index'))
            ->assertOk()
            ->assertSee('On Sale Today')
            ->assertSee('Taken Off Sale');
    }

    public function test_it_filters_to_active_items(): void
    {
        $this->menu();

        $this->actingAs($this->admin())->get(route('menu-items.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee('On Sale Today')
            ->assertDontSee('Taken Off Sale');
    }

    public function test_it_filters_to_disabled_items(): void
    {
        $this->menu();

        $this->actingAs($this->admin())->get(route('menu-items.index', ['status' => 'disabled']))
            ->assertOk()
            ->assertSee('Taken Off Sale')
            ->assertDontSee('On Sale Today');
    }

    /** The counts on the buttons say how many are in each state. */
    public function test_it_counts_each_state(): void
    {
        $category = Category::factory()->create();
        MenuItem::factory()->count(3)->create(['category_id' => $category->id]);
        MenuItem::factory()->count(2)->inactive()->create(['category_id' => $category->id]);

        $counts = $this->actingAs($this->admin())->get(route('menu-items.index'))
            ->assertOk()
            ->viewData('counts');

        $this->assertSame(['all' => 5, 'active' => 3, 'disabled' => 2], $counts);
    }

    /** Status and the other filters narrow together rather than replacing. */
    public function test_status_composes_with_the_category_and_search_filters(): void
    {
        $rice = Category::factory()->create(['name' => 'Rice']);
        $drinks = Category::factory()->create(['name' => 'Drinks']);

        MenuItem::factory()->inactive()->create(['category_id' => $rice->id, 'name' => 'Old Rice Dish']);
        MenuItem::factory()->inactive()->create(['category_id' => $drinks->id, 'name' => 'Old Drink']);
        MenuItem::factory()->create(['category_id' => $rice->id, 'name' => 'Current Rice Dish']);

        $this->actingAs($this->admin())
            ->get(route('menu-items.index', ['status' => 'disabled', 'category' => $rice->id]))
            ->assertOk()
            ->assertSee('Old Rice Dish')
            ->assertDontSee('Old Drink')
            ->assertDontSee('Current Rice Dish');
    }

    /** A stale bookmark must not silently hide half the menu. */
    public function test_an_unknown_status_shows_everything(): void
    {
        $this->menu();

        $this->actingAs($this->admin())->get(route('menu-items.index', ['status' => 'nonsense']))
            ->assertOk()
            ->assertSee('On Sale Today')
            ->assertSee('Taken Off Sale');
    }
}
