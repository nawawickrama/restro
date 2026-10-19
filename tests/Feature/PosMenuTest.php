<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The menu on its own, one tap from the POS home screen, for answering "how
 * much is…?" or "do you have…?" at the counter without starting a sale.
 */
class PosMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_screen_leads_to_the_menu(): void
    {
        $this->actingAs($this->cashier())->get(route('pos.home'))
            ->assertOk()
            ->assertSee('MENU')
            ->assertSee(route('pos.menu'));
    }

    public function test_it_shows_what_is_on_with_its_price_and_description(): void
    {
        $rice = Category::factory()->create(['name' => 'Rice and Curry']);
        MenuItem::factory()->for($rice)->create([
            'name' => 'Chicken Kottu',
            'price' => 950,
            'description' => 'Chopped roti, egg and chicken curry',
        ]);
        MenuItem::factory()->for($rice)->inactive()->create(['name' => 'Seasonal Soup']);

        $this->actingAs($this->cashier())->get(route('pos.menu'))
            ->assertOk()
            ->assertSee('Rice and Curry')
            ->assertSee('Chicken Kottu')
            ->assertSee(money(950))
            ->assertSee('Chopped roti, egg and chicken curry')
            ->assertDontSee('Seasonal Soup');
    }

    public function test_looking_at_it_starts_no_order(): void
    {
        MenuItem::factory()->create();

        $this->actingAs($this->cashier())->get(route('pos.menu'))->assertOk();

        $this->assertSame(0, Order::query()->count());
    }

    public function test_it_is_for_signed_in_staff(): void
    {
        $this->get(route('pos.menu'))->assertRedirect(route('login'));
    }
}
