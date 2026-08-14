<?php

namespace Tests\Feature;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\PosOperationException;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Services\CheckoutService;
use App\Services\OrderService;
use App\Services\SettingsService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The rules from section 19 of the specification, one test each. These are the
 * behaviours a restaurant would notice breaking, so they are worth pinning
 * down before anything else.
 */
class BusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orders;

    private CheckoutService $checkout;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orders = app(OrderService::class);
        $this->checkout = app(CheckoutService::class);
    }

    public function test_a_completed_order_cannot_be_edited(): void
    {
        $order = $this->paidOrder();

        $this->expectException(PosOperationException::class);

        $this->orders->addItem($order, MenuItem::factory()->create());
    }

    public function test_a_cancelled_order_cannot_be_edited(): void
    {
        $user = $this->cashier();
        $order = $this->orders->startTakeaway($user);
        $this->orders->cancel($order);

        $this->expectException(PosOperationException::class);

        $this->orders->addItem($order, MenuItem::factory()->create());
    }

    public function test_an_open_dine_in_order_keeps_its_table_occupied(): void
    {
        $table = RestaurantTable::factory()->create();

        $this->orders->startDineIn($table, $this->cashier());

        $this->assertTrue($table->fresh()->isOccupied());
    }

    public function test_completing_a_dine_in_order_frees_the_table(): void
    {
        $user = $this->cashier();
        $table = RestaurantTable::factory()->create();
        $order = $this->orders->startDineIn($table, $user);
        $this->orders->addItem($order, MenuItem::factory()->create(['price' => 500]));

        $this->checkout->checkout($order, PaymentMethod::Cash, $user, 500);

        $this->assertTrue($table->fresh()->isAvailable());
    }

    public function test_a_table_cannot_have_two_active_dine_in_orders(): void
    {
        $table = RestaurantTable::factory()->create();
        $this->orders->startDineIn($table, $this->cashier());

        $this->expectException(PosOperationException::class);

        $this->orders->startDineIn($table, $this->cashier());
    }

    /** The unique index on the generated column is the last line of defence. */
    public function test_the_database_itself_rejects_a_second_open_dine_in_order(): void
    {
        $user = $this->cashier();
        $table = RestaurantTable::factory()->create();
        $this->orders->startDineIn($table, $user);

        $this->expectException(UniqueConstraintViolationException::class);

        // Bypasses the service on purpose: this asserts the schema-level rule.
        Order::query()->create([
            'order_number' => 'ORD-TEST-0001',
            'type' => OrderType::DineIn,
            'status' => OrderStatus::Open,
            'payment_status' => PaymentStatus::Unpaid,
            'table_id' => $table->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_order_item_prices_are_stored_when_the_item_is_added(): void
    {
        $item = MenuItem::factory()->create(['price' => 900]);
        $order = $this->orders->startTakeaway($this->cashier());
        $this->orders->addItem($order, $item);

        $item->update(['price' => 1500]);

        $this->assertSame('900.00', $order->fresh()->items->first()->unit_price);
    }

    public function test_changing_a_menu_price_does_not_change_old_orders(): void
    {
        $user = $this->cashier();
        $item = MenuItem::factory()->create(['price' => 1000]);
        $order = $this->orders->startTakeaway($user);
        $this->orders->addItem($order, $item, 2);
        $this->checkout->checkout($order, PaymentMethod::Cash, $user, 2000);

        $item->update(['price' => 4000]);

        $this->assertSame('2000.00', $order->fresh()->total);
    }

    public function test_disabled_menu_items_cannot_be_added_to_orders(): void
    {
        $order = $this->orders->startTakeaway($this->cashier());

        $this->expectException(PosOperationException::class);

        $this->orders->addItem($order, MenuItem::factory()->inactive()->create());
    }

    public function test_items_in_a_disabled_category_cannot_be_added_to_orders(): void
    {
        $item = MenuItem::factory()->create();
        $item->category->update(['is_active' => false]);

        $order = $this->orders->startTakeaway($this->cashier());

        $this->expectException(PosOperationException::class);

        $this->orders->addItem($order, $item);
    }

    public function test_a_discount_cannot_exceed_the_subtotal(): void
    {
        $order = $this->orders->startTakeaway($this->cashier());
        $this->orders->addItem($order, MenuItem::factory()->create(['price' => 500]));

        $this->expectException(PosOperationException::class);

        $this->orders->applyDiscount($order, 600);
    }

    public function test_an_order_cannot_be_completed_without_covering_the_total(): void
    {
        $user = $this->cashier();
        $order = $this->orders->startTakeaway($user);
        $this->orders->addItem($order, MenuItem::factory()->create(['price' => 1000]));

        try {
            $this->checkout->checkout($order, PaymentMethod::Cash, $user, 400);
            $this->fail('Checkout should have been refused.');
        } catch (PosOperationException) {
            // The order must be untouched: still open, still unpaid.
        }

        $order->refresh();
        $this->assertSame(OrderStatus::Open, $order->status);
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertSame(0, $order->payments()->count());
    }

    public function test_an_empty_order_cannot_be_checked_out(): void
    {
        $user = $this->cashier();
        $order = $this->orders->startTakeaway($user);

        $this->expectException(PosOperationException::class);

        $this->checkout->checkout($order, PaymentMethod::Cash, $user, 1000);
    }

    public function test_a_payment_records_the_change_due(): void
    {
        $user = $this->cashier();
        $order = $this->orders->startTakeaway($user);
        $this->orders->addItem($order, MenuItem::factory()->create(['price' => 2500]));

        $payment = $this->checkout->checkout($order, PaymentMethod::Cash, $user, 3000);

        $this->assertSame('2500.00', $payment->amount);
        $this->assertSame('500.00', $payment->change_amount);
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    public function test_a_card_payment_does_not_record_change(): void
    {
        $user = $this->cashier();
        $order = $this->orders->startTakeaway($user);
        $this->orders->addItem($order, MenuItem::factory()->create(['price' => 2500]));

        $payment = $this->checkout->checkout($order, PaymentMethod::Card, $user, reference: 'AUTH-42');

        $this->assertNull($payment->tendered);
        $this->assertSame('0.00', $payment->change_amount);
        $this->assertSame('AUTH-42', $payment->reference);
    }

    public function test_a_phone_order_opens_empty_so_the_food_can_be_taken_down_first(): void
    {
        $order = $this->orders->startPhoneOrder($this->cashier());

        $this->assertNull($order->customer_phone);
        $this->assertNull($order->customer_name);
        $this->assertSame(FulfillmentStatus::Pending, $order->fulfillment_status);
        $this->assertTrue($order->needsCustomerPhone());
    }

    public function test_customer_details_are_captured_after_the_items(): void
    {
        $order = $this->orders->startPhoneOrder($this->cashier());
        $this->orders->addItem($order, MenuItem::factory()->create(['price' => 400]));

        $this->orders->setCustomer($order, [
            'customer_phone' => '0771234567',
            'customer_name' => 'Nimal',
            'note' => 'Collecting at 7pm',
        ]);

        $order->refresh();
        $this->assertSame('0771234567', $order->customer_phone);
        $this->assertSame('Nimal', $order->customer_name);
        $this->assertFalse($order->needsCustomerPhone());
    }

    /** The caller often never gives a name; the number is the part that matters. */
    public function test_a_phone_order_can_be_completed_with_a_number_and_no_name(): void
    {
        $user = $this->cashier();
        $order = $this->orders->startPhoneOrder($user);
        $this->orders->addItem($order, MenuItem::factory()->create(['price' => 400]));
        $this->orders->setCustomer($order, ['customer_phone' => '0771234567']);

        $this->checkout->checkout($order, PaymentMethod::Cash, $user, 400);

        $order->refresh();
        $this->assertNull($order->customer_name);
        $this->assertSame(OrderStatus::Completed, $order->status);
    }

    public function test_a_phone_order_cannot_be_completed_without_a_mobile_number(): void
    {
        $user = $this->cashier();
        $order = $this->orders->startPhoneOrder($user);
        $this->orders->addItem($order, MenuItem::factory()->create(['price' => 400]));

        try {
            $this->checkout->checkout($order, PaymentMethod::Cash, $user, 400);
            $this->fail('Checkout should have been refused.');
        } catch (PosOperationException) {
            // Still open, still unpaid, no payment recorded.
        }

        $order->refresh();
        $this->assertSame(OrderStatus::Open, $order->status);
        $this->assertSame(0, $order->payments()->count());
    }

    public function test_tax_is_applied_after_the_discount(): void
    {
        app(SettingsService::class)->set(['tax_percentage' => '10']);

        $order = $this->orders->startTakeaway($this->cashier());
        $this->orders->addItem($order, MenuItem::factory()->create(['price' => 1000]), 2);
        $this->orders->applyDiscount($order, 200);

        $order->refresh();

        // (2000 - 200) * 10% = 180
        $this->assertSame('2000.00', $order->subtotal);
        $this->assertSame('180.00', $order->tax_amount);
        $this->assertSame('1980.00', $order->total);
    }

    public function test_adding_the_same_item_twice_merges_into_one_line(): void
    {
        $item = MenuItem::factory()->create(['price' => 300]);
        $order = $this->orders->startTakeaway($this->cashier());

        $this->orders->addItem($order, $item);
        $this->orders->addItem($order, $item);

        $order->refresh();
        $this->assertCount(1, $order->items);
        $this->assertSame(2, $order->items->first()->quantity);
        $this->assertSame('600.00', $order->total);
    }

    public function test_an_order_can_be_moved_to_a_free_table_but_not_an_occupied_one(): void
    {
        $user = $this->cashier();
        [$first, $second, $third] = RestaurantTable::factory()->count(3)->create();

        $order = $this->orders->startDineIn($first, $user);
        $this->orders->startDineIn($second, $user);

        $this->orders->moveToTable($order, $third);
        $this->assertSame($third->id, $order->fresh()->table_id);

        $this->expectException(PosOperationException::class);
        $this->orders->moveToTable($order, $second);
    }

    public function test_order_numbers_are_sequential_within_a_day(): void
    {
        $user = $this->cashier();

        $first = $this->orders->startTakeaway($user);
        $second = $this->orders->startTakeaway($user);

        $prefix = 'ORD-'.now()->format('Ymd').'-';
        $this->assertSame($prefix.'0001', $first->order_number);
        $this->assertSame($prefix.'0002', $second->order_number);
    }

    private function paidOrder(): Order
    {
        $user = $this->cashier();
        $order = $this->orders->startTakeaway($user);
        $this->orders->addItem($order, MenuItem::factory()->create(['price' => 100]));
        $this->checkout->checkout($order, PaymentMethod::Cash, $user, 100);

        return $order->fresh();
    }
}
