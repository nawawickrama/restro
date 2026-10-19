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

        // Tapping the table shows its order screen, but writes nothing yet.
        $this->actingAs($cashier)->get(route('pos.tables.show', $table))->assertOk()->assertSee('New order');
        $this->assertSame(0, Order::query()->count());
        $this->assertTrue($table->fresh()->isAvailable());

        // The first item is what opens the order and occupies the table.
        $opened = $this->actingAs($cashier)
            ->postJson(route('pos.tables.store', $table), ['menu_item_id' => $burger->id, 'quantity' => 2])
            ->assertCreated();

        $order = Order::query()->sole();
        $opened->assertJsonPath('redirect', route('pos.orders.show', $order));
        $this->assertSame(OrderType::DineIn, $order->type);
        $this->assertSame(2, $order->items()->sole()->quantity);
        $this->assertSame('1900.00', $order->total);
        $this->assertFalse($table->fresh()->isAvailable());

        // Tapping the same table again returns to the running order.
        $this->actingAs($cashier)->get(route('pos.tables.show', $table))
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

        $this->actingAs($cashier)->get(route('pos.takeaway.create'))->assertOk();
        $this->actingAs($cashier)
            ->postJson(route('pos.takeaway.store'), ['menu_item_id' => $item->id])
            ->assertCreated();

        $order = Order::query()->sole();
        $this->assertSame(OrderType::Takeaway, $order->type);
        $this->assertNull($order->customer_name);

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

        // The items go on while the customer is still talking; the first one
        // opens the order.
        $this->actingAs($cashier)->get(route('pos.phone.create'))->assertOk();
        $this->actingAs($cashier)
            ->postJson(route('pos.phone.store'), ['menu_item_id' => $item->id])
            ->assertCreated();

        $order = Order::query()->sole();
        $this->assertSame(OrderType::PhoneTakeaway, $order->type);
        $this->assertNull($order->customer_phone);

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
        $this->openPhoneOrder($cashier);
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

        $this->openTable($table, $cashier);
        $dineIn = Order::query()->sole();

        // A name alone is accepted; no number needed.
        $this->actingAs($cashier)->post(route('pos.orders.customer', $dineIn), [
            'customer_name' => 'Nimal',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Nimal', $dineIn->fresh()->customer_name);

        // Saving nothing at all is fine too.
        $this->openTakeaway($cashier);
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

        $this->openTable($table, $cashier);
        $dineIn = Order::query()->sole();

        $this->actingAs($cashier)->get(route('pos.orders.show', $dineIn))
            ->assertOk()
            ->assertSee('Add customer details')
            ->assertSee('(optional)')
            // The mandatory phone-order prompt must not appear here.
            ->assertDontSee('Needed before checkout');

        $this->openPhoneOrder($cashier);
        $phone = Order::query()->latest('id')->first();

        $this->actingAs($cashier)->get(route('pos.orders.show', $phone))
            ->assertOk()
            ->assertSee('Needed before checkout');
    }

    public function test_a_phone_order_moves_through_its_collection_stages(): void
    {
        $cashier = $this->cashier();
        $this->openPhoneOrder($cashier);

        $order = Order::query()->sole();

        $this->actingAs($cashier)->post(route('pos.orders.fulfillment', [$order, 'ready']))->assertRedirect();

        $this->assertSame('ready', $order->fresh()->fulfillment_status->value);
    }

    public function test_quantities_can_be_changed_and_lines_removed_from_the_order_screen(): void
    {
        $cashier = $this->cashier();
        $item = MenuItem::factory()->create(['price' => 200]);

        $this->openTakeaway($cashier);
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

        $this->openTakeaway($cashier);
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
        $this->openTable($table, $this->cashier());

        // Tapping a busy table opens the running order rather than refusing.
        $this->actingAs($cashier)->get(route('pos.tables.show', $table))
            ->assertRedirect(route('pos.orders.show', Order::query()->sole()));

        // If it was taken while this cashier was on its order screen, their
        // first item is refused with a message and no second order opens.
        $this->actingAs($cashier)
            ->postJson(route('pos.tables.store', $table), ['menu_item_id' => MenuItem::factory()->create()->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'already has an open order'));

        $this->assertSame(1, Order::query()->count());
    }

    public function test_the_order_history_can_be_filtered(): void
    {
        $cashier = $this->cashier();
        $this->openTakeaway($cashier);
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
