<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three journeys from the specification, walked end to end over HTTP:
 * open the POS, choose food, take payment, print a receipt.
 */
class PosFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_dine_in_order_runs_from_table_to_receipt(): void
    {
        $cashier = $this->cashier();
        $table = RestaurantTable::factory()->create(['name' => 'Table 1']);
        $burger = MenuItem::factory()->create(['price' => 950]);

        $this->actingAs($cashier)->get(route('pos.home'))
            ->assertOk()
            ->assertSee('Table 1')
            ->assertSee('DINE IN');

        // Tapping the table opens an order and occupies it.
        $this->actingAs($cashier)->post(route('pos.tables.select', $table))->assertRedirect();

        $order = Order::query()->sole();
        $this->assertSame(OrderType::DineIn, $order->type);

        $this->actingAs($cashier)
            ->postJson(route('pos.orders.items.store', $order), ['menu_item_id' => $burger->id, 'quantity' => 2])
            ->assertOk()
            ->assertJsonPath('order.formatted.total', 'Rs. 1,900.00')
            ->assertJsonPath('order.items.0.quantity', 2);

        // Tapping the same table again returns to the running order.
        $this->actingAs($cashier)->post(route('pos.tables.select', $table))
            ->assertRedirect(route('pos.orders.show', $order));

        $this->actingAs($cashier)->get(route('pos.orders.checkout', $order))->assertOk();

        $this->actingAs($cashier)->post(route('pos.orders.checkout.store', $order), [
            'method' => PaymentMethod::Cash->value,
            'tendered' => 2000,
        ])->assertRedirect(route('orders.receipt', $order));

        $order->refresh();
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertTrue($table->fresh()->isAvailable());

        $this->actingAs($cashier)->get(route('orders.receipt', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Table 1');
    }

    public function test_a_walk_in_takeaway_order_needs_no_customer_details(): void
    {
        $cashier = $this->cashier();
        $item = MenuItem::factory()->create(['price' => 450]);

        $this->actingAs($cashier)->post(route('pos.takeaway.store'))->assertRedirect();

        $order = Order::query()->sole();
        $this->assertSame(OrderType::Takeaway, $order->type);
        $this->assertNull($order->customer_name);

        $this->actingAs($cashier)
            ->postJson(route('pos.orders.items.store', $order), ['menu_item_id' => $item->id])
            ->assertOk();

        $this->actingAs($cashier)->post(route('pos.orders.checkout.store', $order), [
            'method' => PaymentMethod::Card->value,
        ])->assertRedirect();

        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
    }

    /** Food first, contact details second — the order a real call happens in. */
    public function test_a_phone_order_takes_the_food_before_the_number(): void
    {
        $cashier = $this->cashier();
        $item = MenuItem::factory()->create(['price' => 600]);

        $this->actingAs($cashier)->post(route('pos.phone.store'))->assertRedirect();

        $order = Order::query()->sole();
        $this->assertSame(OrderType::PhoneTakeaway, $order->type);
        $this->assertNull($order->customer_phone);

        // The items go on while the customer is still talking.
        $this->actingAs($cashier)
            ->postJson(route('pos.orders.items.store', $order), ['menu_item_id' => $item->id])
            ->assertOk();

        // Checkout is closed off until a number has been taken.
        $this->actingAs($cashier)->get(route('pos.orders.checkout', $order))->assertForbidden();

        $this->actingAs($cashier)->post(route('pos.orders.customer', $order), [])
            ->assertSessionHasErrors('customer_phone');

        // A number alone is enough; the name is optional.
        $this->actingAs($cashier)->post(route('pos.orders.customer', $order), [
            'customer_phone' => '0771234567',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame('0771234567', $order->customer_phone);
        $this->assertNull($order->customer_name);

        $this->actingAs($cashier)->get(route('pos.orders.checkout', $order))->assertOk();

        $this->actingAs($cashier)->post(route('pos.orders.checkout.store', $order), [
            'method' => 'cash',
            'tendered' => 600,
        ])->assertRedirect(route('orders.receipt', $order));
    }

    public function test_a_phone_order_keeps_the_name_when_one_is_given(): void
    {
        $cashier = $this->cashier();
        $this->actingAs($cashier)->post(route('pos.phone.store'));
        $order = Order::query()->sole();

        $this->actingAs($cashier)->post(route('pos.orders.customer', $order), [
            'customer_phone' => '0771234567',
            'customer_name' => 'Nimal',
            'note' => 'Collecting at 7pm',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame('Nimal', $order->customer_name);
        $this->assertSame('Collecting at 7pm', $order->note);
    }

    /** The number is required on a phone order and optional on the other two. */
    public function test_customer_details_are_optional_on_dine_in_and_takeaway(): void
    {
        $cashier = $this->cashier();
        $table = RestaurantTable::factory()->create();

        $this->actingAs($cashier)->post(route('pos.tables.select', $table));
        $dineIn = Order::query()->sole();

        // A name alone is accepted; no number needed.
        $this->actingAs($cashier)->post(route('pos.orders.customer', $dineIn), [
            'customer_name' => 'Nimal',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Nimal', $dineIn->fresh()->customer_name);

        // Saving nothing at all is fine too.
        $this->actingAs($cashier)->post(route('pos.takeaway.store'));
        $takeaway = Order::query()->latest('id')->first();

        $this->actingAs($cashier)->post(route('pos.orders.customer', $takeaway), [])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($takeaway->fresh()->customer_phone);
    }

    public function test_the_order_screen_offers_customer_details_on_every_type(): void
    {
        $cashier = $this->cashier();
        $table = RestaurantTable::factory()->create();

        $this->actingAs($cashier)->post(route('pos.tables.select', $table));
        $dineIn = Order::query()->sole();

        $this->actingAs($cashier)->get(route('pos.orders.show', $dineIn))
            ->assertOk()
            ->assertSee('Add customer details')
            ->assertSee('(optional)')
            // The mandatory phone-order prompt must not appear here.
            ->assertDontSee('Needed before checkout');

        $this->actingAs($cashier)->post(route('pos.phone.store'));
        $phone = Order::query()->latest('id')->first();

        $this->actingAs($cashier)->get(route('pos.orders.show', $phone))
            ->assertOk()
            ->assertSee('Needed before checkout');
    }

    public function test_a_phone_order_moves_through_its_collection_stages(): void
    {
        $cashier = $this->cashier();
        $this->actingAs($cashier)->post(route('pos.phone.store'));

        $order = Order::query()->sole();

        $this->actingAs($cashier)->post(route('pos.orders.fulfillment', [$order, 'ready']))->assertRedirect();

        $this->assertSame('ready', $order->fresh()->fulfillment_status->value);
    }

    public function test_quantities_can_be_changed_and_lines_removed_from_the_order_screen(): void
    {
        $cashier = $this->cashier();
        $item = MenuItem::factory()->create(['price' => 200]);

        $this->actingAs($cashier)->post(route('pos.takeaway.store'));
        $order = Order::query()->sole();

        $this->actingAs($cashier)
            ->postJson(route('pos.orders.items.store', $order), ['menu_item_id' => $item->id]);

        $line = $order->items()->sole();

        $this->actingAs($cashier)
            ->patchJson(route('pos.orders.items.update', [$order, $line]), ['quantity' => 3])
            ->assertOk()
            ->assertJsonPath('order.formatted.total', 'Rs. 600.00');

        $this->actingAs($cashier)
            ->patchJson(route('pos.orders.items.update', [$order, $line]), ['note' => 'No sauce'])
            ->assertOk()
            ->assertJsonPath('order.items.0.note', 'No sauce');

        $this->actingAs($cashier)
            ->deleteJson(route('pos.orders.items.destroy', [$order, $line]))
            ->assertOk()
            ->assertJsonPath('order.formatted.total', 'Rs. 0.00')
            ->assertJsonCount(0, 'order.items');
    }

    public function test_a_completed_order_redirects_away_from_the_order_screen(): void
    {
        $cashier = $this->cashier();
        $item = MenuItem::factory()->create(['price' => 100]);

        $this->actingAs($cashier)->post(route('pos.takeaway.store'));
        $order = Order::query()->sole();

        $this->actingAs($cashier)->postJson(route('pos.orders.items.store', $order), ['menu_item_id' => $item->id]);
        $this->actingAs($cashier)->post(route('pos.orders.checkout.store', $order), [
            'method' => PaymentMethod::Cash->value,
            'tendered' => 100,
        ]);

        $this->actingAs($cashier)->get(route('pos.orders.show', $order))
            ->assertRedirect(route('orders.show', $order));
    }

    public function test_taking_a_busy_table_shows_a_message_instead_of_an_error_page(): void
    {
        $cashier = $this->cashier();
        $table = RestaurantTable::factory()->create();

        // Occupy the table with someone else's order.
        $this->actingAs($this->cashier())->post(route('pos.tables.select', $table));

        // Tapping a busy table opens the running order rather than refusing.
        $this->actingAs($cashier)->post(route('pos.tables.select', $table))
            ->assertRedirect(route('pos.orders.show', Order::query()->sole()));
    }

    public function test_the_order_history_can_be_filtered(): void
    {
        $cashier = $this->cashier();
        $this->actingAs($cashier)->post(route('pos.takeaway.store'));
        $order = Order::query()->sole();

        $this->actingAs($cashier)->get(route('orders.index'))
            ->assertOk()
            ->assertSee($order->order_number);

        $this->actingAs($cashier)->get(route('orders.index', ['type' => OrderType::DineIn->value]))
            ->assertOk()
            ->assertDontSee($order->order_number);

        $this->actingAs($cashier)->get(route('orders.index', ['search' => $order->order_number]))
            ->assertOk()
            ->assertSee($order->order_number);
    }
}
