<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The kitchen's copy of the bill.
 *
 * It rides the same print job as the customer's receipt, so one press of Print
 * produces both slips. It carries what a cook needs and nothing else: what to
 * make, how many, and where it is going.
 */
class KitchenTicketTest extends TestCase
{
    use RefreshDatabase;

    /** Rings up a paid order with the given items, as the POS would. */
    private function sell(array $items, ?RestaurantTable $table = null): Order
    {
        $cashier = $this->cashier();

        $table
            ? $this->actingAs($cashier)->post(route('pos.tables.select', $table))
            : $this->actingAs($cashier)->post(route('pos.takeaway.store'));

        $order = Order::query()->latest('id')->sole();

        foreach ($items as [$item, $quantity, $note]) {
            $this->actingAs($cashier)->postJson(route('pos.orders.items.store', $order), [
                'menu_item_id' => $item->id,
                'quantity' => $quantity,
                'note' => $note,
            ]);
        }

        $this->actingAs($cashier)->post(route('pos.orders.checkout.store', $order), [
            'method' => PaymentMethod::Cash->value,
            'tendered' => 100000,
        ]);

        return $order->fresh();
    }

    public function test_the_kitchen_copy_prints_alongside_the_customer_bill(): void
    {
        $rice = MenuItem::factory()->create(['name' => 'Chicken Fried Rice', 'price' => 850]);
        $order = $this->sell([[$rice, 2, null]]);

        $page = $this->actingAs($this->cashier())->get(route('orders.receipt', $order));

        $page->assertOk()
            ->assertSee('Kitchen')
            ->assertSee('Chicken Fried Rice')
            // The break is what makes the printer cut between the two slips.
            ->assertSee('kitchen-ticket', false);

        // Both slips come off the same job: the dish is on the page twice.
        $this->assertSame(2, substr_count($page->getContent(), 'Chicken Fried Rice'));
    }

    /** Quantity, price and the time it was rung up — the four things asked for. */
    public function test_it_carries_the_items_their_quantity_price_and_the_time(): void
    {
        $rice = MenuItem::factory()->create(['name' => 'Kottu', 'price' => 900]);
        $order = $this->sell([[$rice, 3, null]]);

        $ticket = $this->kitchenSlip($order);

        $this->assertStringContainsString('Kottu', $ticket);
        $this->assertStringContainsString('>3<', $ticket);              // quantity
        $this->assertStringContainsString('2,700.00', $ticket);         // line price
        $this->assertStringContainsString(
            $order->completed_at->format('d M Y, g:i A'),
            $ticket,
        );
    }

    /** A note is an instruction to the cook, so it has to survive onto the slip. */
    public function test_an_item_note_reaches_the_kitchen(): void
    {
        $curry = MenuItem::factory()->create(['name' => 'Devilled Chicken', 'price' => 1200]);
        $order = $this->sell([[$curry, 1, 'no chilli']]);

        $this->assertStringContainsString('no chilli', $this->kitchenSlip($order));
    }

    /** Without the order number and table the kitchen cannot match food to a bill. */
    public function test_it_says_which_order_and_where_it_is_going(): void
    {
        $table = RestaurantTable::factory()->create(['name' => 'Table 4']);
        $item = MenuItem::factory()->create(['price' => 500]);
        $order = $this->sell([[$item, 1, null]], $table);

        $ticket = $this->kitchenSlip($order);

        $this->assertStringContainsString($order->order_number, $ticket);
        $this->assertStringContainsString('Table 4', $ticket);
    }

    /** Nothing on it that a cook has no use for. */
    public function test_it_stays_plain(): void
    {
        $item = MenuItem::factory()->create(['price' => 500]);
        $order = $this->sell([[$item, 1, null]]);

        $ticket = $this->kitchenSlip($order);

        foreach (['logo', 'Subtotal', 'Total', 'Paid by', 'Change', 'Served by'] as $unwanted) {
            $this->assertStringNotContainsString($unwanted, $ticket);
        }
    }

    /** Just the kitchen's half of the page, so the customer's cannot mask a gap. */
    private function kitchenSlip(Order $order): string
    {
        $html = $this->actingAs($this->cashier())
            ->get(route('orders.receipt', $order))
            ->assertOk()
            ->getContent();

        $start = strpos($html, 'kitchen-ticket');
        $this->assertNotFalse($start, 'The receipt carried no kitchen copy.');

        return substr($html, $start);
    }
}
