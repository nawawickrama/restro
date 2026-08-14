<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The search box above the open takeaway and phone orders on the POS home
 * screen. Filtering happens in the browser, so these tests check that the
 * screen ships everything the filter needs to match on.
 */
class OpenOrderSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_screen_ships_a_searchable_payload_for_open_orders(): void
    {
        $cashier = $this->cashier();

        $this->actingAs($cashier)->post(route('pos.phone.store'));
        $phoneOrder = Order::query()->sole();
        $this->actingAs($cashier)->post(route('pos.orders.customer', $phoneOrder), [
            'customer_phone' => '077 123 4567',
            'customer_name' => 'Nimal Perera',
        ]);

        $response = $this->actingAs($cashier)->get(route('pos.home'));

        $response->assertOk()
            ->assertSee('Search by name, mobile or order number')
            ->assertSee('Nimal Perera', false)
            ->assertSee('077 123 4567', false);

        $payload = $response->viewData('openOrdersPayload')->first();

        // Lower-cased once on the server, so typing does no work per keystroke.
        $this->assertStringContainsString('nimal perera', $payload['haystack']);
        $this->assertStringContainsString(strtolower($phoneOrder->order_number), $payload['haystack']);

        // A number typed without spaces still matches one written with them.
        $this->assertSame('0771234567', $payload['phone_digits']);
    }

    public function test_a_walk_in_takeaway_is_searchable_by_its_number_and_type(): void
    {
        $cashier = $this->cashier();
        $this->actingAs($cashier)->post(route('pos.takeaway.store'));
        $order = Order::query()->sole();

        $payload = $this->actingAs($cashier)->get(route('pos.home'))->viewData('openOrdersPayload')->first();

        $this->assertStringContainsString(strtolower($order->order_number), $payload['haystack']);
        $this->assertStringContainsString('takeaway', $payload['haystack']);
        $this->assertStringContainsString('walk in', $payload['haystack']);
        $this->assertSame('', $payload['phone_digits']);
    }

    public function test_only_open_takeaway_and_phone_orders_are_listed(): void
    {
        $cashier = $this->cashier();
        $item = MenuItem::factory()->create(['price' => 500]);
        $table = RestaurantTable::factory()->create();

        // A dine-in order belongs to the table plan, not this list.
        $this->actingAs($cashier)->post(route('pos.tables.select', $table));

        // A completed takeaway has left the counter.
        $this->actingAs($cashier)->post(route('pos.takeaway.store'));
        $done = Order::query()->latest('id')->first();
        $this->actingAs($cashier)->postJson(route('pos.orders.items.store', $done), ['menu_item_id' => $item->id]);
        $this->actingAs($cashier)->post(route('pos.orders.checkout.store', $done), [
            'method' => 'cash', 'tendered' => 500,
        ]);

        // This one is still waiting.
        $this->actingAs($cashier)->post(route('pos.takeaway.store'));
        $waiting = Order::query()->latest('id')->first();

        $payload = $this->actingAs($cashier)->get(route('pos.home'))->viewData('openOrdersPayload');

        $this->assertCount(1, $payload);
        $this->assertSame($waiting->id, $payload->first()['id']);
    }

    /**
     * The item count is shown on each card. It must come from SQL rather than
     * loading every line of every open order to add them up in PHP.
     */
    public function test_the_home_screen_does_not_load_the_lines_of_every_open_order(): void
    {
        $cashier = $this->cashier();
        $item = MenuItem::factory()->create(['price' => 250]);

        foreach (range(1, 5) as $ignored) {
            $this->actingAs($cashier)->post(route('pos.takeaway.store'));
            $order = Order::query()->latest('id')->first();
            $this->actingAs($cashier)->postJson(route('pos.orders.items.store', $order), [
                'menu_item_id' => $item->id, 'quantity' => 3,
            ]);
        }

        $this->actingAs($cashier)->get(route('pos.home'))->assertOk();

        DB::enableQueryLog();
        $response = $this->actingAs($cashier)->get(route('pos.home'))->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $payload = $response->viewData('openOrdersPayload');

        $this->assertCount(5, $payload);
        $this->assertSame('3 items', $payload->first()['items']);

        // No query selects the order_items rows themselves.
        $this->assertTrue(
            $queries->every(fn (string $sql) => ! str_contains($sql, 'select * from `order_items`')),
            'The home screen should count items in SQL, not load them: '.$queries->implode(' | '),
        );
    }

    /**
     * A regression guard for a Blade trap: the inline `@php(...)` form on this
     * page and a `@php ... @endphp` block cannot coexist, because Blade's
     * block regex matches from the first `@php` to the first `@endphp` and
     * swallows everything between them. The payload is built in the controller
     * to keep the two apart.
     */
    public function test_the_home_screen_has_no_php_block_that_could_swallow_the_page(): void
    {
        $view = file_get_contents(resource_path('views/pos/home.blade.php'));

        $this->assertStringNotContainsString('@endphp', $view);
    }
}
