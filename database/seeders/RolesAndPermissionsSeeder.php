<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent: safe to re-run after adding a permission to
 * {@see Permissions}, which is how new capabilities land.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Admin holds every permission explicitly rather than through a
        // hard-coded "is admin" shortcut, so admin access is auditable.
        Role::findOrCreate('Admin', 'web')->syncPermissions(Permissions::all());

        Role::findOrCreate('Cashier', 'web')->syncPermissions(Permissions::cashier());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
