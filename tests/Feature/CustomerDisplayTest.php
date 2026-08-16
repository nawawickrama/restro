<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The second screen, the one facing the customer.
 *
 * It is fed entirely by the cashier's window over a BroadcastChannel, so what
 * the server can be tested on is: the screen exists, it is protected, it never
 * carries anything the public should not see, and the cashier pages publish
 * the right state at the right moment.
 */
class CustomerDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_display_needs_the_pos_permission(): void
    {
        $this->get(route('pos.display'))->assertRedirect(route('login'));

        $stranger = $this->cashier();
        $stranger->revokePermissionTo('view_pos');
        $stranger->syncRoles([]);

        $this->actingAs($stranger)->get(route('pos.display'))->assertForbidden();

        $this->actingAs($this->cashier())->get(route('pos.display'))->assertOk();
    }

    public function test_the_display_shows_the_restaurant_and_its_logo(): void
    {
        $this->actingAs($this->cashier())->get(route('pos.display'))
            ->assertOk()
            ->assertSee('images/logo.png')
            ->assertSee('customerDisplay(');
    }

    /**
     * The display renders an empty shell. No order is embedded in it, so a
     * screen left running never shows a stale order or anybody's details.
     */
    public function test_the_display_carries_no_order_data_of_its_own(): void
    {
        $cashier = $this->cashier();
        $item = MenuItem::factory()->create(['name' => 'Chicken Burger', 'price' => 950]);

        $this->actingAs($cashier)->post(route('pos.phone.store'));
        $order = Order::query()->sole();
        $this->actingAs($cashier)->post(route('pos.orders.customer', $order), [
            'customer_phone' => '0771234567',
            'customer_name' => 'Nimal Perera',
        ]);
        $this->actingAs($cashier)->postJson(route('pos.orders.items.store', $order), [
            'menu_item_id' => $item->id,
        ]);

        $this->actingAs($cashier)->get(route('pos.display'))
            ->assertOk()
            ->assertDontSee('Chicken Burger')
            ->assertDontSee('Nimal Perera')
            ->assertDontSee('0771234567')
            ->assertDontSee($order->order_number);
    }

    public function test_the_pos_offers_a_button_to_open_the_display(): void
    {
        $this->actingAs($this->cashier())->get(route('pos.home'))
            ->assertOk()
            ->assertSee('customerDisplayLauncher(')
            ->assertSee(route('pos.display'));
    }

    /** Landing on the POS home means nothing is being served: back to idle. */
    public function test_the_pos_home_screen_returns_the_display_to_idle(): void
    {
        $this->actingAs($this->cashier())->get(route('pos.home'))
            ->assertOk()
            ->assertSee("showOnCustomerDisplay?.({ screen: 'idle' })", false);
    }

    public function test_a_completed_sale_publishes_a_thank_you_with_the_change(): void
    {
        $cashier = $this->cashier();
        $item = MenuItem::factory()->create(['price' => 2500]);

        $this->actingAs($cashier)->post(route('pos.takeaway.store'));
        $order = Order::query()->sole();
        $this->actingAs($cashier)->postJson(route('pos.orders.items.store', $order), [
            'menu_item_id' => $item->id,
        ]);

        $this->actingAs($cashier)->post(route('pos.orders.checkout.store', $order), [
            'method' => PaymentMethod::Cash->value,
            'tendered' => 3000,
        ]);

        // The redirect carries the flash that marks this as a fresh sale.
        $this->actingAs($cashier)
            ->withSession(['status' => 'done'])
            ->get(route('orders.receipt', $order))
            ->assertOk()
            ->assertSee('customer-display-state')
            ->assertSee('"screen":"done"', false)
            ->assertSee('"change_due":true', false)
            ->assertSee('500.00')                       // change due
            ->assertSee($order->order_number);          // takeaway: number to call
    }

    /** A dine-in customer is at a table, so there is no number to wait for. */
    public function test_a_dine_in_sale_publishes_no_collection_number(): void
    {
        $cashier = $this->cashier();
        $item = MenuItem::factory()->create(['price' => 500]);
        $table = RestaurantTable::factory()->create();

        $this->actingAs($cashier)->post(route('pos.tables.select', $table));
        $order = Order::query()->sole();
        $this->actingAs($cashier)->postJson(route('pos.orders.items.store', $order), [
            'menu_item_id' => $item->id,
        ]);
        $this->actingAs($cashier)->post(route('pos.orders.checkout.store', $order), [
            'method' => PaymentMethod::Card->value,
        ]);

        $this->actingAs($cashier)
            ->withSession(['status' => 'done'])
            ->get(route('orders.receipt', $order))
            ->assertOk()
            ->assertSee('"collect":null', false);
    }

    /**
     * Reprinting an old receipt must not throw a "Thank you" in front of
     * whoever is standing at the counter now.
     */
    public function test_reprinting_an_old_receipt_leaves_the_display_alone(): void
    {
        $cashier = $this->cashier();
        $item = MenuItem::factory()->create(['price' => 500]);

        $this->actingAs($cashier)->post(route('pos.takeaway.store'));
        $order = Order::query()->sole();
        $this->actingAs($cashier)->postJson(route('pos.orders.items.store', $order), [
            'menu_item_id' => $item->id,
        ]);
        $this->actingAs($cashier)->post(route('pos.orders.checkout.store', $order), [
            'method' => PaymentMethod::Cash->value,
            'tendered' => 500,
        ]);

        // The checkout redirect flashed a "completed" message that is still
        // waiting to be read. Spend it on one request, the way following the
        // redirect would, so the next visit is a genuine reprint.
        $this->actingAs($cashier)->get(route('orders.receipt', $order))->assertOk();

        $this->actingAs($cashier)->get(route('orders.receipt', $order))
            ->assertOk()
            ->assertDontSee('customer-display-state');
    }

    public function test_the_checkout_screen_mirrors_the_payment(): void
    {
        $cashier = $this->cashier();
        $item = MenuItem::factory()->create(['price' => 1200]);

        $this->actingAs($cashier)->post(route('pos.takeaway.store'));
        $order = Order::query()->sole();
        $this->actingAs($cashier)->postJson(route('pos.orders.items.store', $order), [
            'menu_item_id' => $item->id,
        ]);

        $this->actingAs($cashier)->get(route('pos.orders.checkout', $order))
            ->assertOk()
            ->assertSee("screen: 'paying'", false)
            ->assertSee('$watch(\'tendered\'', false);
    }
}
