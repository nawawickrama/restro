<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Access is decided by permissions, never by role name. These tests lock that
 * in: a cashier is simply a user who happens to hold the cashier permissions,
 * and taking one away changes what they can reach.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_screen(): void
    {
        $this->get(route('pos.home'))->assertRedirect(route('login'));
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_a_deactivated_user_cannot_sign_in(): void
    {
        $this->seedRoles();
        $user = User::factory()->create(['is_active' => false, 'password' => 'password']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivating_a_user_ends_their_open_session(): void
    {
        $cashier = $this->cashier();

        $this->actingAs($cashier)->get(route('pos.home'))->assertOk();

        $cashier->update(['is_active' => false]);

        $this->actingAs($cashier)->get(route('pos.home'))->assertRedirect(route('login'));
    }

    public function test_a_cashier_can_run_the_pos_but_not_the_back_office(): void
    {
        $cashier = $this->cashier();

        $this->actingAs($cashier)->get(route('pos.home'))->assertOk();
        $this->actingAs($cashier)->get(route('orders.index'))->assertOk();

        $this->actingAs($cashier)->get(route('menu-items.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('categories.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('tables.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('users.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('settings.edit'))->assertForbidden();
        $this->actingAs($cashier)->get(route('reports.index'))->assertForbidden();
    }

    public function test_an_admin_can_reach_every_screen(): void
    {
        $admin = $this->admin();

        foreach (['pos.home', 'orders.index', 'menu-items.index', 'categories.index',
            'tables.index', 'users.index', 'settings.edit', 'reports.index', 'dashboard'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_a_cashier_cannot_cancel_an_order_without_the_permission(): void
    {
        $cashier = $this->cashier();
        $this->actingAs($cashier)->post(route('pos.takeaway.store'));
        $order = Order::query()->sole();

        $this->actingAs($cashier)->post(route('pos.orders.cancel', $order))->assertForbidden();

        $this->assertTrue($order->fresh()->isEditable());
    }

    public function test_granting_the_cancel_permission_is_all_it_takes(): void
    {
        $cashier = $this->cashier();
        $this->actingAs($cashier)->post(route('pos.takeaway.store'));
        $order = Order::query()->sole();

        $cashier->givePermissionTo(Permissions::CANCEL_ORDERS);

        $this->actingAs($cashier)->post(route('pos.orders.cancel', $order))->assertRedirect(route('pos.home'));

        $this->assertFalse($order->fresh()->isEditable());
    }

    public function test_a_cashier_cannot_apply_a_discount_without_the_permission(): void
    {
        $cashier = $this->cashier();
        $item = MenuItem::factory()->create(['price' => 1000]);

        $this->actingAs($cashier)->post(route('pos.takeaway.store'));
        $order = Order::query()->sole();
        $this->actingAs($cashier)->postJson(route('pos.orders.items.store', $order), ['menu_item_id' => $item->id]);

        $this->actingAs($cashier)->post(route('pos.orders.discount', $order), ['discount_amount' => 500])
            ->assertForbidden();

        $this->assertSame('0.00', $order->fresh()->discount_amount);
    }

    public function test_an_admin_can_apply_a_discount(): void
    {
        $admin = $this->admin();
        $item = MenuItem::factory()->create(['price' => 1000]);

        $this->actingAs($admin)->post(route('pos.takeaway.store'));
        $order = Order::query()->sole();
        $this->actingAs($admin)->postJson(route('pos.orders.items.store', $order), ['menu_item_id' => $item->id]);

        $this->actingAs($admin)->post(route('pos.orders.discount', $order), ['discount_amount' => 250])
            ->assertRedirect();

        $this->assertSame('750.00', $order->fresh()->total);
    }

    public function test_the_dashboard_hides_takings_from_staff_without_the_reports_permission(): void
    {
        $this->actingAs($this->cashier())->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee("Today's sales");

        $this->actingAs($this->admin())->get(route('dashboard'))
            ->assertOk()
            ->assertSee("Today's sales");
    }
}
