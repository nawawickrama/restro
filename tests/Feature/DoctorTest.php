<?php

namespace Tests\Feature;

use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The deployment check exists because every way a deploy silently fails to
 * take effect looks identical from the outside: nothing changed.
 */
class DoctorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_a_healthy_install(): void
    {
        $this->seedRoles();

        $this->artisan('restro:doctor')
            ->expectsOutputToContain('Deployment check')
            ->expectsOutputToContain('none pending')
            ->assertSuccessful();
    }

    /**
     * A permission the code checks but the database lacks hides whole sections
     * of the menu without erroring anywhere — the exact fault that made the
     * customers screen invisible after an earlier deploy.
     */
    public function test_it_names_a_missing_permission_and_how_to_fix_it(): void
    {
        $this->seedRoles();

        Permission::query()->where('name', Permissions::MANAGE_CATERING)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->artisan('restro:doctor')
            ->expectsOutputToContain('manage_catering')
            ->expectsOutputToContain('invisible')
            ->expectsOutputToContain('php artisan migrate --force')
            ->assertSuccessful();
    }

    /** Compiled Blade survives a git pull, which is why a page can look old. */
    public function test_it_flags_cached_views(): void
    {
        $this->seedRoles();

        file_put_contents(storage_path('framework/views/deployment-check-probe.php'), '<?php // probe');

        try {
            $this->artisan('restro:doctor')
                ->expectsOutputToContain('not replaced by a git pull')
                ->expectsOutputToContain('php artisan view:clear')
                ->assertSuccessful();
        } finally {
            @unlink(storage_path('framework/views/deployment-check-probe.php'));
        }
    }

    /** Each remedy is listed once, however many checks asked for it. */
    public function test_it_does_not_repeat_the_same_remedy(): void
    {
        $this->seedRoles();

        Permission::query()->where('name', Permissions::MANAGE_CATERING)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Artisan::call rather than $this->artisan, because only the former
        // records the output for inspection afterwards.
        Artisan::call('restro:doctor');
        $output = Artisan::output();

        $this->assertSame(
            1,
            substr_count($output, 'php artisan migrate --force'),
            'The same command should not be listed twice.',
        );
    }
}
