<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@restro.test'],
            ['name' => 'Restaurant Admin', 'password' => Hash::make('password'), 'is_active' => true],
        );
        $admin->syncRoles(['Admin']);

        $cashier = User::query()->updateOrCreate(
            ['email' => 'cashier@restro.test'],
            ['name' => 'Sample Cashier', 'password' => Hash::make('password'), 'is_active' => true],
        );
        $cashier->syncRoles(['Cashier']);
    }
}
