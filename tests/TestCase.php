<?php

namespace Tests;

use App\Models\User;
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
}
