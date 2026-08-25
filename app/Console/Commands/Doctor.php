<?php

namespace App\Console\Commands;

use App\Support\Permissions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Answers "I deployed, and nothing changed".
 *
 * Every one of the usual causes is silent: a migration that never ran leaves
 * a menu item invisible rather than throwing, a stale compiled view serves
 * yesterday's HTML, and PHP's own opcode cache can keep running the previous
 * copy of a file that git has already replaced. This checks each in turn and
 * says what to run.
 */
class Doctor extends Command
{
    protected $signature = 'restro:doctor';

    protected $description = 'Check that a deployment actually took effect';

    private array $fixes = [];

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <options=bold>Deployment check</>');
        $this->newLine();

        $this->checkDatabase();
        $this->checkMigrations();
        $this->checkPermissions();
        $this->checkCaches();
        $this->checkAssets();
        $this->checkStorageLink();
        $this->checkEnvironment();

        $this->newLine();

        $this->fixes = array_values(array_unique($this->fixes));

        if ($this->fixes === []) {
            $this->info('  Everything is up to date. If a screen still looks old, it is the browser: reload with a hard refresh.');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->line('  <options=bold>Run these, in order:</>');
        $this->newLine();

        foreach (array_unique($this->fixes) as $fix) {
            $this->line("    {$fix}");
        }

        $this->newLine();

        return self::SUCCESS;
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            $this->ok('Database', DB::connection()->getDatabaseName());
        } catch (\Throwable $e) {
            $this->bad('Database', 'cannot connect — '.str($e->getMessage())->limit(60));
            $this->fixes[] = 'Check the DB_ settings in .env, then: php artisan config:clear';
        }
    }

    /** A migration that never ran is the quietest failure of the lot. */
    private function checkMigrations(): void
    {
        try {
            $ran = DB::table('migrations')->pluck('migration')->all();
        } catch (\Throwable) {
            $this->bad('Migrations', 'no migrations table');
            $this->fixes[] = 'php artisan migrate --force';

            return;
        }

        $files = collect(File::files(database_path('migrations')))
            ->map(fn ($file) => $file->getFilenameWithoutExtension())
            ->values();

        $pending = $files->diff($ran)->values();

        if ($pending->isEmpty()) {
            $this->ok('Migrations', $files->count().' applied, none pending');

            return;
        }

        $this->bad('Migrations', $pending->count().' have not run');

        foreach ($pending as $migration) {
            $this->line("      <fg=yellow>pending</> {$migration}");
        }

        $this->fixes[] = 'php artisan migrate --force';
    }

    /**
     * A permission the code checks but the database lacks hides a whole
     * section of the menu without erroring anywhere.
     */
    private function checkPermissions(): void
    {
        if (! Schema::hasTable('permissions')) {
            $this->bad('Permissions', 'table missing');

            return;
        }

        $stored = Permission::query()->pluck('name');
        $missing = collect(Permissions::all())->diff($stored)->values();

        if ($missing->isEmpty()) {
            $this->ok('Permissions', $stored->count().' present');

            return;
        }

        $this->bad('Permissions', $missing->count().' missing — the screens they guard are invisible');

        foreach ($missing as $permission) {
            $this->line("      <fg=yellow>missing</> {$permission}");
        }

        // The same migrate run creates them; listed once by the dedupe below.
        $this->fixes[] = 'php artisan migrate --force';
        $this->fixes[] = 'php artisan permission:cache-reset';
    }

    /** Compiled Blade and cached config both outlive a git pull. */
    private function checkCaches(): void
    {
        $views = Schema::hasTable('migrations')
            ? count(File::glob(storage_path('framework/views/*.php')))
            : 0;

        if ($views > 0) {
            $this->caution('Compiled views', "{$views} cached — these are not replaced by a git pull");
            $this->fixes[] = 'php artisan view:clear';
        } else {
            $this->ok('Compiled views', 'none cached');
        }

        foreach (['config' => 'config.php', 'routes' => 'routes-v7.php'] as $label => $file) {
            if (File::exists(base_path('bootstrap/cache/'.$file))) {
                $this->caution(ucfirst($label).' cache', 'present — will serve the previous deployment');
                $this->fixes[] = "php artisan {$label}:clear";
            } else {
                $this->ok(ucfirst($label).' cache', 'not cached');
            }
        }
    }

    /** The compiled CSS and JS ship with the repository on this install. */
    private function checkAssets(): void
    {
        $manifest = public_path('build/manifest.json');

        if (! File::exists($manifest)) {
            $this->bad('Built assets', 'public/build/manifest.json is missing');
            $this->fixes[] = 'Locally: npm run build, then commit public/build and pull again';

            return;
        }

        $this->ok('Built assets', 'manifest present, built '.date('d M Y H:i', File::lastModified($manifest)));
    }

    private function checkStorageLink(): void
    {
        if (File::exists(public_path('storage'))) {
            $this->ok('Storage link', 'present — uploaded images will load');

            return;
        }

        $this->bad('Storage link', 'missing — uploaded photos and artwork will not load');
        $this->fixes[] = 'php artisan storage:link';
    }

    private function checkEnvironment(): void
    {
        if (config('app.debug')) {
            $this->caution('APP_DEBUG', 'true — error pages will show your database credentials');
            $this->fixes[] = 'Set APP_DEBUG=false in .env, then: php artisan config:clear';
        } else {
            $this->ok('APP_DEBUG', 'false');
        }

        $this->ok('APP_URL', (string) config('app.url'));
    }

    private function ok(string $label, string $detail): void
    {
        $this->line(sprintf('  <fg=green>✓</> %-18s %s', $label, $detail));
    }

    private function caution(string $label, string $detail): void
    {
        $this->line(sprintf('  <fg=yellow>!</> %-18s %s', $label, $detail));
    }

    private function bad(string $label, string $detail): void
    {
        $this->line(sprintf('  <fg=red>✗</> %-18s %s', $label, $detail));
    }
}
