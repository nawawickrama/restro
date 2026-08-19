<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Brings the database's permissions up to date with the application's.
 *
 * A new capability — `manage_customers` was the first — is added to
 * {@see Permissions} and seeded locally, which is easy to forget on a server
 * where deployment is "pull, then migrate". The screen then exists, the route
 * works, and the menu item is simply invisible because nobody holds the
 * permission that reveals it.
 *
 * Running it as a migration means the usual `migrate --force` cannot miss it.
 *
 * Deliberately additive: it creates what is missing and grants it to Admin,
 * and never revokes anything. A restaurant that has granted a permission to
 * its cashiers by hand keeps that grant.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Permissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Admin is defined as holding everything, so it gains whatever is new.
        Role::query()->where('name', 'Admin')->first()?->givePermissionTo(Permissions::all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Nothing to undo: removing a permission the application still checks
        // would lock people out of screens that are still there.
    }
};
