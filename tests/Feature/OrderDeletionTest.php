<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Services\ReportService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deleting an order outright.
 *
 * Not the same act as cancelling: a cancelled order stays on the books with a
 * cancelled status, while a deleted one leaves no trace and takes its money out
 * of every figure the restaurant looks at.
 */
class OrderDeletionTest extends TestCase
{
    use RefreshDatabase;

    /** Rings up a paid order, as the POS would. */
    private function sell(int $price, int $quantity = 1, ?RestaurantTable $table = null): Order
    {
        $cashier = $this->cashier();
        $item = MenuItem::factory()->create(['price' => $price]);

        $table
            ? $this->actingAs($cashier)->post(route('pos.tables.select', $table))
            : $this->actingAs($cashier)->post(route('pos.takeaway.store'));

        $order = Order::query()->latest('id')->sole();

        $this->actingAs($cashier)->postJson(route('pos.orders.items.store', $order), [
            'menu_item_id' => $item->id,
            'quantity' => $quantity,
        ]);

        $this->actingAs($cashier)->post(route('pos.orders.checkout.store', $order), [
            'method' => PaymentMethod::Cash->value,
            'tendered' => 100000,
        ]);

        return $order->fresh();
    }

    public function test_deleting_an_order_removes_the_record_its_items_and_its_payments(): void
    {
        $order = $this->sell(850, 2);

        $this->assertDatabaseHas('order_items', ['order_id' => $order->id]);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id]);

        $this->actingAs($this->admin())
            ->delete(route('orders.destroy', $order))
            ->assertRedirect(route('orders.index'));

        // The row itself, and everything hanging off it.
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertSame(0, OrderItem::query()->where('order_id', $order->id)->count());
        $this->assertSame(0, Payment::query()->where('order_id', $order->id)->count());
    }

    /** The point of the whole feature: the money leaves the takings. */
    public function test_the_money_comes_out_of_the_reports(): void
    {
        $kept = $this->sell(1000);
        $deleted = $this->sell(1700);

        $reports = app(ReportService::class);
        $window = [today()->startOfDay(), today()->endOfDay()];

        $before = $reports->salesSummary(...$window);
        $this->assertSame(2700.0, (float) $before['total']);
        $this->assertSame(2, $before['orders']);

        $this->actingAs($this->admin())->delete(route('orders.destroy', $deleted));

        $after = $reports->salesSummary(...$window);
        $this->assertSame(1000.0, (float) $after['total'], 'The deleted order still counted towards sales.');
        $this->assertSame(1, $after['orders']);

        // And the payment breakdown with it, which is summed from its own table.
        // by_method is a flat map of method => amount, and every method is
        // always present, so the sum of its values is the day's takings.
        $this->assertSame(1000.0, (float) collect($after['by_method'])->sum());

        $this->assertDatabaseHas('orders', ['id' => $kept->id]);
    }

    /** Item-level reporting is summed from the order lines, so it must drop too. */
    public function test_the_items_stop_counting_as_sold(): void
    {
        $order = $this->sell(500, 4);
        $reports = app(ReportService::class);
        $window = [today()->startOfDay(), today()->endOfDay()];

        $this->assertNotEmpty($reports->salesByItem(...$window));

        $this->actingAs($this->admin())->delete(route('orders.destroy', $order));

        $this->assertEmpty($reports->salesByItem(...$window), 'A deleted order still had items in the top sellers.');
    }

    /**
     * Occupancy is derived from open dine-in orders, so deleting one frees the
     * table. Seating it again is the real proof: the unique index that enforces
     * one open order per table would refuse if the row were still there.
     */
    public function test_deleting_an_open_order_frees_its_table(): void
    {
        $table = RestaurantTable::factory()->create();
        $cashier = $this->cashier();

        $this->actingAs($cashier)->post(route('pos.tables.select', $table));
        $order = Order::query()->latest('id')->sole();

        $this->actingAs($this->admin())->delete(route('orders.destroy', $order));

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);

        $this->actingAs($cashier)->post(route('pos.tables.select', $table));
        $this->assertSame(1, Order::query()->where('table_id', $table->id)->open()->count());
    }

    /** Deleting sales is how a till is robbed, so it is not a cashier's to do. */
    public function test_a_cashier_cannot_delete_an_order(): void
    {
        $order = $this->sell(900);

        $this->actingAs($this->cashier())
            ->delete(route('orders.destroy', $order))
            ->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    /** Nor is the button offered to them — the screen matches the permission. */
    public function test_the_button_shows_only_to_someone_who_may_use_it(): void
    {
        $order = $this->sell(900);

        $this->actingAs($this->cashier())->get(route('orders.show', $order))
            ->assertOk()
            ->assertDontSee('Delete permanently');

        $this->actingAs($this->admin())->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Delete permanently');
    }

    /** It can be granted deliberately, which is the intended way in. */
    public function test_the_permission_can_be_handed_to_a_trusted_user(): void
    {
        $order = $this->sell(600);

        $cashier = $this->cashier();
        $cashier->givePermissionTo(Permissions::DELETE_ORDERS);

        $this->actingAs($cashier)
            ->delete(route('orders.destroy', $order))
            ->assertRedirect(route('orders.index'));

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    /** A guest gets nowhere near it. */
    public function test_a_guest_cannot_delete_an_order(): void
    {
        $order = $this->sell(600);

        $this->delete(route('orders.destroy', $order))->assertRedirect(route('login'));

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    /** The confirmation says what it cost, since that is the unrecoverable part. */
    public function test_it_reports_what_was_removed(): void
    {
        $order = $this->sell(1250, 2);

        $this->actingAs($this->admin())
            ->delete(route('orders.destroy', $order))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, $order->order_number)
                && str_contains($status, '2,500.00'));
    }

    /** Deleting an order must not take the customer record with it. */
    public function test_the_customer_survives_their_order_being_deleted(): void
    {
        $cashier = $this->cashier();
        $item = MenuItem::factory()->create(['price' => 700]);

        $this->actingAs($cashier)->post(route('pos.phone.store'));
        $order = Order::query()->latest('id')->sole();

        $this->actingAs($cashier)->postJson(route('pos.orders.items.store', $order), ['menu_item_id' => $item->id]);
        $this->actingAs($cashier)->post(route('pos.orders.customer', $order), [
            'customer_name' => 'Nimal',
            'customer_phone' => '0771234567',
        ]);
        $this->actingAs($cashier)->post(route('pos.orders.checkout.store', $order), [
            'method' => PaymentMethod::Cash->value,
            'tendered' => 1000,
        ]);

        $this->assertDatabaseHas('customers', ['phone_digits' => '0771234567']);

        $this->actingAs($this->admin())->delete(route('orders.destroy', $order));

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('customers', ['phone_digits' => '0771234567']);
    }

    /** Admin holds every permission, so it holds this one. */
    public function test_admin_holds_the_new_permission(): void
    {
        $this->assertTrue($this->admin()->can(Permissions::DELETE_ORDERS));
        $this->assertContains(Permissions::DELETE_ORDERS, Permissions::all());
        $this->assertNotContains(Permissions::DELETE_ORDERS, Permissions::cashier());
    }
}
