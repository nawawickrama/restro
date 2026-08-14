<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\MenuItemImageService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_category_can_be_created_edited_and_disabled(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('categories.store'), ['name' => 'Burgers', 'is_active' => '1'])
            ->assertRedirect(route('categories.index'));

        $category = Category::query()->sole();
        $this->assertTrue($category->is_active);

        $this->actingAs($admin)->put(route('categories.update', $category), ['name' => 'Grill', 'is_active' => '0'])
            ->assertRedirect(route('categories.index'));

        $this->assertSame('Grill', $category->fresh()->name);
        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_a_category_holding_items_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $item = MenuItem::factory()->create();

        $this->actingAs($admin)->delete(route('categories.destroy', $item->category))
            ->assertSessionHas('error');

        $this->assertModelExists($item->category);
    }

    public function test_a_menu_item_with_order_history_is_disabled_rather_than_deleted(): void
    {
        $cashier = $this->cashier();
        $admin = $this->admin();
        $item = MenuItem::factory()->create(['price' => 500]);

        $this->actingAs($cashier)->post(route('pos.takeaway.store'));
        $order = Order::query()->sole();
        $this->actingAs($cashier)->postJson(route('pos.orders.items.store', $order), ['menu_item_id' => $item->id]);

        $this->actingAs($admin)->delete(route('menu-items.destroy', $item))->assertRedirect();

        $this->assertModelExists($item);
        $this->assertFalse($item->fresh()->is_active);
    }

    public function test_a_menu_item_photo_can_be_added_replaced_and_removed(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $category = Category::factory()->create();

        $this->actingAs($admin)->post(route('menu-items.store'), [
            'category_id' => $category->id,
            'name' => 'Chicken Burger',
            'price' => 950,
            'is_active' => '1',
            'image' => UploadedFile::fake()->image('burger.jpg'),
        ])->assertRedirect(route('menu-items.index'));

        $item = MenuItem::query()->sole();
        $original = $item->image_path;
        $this->assertNotNull($original);
        Storage::disk('public')->assertExists($original);
        $this->assertStringContainsString($original, $item->imageUrl());

        // Replacing the photo cleans up the file it replaced.
        $this->actingAs($admin)->put(route('menu-items.update', $item), [
            'category_id' => $category->id,
            'name' => 'Chicken Burger',
            'price' => 950,
            'is_active' => '1',
            'image' => UploadedFile::fake()->image('better-burger.jpg'),
        ])->assertRedirect();

        $replaced = $item->fresh()->image_path;
        $this->assertNotSame($original, $replaced);
        Storage::disk('public')->assertMissing($original);
        Storage::disk('public')->assertExists($replaced);

        // Removing it leaves the item intact with no photo.
        $this->actingAs($admin)->put(route('menu-items.update', $item), [
            'category_id' => $category->id,
            'name' => 'Chicken Burger',
            'price' => 950,
            'is_active' => '1',
            'remove_image' => '1',
        ])->assertRedirect();

        $this->assertNull($item->fresh()->image_path);
        Storage::disk('public')->assertMissing($replaced);
    }

    /**
     * A validation cap looser than php.ini would hand the user a raw 413 page,
     * because PHP discards the body before Laravel runs. The rule is derived
     * from the ini settings so the two can never drift apart.
     */
    public function test_the_photo_size_limit_never_exceeds_what_php_accepts(): void
    {
        $limitBytes = MenuItemImageService::maxUploadKilobytes() * 1024;

        foreach (['upload_max_filesize', 'post_max_size'] as $directive) {
            $ini = ini_get($directive);
            $bytes = (int) $ini * match (strtolower(substr((string) $ini, -1))) {
                'g' => 1024 ** 3,
                'm' => 1024 ** 2,
                'k' => 1024,
                default => 1,
            };

            $this->assertLessThan($bytes, $limitBytes, "The photo cap must fit inside {$directive}.");
        }
    }

    /**
     * Photo URLs must not carry a host. The POS is opened from several
     * addresses — 127.0.0.1 on the terminal, a LAN IP on a tablet — and a URL
     * built from APP_URL points every one of them at the wrong machine.
     */
    public function test_photo_urls_are_host_relative(): void
    {
        $item = MenuItem::factory()->create(['image_path' => 'menu-items/burger.jpg']);

        $this->assertSame('/storage/menu-items/burger.jpg', $item->imageUrl());
    }

    public function test_an_oversized_photo_is_rejected_with_a_message(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $category = Category::factory()->create();

        $overLimit = MenuItemImageService::maxUploadKilobytes() + 64;

        $this->actingAs($admin)->post(route('menu-items.store'), [
            'category_id' => $category->id,
            'name' => 'Huge Photo',
            'price' => 300,
            'is_active' => '1',
            'image' => UploadedFile::fake()->create('huge.jpg', $overLimit, 'image/jpeg'),
        ])->assertSessionHasErrors('image');

        $this->assertSame(0, MenuItem::query()->count());
    }

    public function test_a_photo_is_optional_and_rejects_non_images(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $category = Category::factory()->create();

        $this->actingAs($admin)->post(route('menu-items.store'), [
            'category_id' => $category->id,
            'name' => 'Plain Rice',
            'price' => 300,
            'is_active' => '1',
        ])->assertRedirect(route('menu-items.index'));

        $this->assertNull(MenuItem::query()->sole()->imageUrl());

        $this->actingAs($admin)->post(route('menu-items.store'), [
            'category_id' => $category->id,
            'name' => 'Bad Upload',
            'price' => 300,
            'is_active' => '1',
            'image' => UploadedFile::fake()->create('menu.pdf', 40, 'application/pdf'),
        ])->assertSessionHasErrors('image');
    }

    public function test_a_table_with_an_open_order_cannot_be_deleted(): void
    {
        $cashier = $this->cashier();
        $admin = $this->admin();
        $table = RestaurantTable::factory()->create();

        $this->actingAs($cashier)->post(route('pos.tables.select', $table));

        $this->actingAs($admin)->delete(route('tables.destroy', $table))->assertSessionHas('error');

        $this->assertModelExists($table);
    }

    public function test_a_user_is_created_with_a_role_and_can_sign_in(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'New Cashier',
            'email' => 'new@restro.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'role' => 'Cashier',
            'is_active' => '1',
        ])->assertRedirect(route('users.index'));

        $user = User::query()->where('email', 'new@restro.test')->sole();
        $this->assertTrue($user->hasRole('Cashier'));

        // Drop the admin's session before checking the new account can sign in.
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->post(route('login'), ['email' => 'new@restro.test', 'password' => 'secret-password'])
            ->assertRedirect(route('pos.home'));
    }

    public function test_an_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'Admin',
            'is_active' => '0',
        ])->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_settings_change_the_currency_and_tax_used_by_the_pos(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('settings.update'), [
            'restaurant_name' => 'Sea Breeze',
            'currency_symbol' => '$',
            'tax_percentage' => '5',
            'receipt_footer' => 'See you soon',
        ])->assertRedirect(route('settings.edit'));

        $settings = app(SettingsService::class);
        $this->assertSame('Sea Breeze', $settings->restaurantName());
        $this->assertSame(5.0, $settings->taxPercentage());
        $this->assertSame('$ 1,000.00', $settings->formatMoney(1000));
    }

    public function test_reports_count_completed_sales_only(): void
    {
        $admin = $this->admin();
        $item = MenuItem::factory()->create(['price' => 1000]);

        // One completed order, one left open.
        $this->actingAs($admin)->post(route('pos.takeaway.store'));
        $paid = Order::query()->latest('id')->first();
        $this->actingAs($admin)->postJson(route('pos.orders.items.store', $paid), ['menu_item_id' => $item->id]);
        $this->actingAs($admin)->post(route('pos.orders.checkout.store', $paid), [
            'method' => 'cash',
            'tendered' => 1000,
        ]);

        $this->actingAs($admin)->post(route('pos.takeaway.store'));
        $open = Order::query()->latest('id')->first();
        $this->actingAs($admin)->postJson(route('pos.orders.items.store', $open), ['menu_item_id' => $item->id]);

        $this->actingAs($admin)->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Rs. 1,000.00')
            ->assertSee($item->name);
    }
}
