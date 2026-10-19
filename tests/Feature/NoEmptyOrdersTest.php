<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An order exists only once something is on it.
 *
 * Opening a sale used to create its order on the spot, and cashiers open the
 * order screen to check a price or search the menu as often as to sell. Each
 * look left an empty open order behind; a thousand had piled up before anyone
 * noticed.
 */
class NoEmptyOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_checking_the_menu_from_the_order_screen_writes_nothing(): void
    {
        $cashier = $this->cashier();
        $table = RestaurantTable::factory()->create(['name' => 'Table 9']);
        MenuItem::factory()->create(['name' => 'Chicken Kottu']);

        foreach ([route('pos.takeaway.create'), route('pos.phone.create'), route('pos.tables.show', $table)] as $screen) {
            $this->actingAs($cashier)->get($screen)
                ->assertOk()
                ->assertSee('New order')
                ->assertSee('Chicken Kottu');
        }

        $this->assertSame(0, Order::query()->count());
        $this->assertTrue($table->fresh()->isAvailable());
    }

    /** Hold, discount, cancel and the customer form all need an order to act on. */
    public function test_the_screen_offers_nothing_that_needs_an_order_until_one_is_open(): void
    {
        $this->actingAs($this->admin())->get(route('pos.phone.create'))
            ->assertOk()
            ->assertDontSee('Hold')
            ->assertDontSee('Cancel order')
            ->assertDontSee('Add mobile number');
    }

    public function test_the_first_item_opens_the_order_with_its_quantity_and_note(): void
    {
        $curry = MenuItem::factory()->create(['price' => 1200]);

        $this->actingAs($this->cashier())
            ->postJson(route('pos.takeaway.store'), [
                'menu_item_id' => $curry->id,
                'quantity' => 2,
                'note' => 'no chilli',
            ])
            ->assertCreated();

        $line = Order::query()->sole()->items()->sole();
        $this->assertSame(2, $line->quantity);
        $this->assertSame('no chilli', $line->note);
    }

    public function test_nothing_opens_without_an_item(): void
    {
        $cashier = $this->cashier();

        $this->actingAs($cashier)->post(route('pos.takeaway.store'))->assertSessionHasErrors('menu_item_id');
        $this->actingAs($cashier)->post(route('pos.phone.store'))->assertSessionHasErrors('menu_item_id');
        $this->actingAs($cashier)
            ->post(route('pos.tables.store', RestaurantTable::factory()->create()))
            ->assertSessionHasErrors('menu_item_id');

        $this->assertSame(0, Order::query()->count());
    }

    /** Opening and adding are one step, so a refused item leaves no order behind. */
    public function test_an_item_that_cannot_be_sold_opens_no_order(): void
    {
        $table = RestaurantTable::factory()->create();
        $off = MenuItem::factory()->inactive()->create(['name' => 'Seasonal Soup']);

        $this->actingAs($this->cashier())
            ->postJson(route('pos.tables.store', $table), ['menu_item_id' => $off->id])
            ->assertUnprocessable();

        $this->assertSame(0, Order::query()->count());
        $this->assertTrue($table->fresh()->isAvailable());
    }

    public function test_the_cleanup_removes_only_open_orders_with_nothing_on_them(): void
    {
        $cashier = $this->cashier();
        $table = RestaurantTable::factory()->create();

        $emptyTakeaway = $this->openTakeaway($cashier);
        $emptyTable = $this->openTable($table, $cashier);

        // Something on it: still being served.
        $running = $this->openTakeaway($cashier);
        $this->actingAs($cashier)->postJson(route('pos.orders.items.store', $running), [
            'menu_item_id' => MenuItem::factory()->create()->id,
        ]);

        // Sold and paid for.
        $sold = $this->openTakeaway($cashier);
        $this->actingAs($cashier)->postJson(route('pos.orders.items.store', $sold), [
            'menu_item_id' => MenuItem::factory()->create(['price' => 500])->id,
        ]);
        $this->actingAs($cashier)->post(route('pos.orders.checkout.store', $sold), [
            'method' => PaymentMethod::Cash->value,
            'tendered' => 500,
        ]);

        // Empty, but somebody chose to cancel it, so it stays on the books.
        $cancelled = $this->openTakeaway($cashier);
        $cancelled->forceFill(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()])->save();

        $migration = require database_path('migrations/2026_10_19_090000_delete_open_orders_left_empty.php');
        $migration->up();

        $this->assertModelMissing($emptyTakeaway);
        $this->assertModelMissing($emptyTable);
        $this->assertTrue($table->fresh()->isAvailable());

        $this->assertModelExists($running);
        $this->assertModelExists($sold);
        $this->assertModelExists($cancelled);
    }
}
