<?php

namespace Tests\Feature;

use App\Enums\CustomerSource;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Customers as records of their own, gathered from the orders that met them.
 */
class CustomerModuleTest extends TestCase
{
    use RefreshDatabase;

    /** Taking a number on an order is what puts somebody in the list. */
    public function test_capturing_a_number_creates_a_customer_tagged_with_its_source(): void
    {
        $cashier = $this->cashier();

        $cases = [
            ['pos.takeaway.store', CustomerSource::WalkIn, '0771111111'],
            ['pos.phone.store', CustomerSource::Phone, '0772222222'],
        ];

        foreach ($cases as [$route, $expected, $phone]) {
            $this->actingAs($cashier)->post(route($route));
            $order = Order::query()->latest('id')->first();

            $this->actingAs($cashier)->post(route('pos.orders.customer', $order), [
                'customer_phone' => $phone,
                'customer_name' => 'Nimal',
            ]);

            $customer = Customer::query()->where('phone_digits', $phone)->sole();
            $this->assertSame($expected, $customer->source);
            $this->assertSame($customer->id, $order->fresh()->customer_id);
        }

        // And a dine-in table that leaves a number.
        $table = RestaurantTable::factory()->create();
        $this->actingAs($cashier)->post(route('pos.tables.select', $table));
        $dineIn = Order::query()->latest('id')->first();
        $this->actingAs($cashier)->post(route('pos.orders.customer', $dineIn), [
            'customer_phone' => '0773333333',
        ]);

        $this->assertSame(CustomerSource::DineIn, Customer::query()->where('phone_digits', '0773333333')->sole()->source);
    }

    /** The same person, however the number was typed, is one record. */
    public function test_a_number_typed_differently_is_recognised_as_the_same_customer(): void
    {
        $cashier = $this->cashier();

        foreach (['077 123 4567', '0771234567', '077-123-4567'] as $index => $phone) {
            $this->actingAs($cashier)->post(route('pos.takeaway.store'));
            $order = Order::query()->latest('id')->first();

            $this->actingAs($cashier)->post(route('pos.orders.customer', $order), [
                'customer_phone' => $phone,
            ]);
        }

        $this->assertSame(1, Customer::query()->count());
        $this->assertSame(3, Customer::query()->sole()->orders()->count());
    }

    /**
     * A record keeps the source it was first met on — a caller who later eats
     * in was still met over the phone — but will accept a name it never had.
     */
    public function test_a_returning_customer_keeps_its_source_and_gains_a_missing_name(): void
    {
        $cashier = $this->cashier();

        $this->actingAs($cashier)->post(route('pos.phone.store'));
        $first = Order::query()->sole();
        $this->actingAs($cashier)->post(route('pos.orders.customer', $first), ['customer_phone' => '0771234567']);

        $customer = Customer::query()->sole();
        $this->assertNull($customer->name);

        $this->actingAs($cashier)->post(route('pos.takeaway.store'));
        $second = Order::query()->latest('id')->first();
        $this->actingAs($cashier)->post(route('pos.orders.customer', $second), [
            'customer_phone' => '0771234567',
            'customer_name' => 'Nimal Perera',
        ]);

        $customer->refresh();
        $this->assertSame('Nimal Perera', $customer->name);
        $this->assertSame(CustomerSource::Phone, $customer->source);
        $this->assertSame(1, Customer::query()->count());
    }

    /** A name with no number identifies nobody, so it starts no record. */
    public function test_a_name_without_a_number_creates_no_customer(): void
    {
        $cashier = $this->cashier();

        $this->actingAs($cashier)->post(route('pos.takeaway.store'));
        $order = Order::query()->sole();

        $this->actingAs($cashier)->post(route('pos.orders.customer', $order), ['customer_name' => 'Kamala']);

        $this->assertSame(0, Customer::query()->count());
        $this->assertNull($order->fresh()->customer_id);
        $this->assertSame('Kamala', $order->fresh()->customer_name);
    }

    public function test_a_customer_can_be_added_by_hand(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('customers.store'), [
            'phone' => '077 999 8888',
            'name' => 'Sunil',
            'note' => 'No chilli',
        ])->assertRedirect();

