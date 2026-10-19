<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The customer's bill is printed on a till roll, so it says each thing once.
 */
class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_plain_sale_prints_nothing_twice(): void
    {
        $cashier = $this->cashier();
        $tea = MenuItem::factory()->create(['name' => 'Plain Tea', 'price' => 150]);
        $rice = MenuItem::factory()->create(['name' => 'Kottu', 'price' => 900]);

        $this->openTakeaway($cashier);
        $order = Order::query()->sole();
        $this->actingAs($cashier)->postJson(route('pos.orders.items.store', $order), ['menu_item_id' => $tea->id]);
        $this->actingAs($cashier)->postJson(route('pos.orders.items.store', $order), [
            'menu_item_id' => $rice->id,
            'quantity' => 2,
        ]);
        $this->actingAs($cashier)->post(route('pos.orders.checkout.store', $order), [
            'method' => PaymentMethod::Cash->value,
            'tendered' => 2000,
        ]);

        $bill = $this->customerSlip($order);

        // No discount or tax, so the subtotal would only repeat the total.
        $this->assertStringNotContainsString('Subtotal', $bill);
        $this->assertStringContainsString('Total', $bill);

        // A unit price only where it differs from the line amount.
        $this->assertStringContainsString('@ '.money(900), $bill);
        $this->assertStringNotContainsString('@ '.money(150), $bill);

        // One line for what was handed over, one for what went back.
        $this->assertStringContainsString('Paid by Cash', $bill);
        $this->assertStringContainsString(money(2000), $bill);
        $this->assertStringContainsString('Change', $bill);
        $this->assertStringContainsString(money(50), $bill);
        $this->assertStringNotContainsString('Cash received', $bill);
    }

    /** Just the customer's half of the page, so the kitchen's cannot mask a gap. */
    private function customerSlip(Order $order): string
    {
        $html = $this->actingAs($this->cashier())
            ->get(route('orders.receipt', $order))
            ->assertOk()
            ->getContent();

        $end = strpos($html, 'kitchen-ticket');
        $this->assertNotFalse($end, 'The receipt carried no kitchen copy.');

        return substr($html, 0, $end);
    }
}
