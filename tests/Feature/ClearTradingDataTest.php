<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Handing a fresh install to a restaurant: the sample menu and any orders
 * taken while testing go, and the things somebody configured stay.
 */
class ClearTradingDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_clears_the_menu_tables_orders_and_customers(): void
    {
        $cashier = $this->cashier();
        $table = RestaurantTable::factory()->create();
        $item = MenuItem::factory()->create(['price' => 500]);

        // A completed sale, so orders, items, payments and a customer all exist.
        $this->actingAs($cashier)->post(route('pos.tables.select', $table));
        $order = Order::query()->sole();
        $this->actingAs($cashier)->post(route('pos.orders.customer', $order), ['customer_phone' => '0771234567']);
        $this->actingAs($cashier)->postJson(route('pos.orders.items.store', $order), ['menu_item_id' => $item->id]);
        $this->actingAs($cashier)->post(route('pos.orders.checkout.store', $order), [
            'method' => 'cash', 'tendered' => 500,
        ]);

        $this->artisan('restro:clear-data --force')->assertSuccessful();

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Customer::query()->count());
        $this->assertSame(0, MenuItem::query()->count());
        $this->assertSame(0, Category::query()->count());
        $this->assertSame(0, RestaurantTable::query()->count());
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    /** The things somebody configured are not sample data. */
    public function test_it_keeps_staff_logins_roles_and_settings(): void
    {
        $admin = $this->admin();
        $cashier = $this->cashier();
        Setting::query()->updateOrCreate(['key' => 'restaurant_name'], ['value' => 'K&D Foods & Catering']);
        MenuItem::factory()->create();

        $this->artisan('restro:clear-data --force')->assertSuccessful();

        $this->assertSame(2, User::query()->count());
        $this->assertTrue($admin->fresh()->hasRole('Admin'));
        $this->assertTrue($cashier->fresh()->hasRole('Cashier'));
        $this->assertTrue($cashier->fresh()->can('view_pos'));
        $this->assertSame('K&D Foods & Catering', Setting::query()->find('restaurant_name')->value);
    }

    /** Photographs are files, not rows, so they need deleting separately. */
    public function test_it_removes_the_menu_photographs(): void
    {
        Storage::fake('public');

        $category = Category::factory()->create();
        $this->actingAs($this->admin())->post(route('menu-items.store'), [
            'category_id' => $category->id,
            'name' => 'Devilled Chicken',
            'price' => 1250,
            'is_active' => '1',
            'image' => UploadedFile::fake()->image('devilled.jpg'),
        ]);

        $path = MenuItem::query()->sole()->image_path;
        Storage::disk('public')->assertExists($path);

        $this->artisan('restro:clear-data --force')->assertSuccessful();

        Storage::disk('public')->assertMissing($path);
        $this->assertSame([], Storage::disk('public')->files('menu-items'));
    }

    /** A file left behind by an earlier upload is still work worth doing. */
    public function test_it_sweeps_orphaned_photographs_even_with_no_rows_left(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('menu-items/orphan.jpg', 'not referenced by anything');

        $this->artisan('restro:clear-data --force')->assertSuccessful();

        Storage::disk('public')->assertMissing('menu-items/orphan.jpg');
    }

    public function test_it_says_so_when_there_is_nothing_to_clear(): void
    {
        Storage::fake('public');

        $this->artisan('restro:clear-data --force')
            ->expectsOutputToContain('Nothing to clear')
            ->assertSuccessful();
    }

    /** Nothing is deleted unless the operator says yes. */
    public function test_it_deletes_nothing_when_the_confirmation_is_declined(): void
    {
        MenuItem::factory()->create();

        $this->artisan('restro:clear-data')
            ->expectsConfirmation('Delete this data?', 'no')
            ->assertSuccessful();

        $this->assertSame(1, MenuItem::query()->count());
    }
}