        $customer = Customer::query()->sole();
        $this->assertSame(CustomerSource::Manual, $customer->source);
        $this->assertSame('0779998888', $customer->phone_digits);
        $this->assertSame('No chilli', $customer->note);
    }

    public function test_adding_a_number_that_already_exists_is_refused(): void
    {
        $admin = $this->admin();
        Customer::factory()->create(['phone' => '0771234567', 'phone_digits' => '0771234567']);

        $this->actingAs($admin)->post(route('customers.store'), ['phone' => '077 123 4567'])
            ->assertSessionHasErrors('phone_digits');

        $this->assertSame(1, Customer::query()->count());
    }

    public function test_the_list_filters_by_how_the_customer_was_met(): void
    {
        $admin = $this->admin();

        Customer::factory()->source(CustomerSource::Phone)->create(['name' => 'Phone Person']);
        Customer::factory()->source(CustomerSource::WalkIn)->create(['name' => 'Walk Person']);
        Customer::factory()->source(CustomerSource::Manual)->create(['name' => 'Manual Person']);

        $this->actingAs($admin)->get(route('customers.index', ['source' => 'phone']))
            ->assertOk()
            ->assertSee('Phone Person')
            ->assertDontSee('Walk Person')
            ->assertDontSee('Manual Person');

        $this->actingAs($admin)->get(route('customers.index'))
            ->assertOk()
            ->assertSee('Phone Person')
            ->assertSee('Walk Person');
    }

    public function test_the_list_searches_by_name_or_number_however_typed(): void
    {
        $admin = $this->admin();
        Customer::factory()->create(['name' => 'Nimal Perera', 'phone' => '077 123 4567', 'phone_digits' => '0771234567']);
        Customer::factory()->create(['name' => 'Kamala Silva', 'phone' => '0719999999', 'phone_digits' => '0719999999']);

        foreach (['Nimal', '0771234567', '077 123'] as $term) {
            $this->actingAs($admin)->get(route('customers.index', ['search' => $term]))
                ->assertOk()
                ->assertSee('Nimal Perera')
                ->assertDontSee('Kamala Silva');
        }
    }

    /** The list must not run a query per customer to total their spending. */
    public function test_the_list_totals_spending_in_sql(): void
    {
        $admin = $this->admin();
        Customer::factory()->count(30)->create();

        $this->actingAs($admin)->get(route('customers.index'))->assertOk();

        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('customers.index', ['per_page' => 100]))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(8, $queries, "Expected a handful of queries, ran {$queries}.");
    }

    public function test_a_customer_page_shows_what_they_have_spent(): void
    {
        $cashier = $this->cashier();
        $item = MenuItem::factory()->create(['price' => 1500]);

        $this->actingAs($cashier)->post(route('pos.takeaway.store'));
        $order = Order::query()->sole();
        $this->actingAs($cashier)->post(route('pos.orders.customer', $order), [
            'customer_phone' => '0771234567', 'customer_name' => 'Nimal',
        ]);
        $this->actingAs($cashier)->postJson(route('pos.orders.items.store', $order), ['menu_item_id' => $item->id]);
        $this->actingAs($cashier)->post(route('pos.orders.checkout.store', $order), [
            'method' => 'cash', 'tendered' => 1500,
        ]);

        $this->actingAs($this->admin())->get(route('customers.show', Customer::query()->sole()))
            ->assertOk()
            ->assertSee('Nimal')
            ->assertSee('Rs. 1,500.00')
            ->assertSee($order->order_number);
    }

    /** Deleting a customer must never take their order history with them. */
    public function test_deleting_a_customer_keeps_their_orders(): void
    {
        $cashier = $this->cashier();

        $this->actingAs($cashier)->post(route('pos.takeaway.store'));
        $order = Order::query()->sole();
        $this->actingAs($cashier)->post(route('pos.orders.customer', $order), [
            'customer_phone' => '0771234567', 'customer_name' => 'Nimal',
        ]);

        $this->actingAs($this->admin())->delete(route('customers.destroy', Customer::query()->sole()))
            ->assertRedirect(route('customers.index'));

        $order->refresh();
        $this->assertSame(0, Customer::query()->count());
        $this->assertNull($order->customer_id);

        // The order still says who it was for.
        $this->assertSame('Nimal', $order->customer_name);
        $this->assertSame('0771234567', $order->customer_phone);
    }

    public function test_the_module_is_behind_its_own_permission(): void
    {
        $cashier = $this->cashier();

        $this->actingAs($cashier)->get(route('customers.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('customers.create'))->assertForbidden();

        // It is a permission, not a role: granting it is all it takes.
        $cashier->givePermissionTo(Permissions::MANAGE_CUSTOMERS);

        $this->actingAs($cashier)->get(route('customers.index'))->assertOk();
    }
}
