<?php

namespace Tests;

use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\OrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Roles and permissions are part of the schema as far as tests care. */
    protected function seedRoles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function admin(): User
    {
        $this->seedRoles();

        return tap(User::factory()->create(['is_active' => true]), fn (User $user) => $user->assignRole('Admin'));
    }

    protected function cashier(): User
    {
        $this->seedRoles();

        return tap(User::factory()->create(['is_active' => true]), fn (User $user) => $user->assignRole('Cashier'));
    }

    /*
     * An open order with nothing on it yet, signed in as the user who opened it.
     *
     * The POS itself never makes one: an order opens with its first item. But
     * an order is still left empty once its lines are taken off again, and a
     * test about what happens next reads more clearly starting from a blank
     * order than carrying an item it did not ask for.
     */

    protected function openTakeaway(User $user): Order
    {
        $this->actingAs($user);

        return app(OrderService::class)->startTakeaway($user);
    }

    protected function openPhoneOrder(User $user): Order
    {
        $this->actingAs($user);

        return app(OrderService::class)->startPhoneOrder($user);
    }

    protected function openTable(RestaurantTable $table, User $user): Order
    {
        $this->actingAs($user);

        return app(OrderService::class)->startDineIn($table, $user);
    }
}
