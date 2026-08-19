<?php

namespace Tests\Feature;

use App\Enums\CustomerSource;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A customer the restaurant already knows, ordering again.
 *
 * The point of these is that recognising somebody must never get in the way of
 * serving them: the till takes the order exactly as it would for a stranger,
 * and knowing who they are only ever adds information.
 */
class ReturningCustomerTest extends TestCase
{
    use RefreshDatabase;

    /** The order is taken normally — a known number is not a duplicate error. */
    public function test_a_known_number_on_an_order_is_accepted_not_refused(): void
    {
        $cashier = $this->cashier();
        $item = MenuItem::factory()->create(['price' => 800]);

        Customer::factory()->create([
            'name' => 'Nimal Perera',
            'phone' => '077 123 4567',
            'phone_digits' => '0771234567',
            'source' => CustomerSource::Manual,
        ]);

        $this->actingAs($cashier)->post(route('pos.takeaway.store'));
        $order = Order::query()->sole();

        $this->actingAs($cashier)->post(route('pos.orders.customer', $order), [
            'customer_phone' => '0771234567',
        ])->assertRedirect()->assertSessionHasNoErrors();

        // Linked to the existing record rather than starting a second one.
        $this->assertSame(1, Customer::query()->count());
        $this->assertSame(Customer::query()->sole()->id, $order->fresh()->customer_id);

        // And the order goes all the way through.
        $this->actingAs($cashier)->postJson(route('pos.orders.items.store', $order), ['menu_item_id' => $item->id]);
        $this->actingAs($cashier)->post(route('pos.orders.checkout.store', $order), [
            'method' => 'cash', 'tendered' => 800,
        ])->assertRedirect(route('orders.receipt', $order));
    }

    /**
     * A regular who reads out only their number is still named on the receipt,
     * the customer display and the order list.
     */
    public function test_a_known_number_brings_its_name_onto_the_order(): void
    {
        $cashier = $this->cashier();

        Customer::factory()->create([
            'name' => 'Nimal Perera',
            'phone' => '0771234567',
            'phone_digits' => '0771234567',
        ]);

        $this->actingAs($cashier)->post(route('pos.phone.store'));
        $order = Order::query()->sole();

        $this->actingAs($cashier)->post(route('pos.orders.customer', $order), [
            'customer_phone' => '077 123 4567',
        ]);

        $this->assertSame('Nimal Perera', $order->fresh()->customer_name);
        $this->assertSame('Nimal Perera', $order->fresh()->customerLabel());
    }

    /** A name the cashier types wins over the one on file. */
    public function test_a_typed_name_is_not_overwritten_by_the_stored_one(): void
    {
        $cashier = $this->cashier();

        Customer::factory()->create([
            'name' => 'Nimal Perera',
            'phone' => '0771234567',
            'phone_digits' => '0771234567',
        ]);

        $this->actingAs($cashier)->post(route('pos.takeaway.store'));
        $order = Order::query()->sole();

        $this->actingAs($cashier)->post(route('pos.orders.customer', $order), [
            'customer_phone' => '0771234567',
            'customer_name' => 'Nimal (brother)',
        ]);

        $this->assertSame('Nimal (brother)', $order->fresh()->customer_name);

        // The record on file is left alone: it already had a name.
        $this->assertSame('Nimal Perera', Customer::query()->sole()->name);
    }

    public function test_the_lookup_recognises_a_number_however_it_is_typed(): void
    {
        $cashier = $this->cashier();

        $customer = Customer::factory()->create([
            'name' => 'Nimal Perera',
            'phone' => '077 123 4567',
            'phone_digits' => '0771234567',
        ]);

        foreach (['0771234567', '077 123 4567', '077-123-4567'] as $typed) {
            $this->actingAs($cashier)->getJson(route('pos.customer.lookup', ['phone' => $typed]))
                ->assertOk()
                ->assertJsonPath('found', true)
                ->assertJsonPath('name', 'Nimal Perera');
        }

        $this->actingAs($cashier)->getJson(route('pos.customer.lookup', ['phone' => '0769999999']))
            ->assertOk()
            ->assertJsonPath('found', false);
    }

    /**
     * The lookup answers whole numbers only. A short prefix would let anybody
     * holding the POS walk the customer list out of the database.
     */
    public function test_the_lookup_refuses_to_answer_a_partial_number(): void
    {
        $cashier = $this->cashier();

        Customer::factory()->create(['phone' => '0771234567', 'phone_digits' => '0771234567']);

        foreach (['0', '07', '077', '0771', '077123'] as $prefix) {
            $this->actingAs($cashier)->getJson(route('pos.customer.lookup', ['phone' => $prefix]))
                ->assertOk()
                ->assertJsonPath('found', false);
        }
    }

    public function test_the_lookup_is_behind_the_pos_permission(): void
    {
        // A session route, so a stranger is sent to sign in rather than told 401.
        $this->get(route('pos.customer.lookup', ['phone' => '0771234567']))
            ->assertRedirect(route('login'));

        $stranger = $this->cashier();
        $stranger->syncRoles([]);
        $stranger->revokePermissionTo('view_pos');

        $this->actingAs($stranger)->get(route('pos.customer.lookup', ['phone' => '0771234567']))
            ->assertForbidden();
    }

    public function test_the_order_screen_carries_the_recognition_lookup(): void
    {
        $cashier = $this->cashier();

        $this->actingAs($cashier)->post(route('pos.phone.store'));
        $order = Order::query()->sole();

        $this->actingAs($cashier)->get(route('pos.orders.show', $order))
            ->assertOk()
            ->assertSee(route('pos.customer.lookup'))
            ->assertSee('previous order');
    }
}
