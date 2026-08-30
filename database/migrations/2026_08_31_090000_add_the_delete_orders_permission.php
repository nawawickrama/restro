<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Adds `delete_orders`, the permission behind erasing a sale outright.
 *
 * Same reasoning as the earlier permission sync: a server is deployed with
 * "pull, then migrate", so a permission that only exists in a seeder is a
 * permission the live database will not have. The button then renders for
 * nobody and the route 403s, with nothing to say why.
 *
 * Additive, and granted only to Admin. A cashier does not get this by running
 * a migration — deleting sales is how a till is robbed, so it has to be handed
 * out deliberately from the users screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::findOrCreate(Permissions::DELETE_ORDERS, 'web');

        Role::query()->where('name', 'Admin')->first()?->givePermissionTo(Permissions::DELETE_ORDERS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Left in place: the application still checks it, and revoking it would
        // only hide a screen that is still there.
    }
};
