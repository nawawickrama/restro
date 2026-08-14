<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A Blade typo on a screen nobody visits during a test is invisible until a
 * cashier finds it mid-service. This walks every screen once.
 */
class ScreensRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_screen_renders(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Sign in');
    }

    public function test_every_pos_screen_renders(): void
    {
        $admin = $this->admin();
        $table = RestaurantTable::factory()->create();
        $item = MenuItem::factory()->create(['price' => 750]);

        $this->actingAs($admin)->get(route('pos.home'))->assertOk();

        $this->actingAs($admin)->post(route('pos.tables.select', $table));
        $order = Order::query()->sole();

        // The order screen carries the whole menu and the order as JSON.
        $this->actingAs($admin)->get(route('pos.orders.show', $order))
            ->assertOk()
            ->assertSee('Current order')
            ->assertSee($item->name)
            ->assertSee($table->name);

        $this->actingAs($admin)->postJson(route('pos.orders.items.store', $order), ['menu_item_id' => $item->id]);

        $this->actingAs($admin)->get(route('pos.orders.checkout', $order))
            ->assertOk()
            ->assertSee('Amount received')
            ->assertSee('Rs. 750.00');
    }

    public function test_the_phone_order_screen_prompts_for_a_number_then_shows_it(): void
    {
        $admin = $this->admin();
        MenuItem::factory()->create();

        $this->actingAs($admin)->post(route('pos.phone.store'));
        $order = Order::query()->sole();

        $this->actingAs($admin)->get(route('pos.orders.show', $order))
            ->assertOk()
            ->assertSee('Add mobile number')
            ->assertSee('Number needed');

        $this->actingAs($admin)->post(route('pos.orders.customer', $order), [
            'customer_phone' => '0771234567',
        ]);

        $this->actingAs($admin)->get(route('pos.orders.show', $order))
            ->assertOk()
            ->assertSee('0771234567')
            ->assertSee('No name given')
            ->assertDontSee('Number needed');
    }

    public function test_every_admin_form_renders(): void
    {
        $admin = $this->admin();
        $category = Category::factory()->create();
        $item = MenuItem::factory()->create();
        $table = RestaurantTable::factory()->create();

        $screens = [
            route('categories.create'),
            route('categories.edit', $category),
            route('menu-items.create'),
            route('menu-items.edit', $item),
            route('tables.create'),
            route('tables.edit', $table),
            route('users.create'),
            route('users.edit', $admin),
            route('settings.edit'),
            route('reports.index'),
            route('dashboard'),
        ];

        foreach ($screens as $screen) {
            $this->actingAs($admin)->get($screen)->assertOk();
        }
    }

    public function test_the_order_detail_and_receipt_render_for_each_order_type(): void
    {
        $admin = $this->admin();
        $item = MenuItem::factory()->create(['price' => 500]);
        $table = RestaurantTable::factory()->create();

        $this->actingAs($admin)->post(route('pos.tables.select', $table));
        $this->actingAs($admin)->post(route('pos.phone.store'));
        $this->actingAs($admin)->post(route('pos.takeaway.store'));

        $phoneOrder = Order::query()->where('type', 'phone_takeaway')->sole();
        $this->actingAs($admin)->post(route('pos.orders.customer', $phoneOrder), [
            'customer_phone' => '0771234567',
            'customer_name' => 'Nimal',
        ]);

        foreach (Order::query()->get() as $order) {
            $this->actingAs($admin)->postJson(route('pos.orders.items.store', $order), ['menu_item_id' => $item->id]);

            $this->actingAs($admin)->get(route('orders.show', $order))->assertOk()->assertSee($order->order_number);
            $this->actingAs($admin)->get(route('orders.receipt', $order))->assertOk()->assertSee($order->order_number);
        }
    }

    public function test_the_pos_home_screen_shows_free_and_occupied_tables(): void
    {
        $admin = $this->admin();
        RestaurantTable::factory()->create(['name' => 'Table A']);
        $busy = RestaurantTable::factory()->create(['name' => 'Table B']);

        $this->actingAs($admin)->post(route('pos.tables.select', $busy));

        $this->actingAs($admin)->get(route('pos.home'))
            ->assertOk()
            ->assertSee('Table A')
            ->assertSee('Table B')
            ->assertSee('Free')
            ->assertSee('Occupied');
    }
}
