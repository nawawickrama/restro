<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Services\MenuItemImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Empties the restaurant's trading data, leaving the staff able to sign in.
 *
 * Used once when handing a fresh install to a restaurant, to clear the sample
 * menu and any orders taken while testing. Staff accounts, roles, permissions
 * and the restaurant's settings are kept: those are the things somebody
 * configured, not the things somebody demonstrated.
 */
class ClearTradingData extends Command
{
    protected $signature = 'restro:clear-data {--force : Skip the confirmation}';

    protected $description = 'Delete menu, tables, orders and customers, keeping staff logins and settings';

    public function handle(MenuItemImageService $images): int
    {
        $counts = [
            'orders' => Order::query()->count(),
            'customers' => Customer::query()->count(),
            'menu items' => MenuItem::query()->count(),
            'categories' => Category::query()->count(),
            'tables' => RestaurantTable::query()->count(),
        ];

        // Photo files are counted alongside the rows, so a folder left holding
        // orphans is still work worth doing even when the tables are empty.
        $photos = Storage::disk('public')->files('menu-items');

        if (array_sum($counts) === 0 && $photos === []) {
            $this->info('Nothing to clear — the restaurant has no menu, tables, orders or photos.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('  This will permanently delete:');
        foreach ($counts + ['photo files' => count($photos)] as $label => $count) {
            $this->line(sprintf('    %-12s %d', $label, $count));
        }

        $this->newLine();
        $this->line('  Staff logins, roles and restaurant settings are kept.');
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Delete this data?')) {
            $this->warn('Nothing was deleted.');

            return self::SUCCESS;
        }

        // The photographs belong to the menu items and are not in the database,
        // so they have to be removed before the rows that point at them.
        MenuItem::query()->whereNotNull('image_path')->each(
            fn (MenuItem $item) => $images->remove($item),
        );

        // Every menu item is about to go, so nothing left in the photo folder
        // can still be referenced.
        if ($photos !== []) {
            Storage::disk('public')->delete($photos);
        }

        DB::transaction(function (): void {
            // Order matters: order items and payments cascade from orders, but
            // tables and menu items are protected by foreign keys until the
            // orders referencing them are gone.
            Order::query()->delete();
            Customer::query()->delete();
            MenuItem::query()->delete();
            Category::query()->delete();
            RestaurantTable::query()->delete();
        });

        $this->newLine();
        $this->info('  Cleared. The restaurant can now be set up with its own menu and tables.');
        $this->newLine();

        return self::SUCCESS;
    }
}
