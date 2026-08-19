<?php

namespace Tests\Feature;

use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BackOfficeNavigationTest extends TestCase
{
    use RefreshDatabase;

    /** Every screen an admin is allowed into is reachable from the sidebar. */
    public function test_the_sidebar_offers_an_admin_every_section(): void
    {
        $response = $this->actingAs($this->admin())->get(route('dashboard'))->assertOk();

        foreach ([
            'dashboard', 'pos.home', 'orders.index', 'reports.index', 'menu-items.index',
            'categories.index', 'tables.index', 'customers.index', 'users.index', 'settings.edit',
        ] as $route) {
            $response->assertSee(route($route));
        }

        $response->assertSee('Customers');
    }

    /** A cashier sees only what they hold, and the sidebar reflects that. */
    public function test_the_sidebar_hides_what_a_cashier_cannot_open(): void
    {
        $this->actingAs($this->cashier())->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Customers')
            ->assertDontSee(route('users.index'))
            ->assertDontSee(route('settings.edit'));
    }

    /**
     * The sidebar stays put while the page scrolls. A long order history
     * should never scroll the navigation off the screen.
     */
    public function test_the_sidebar_is_sticky_from_large_screens_up(): void
    {
        $html = $this->actingAs($this->admin())->get(route('dashboard'))->getContent();

        foreach (['lg:sticky', 'lg:top-0', 'lg:h-dvh', 'lg:self-start', 'lg:overflow-y-auto'] as $class) {
            $this->assertStringContainsString($class, $html, "The sidebar needs {$class} to stay put.");
        }

        // Alpine sets an inline display on the nav for the mobile toggle, so the
        // large-screen rule has to be important or the sidebar never shows.
        $this->assertStringContainsString('lg:flex!', $html);
    }

    /**
     * Reproduces the deployment fault behind a missing Customers menu item: a
     * server that pulled the code and ran migrations, but never re-seeded, so
     * the permission the menu checks did not exist.
     */
    public function test_a_permission_missing_from_the_database_is_restored_by_migrating(): void
    {
        $admin = $this->admin();

        Permission::query()->where('name', Permissions::MANAGE_CUSTOMERS)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertFalse($admin->fresh()->can(Permissions::MANAGE_CUSTOMERS));
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertDontSee('Customers');

        // What `php artisan migrate --force` does on the server.
        foreach (Permissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        Role::query()->where('name', 'Admin')->first()?->givePermissionTo(Permissions::all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($admin->fresh()->can(Permissions::MANAGE_CUSTOMERS));
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('Customers');
    }

    /** Every permission the code checks must exist once the app is set up. */
    public function test_every_permission_the_application_uses_exists(): void
    {
        $this->seedRoles();

        foreach (Permissions::all() as $permission) {
            $this->assertTrue(
                Permission::query()->where('name', $permission)->exists(),
                "Permission [{$permission}] is checked in code but missing from the database.",
            );
        }
    }
}
