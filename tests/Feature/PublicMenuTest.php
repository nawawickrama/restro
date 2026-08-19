<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The full menu behind the QR code on the table.
 *
 * Read by a customer on their own phone, so it has to work with no session,
 * show everything that is on, and offer nothing to press.
 */
class PublicMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_menu_is_public(): void
    {
        $category = Category::factory()->create(['name' => 'Rice & Curry']);
        MenuItem::factory()->create([
            'category_id' => $category->id,
            'name' => 'Chicken Rice',
            'price' => 950,
            'description' => 'With three vegetables and papadam.',
        ]);

        $this->get(route('menu'))
            ->assertOk()
            ->assertSee('Rice &amp; Curry', false)
            ->assertSee('Chicken Rice')
            ->assertSee('Rs. 950.00')
            ->assertSee('With three vegetables and papadam.');
    }

    /** Every category is shown, not the taster the landing page carries. */
    public function test_it_shows_the_whole_board(): void
    {
        foreach (['Burgers', 'Rice', 'Drinks', 'Snacks', 'Desserts', 'Juices'] as $name) {
            $category = Category::factory()->create(['name' => $name]);
            MenuItem::factory()->count(6)->create(['category_id' => $category->id]);
        }

        $response = $this->get(route('menu'))->assertOk();

        foreach (['Burgers', 'Rice', 'Drinks', 'Snacks', 'Desserts', 'Juices'] as $name) {
            $response->assertSee($name);
        }

        // 6 categories × 6 items, all present rather than capped.
        $this->assertSame(36, substr_count($response->getContent(), 'border-b border-white/5'));
    }

    /** A photo added in the back office is what the customer sees at the table. */
    public function test_an_item_photo_from_the_pos_is_shown(): void
    {
        Storage::fake('public');

        $admin = $this->admin();
        $category = Category::factory()->create();

        $this->actingAs($admin)->post(route('menu-items.store'), [
            'category_id' => $category->id,
            'name' => 'Devilled Chicken',
            'price' => 1250,
            'is_active' => '1',
            'image' => UploadedFile::fake()->image('devilled.jpg'),
        ])->assertRedirect();

        $path = MenuItem::query()->sole()->image_path;
        $this->assertNotNull($path);

        $this->get(route('menu'))
            ->assertOk()
            ->assertSee('Devilled Chicken')
            ->assertSee($path);
    }

    /** Photos are the exception, so a plain item must not leave a hole. */
    public function test_an_item_without_a_photo_still_renders(): void
    {
        $category = Category::factory()->create();
        MenuItem::factory()->create(['category_id' => $category->id, 'name' => 'Plain Tea']);

        $this->get(route('menu'))->assertOk()->assertSee('Plain Tea');
    }

    public function test_disabled_items_and_empty_categories_stay_off_the_menu(): void
    {
        $shown = Category::factory()->create(['name' => 'On The Board']);
        MenuItem::factory()->create(['category_id' => $shown->id, 'name' => 'Served Today']);
        MenuItem::factory()->inactive()->create(['category_id' => $shown->id, 'name' => 'Sold Out Item']);

        Category::factory()->create(['name' => 'Nothing In Here']);

        $this->get(route('menu'))
            ->assertOk()
            ->assertSee('Served Today')
            ->assertDontSee('Sold Out Item')
            ->assertDontSee('Nothing In Here');
    }

    /** It is a menu, not a till: nothing on it can place an order. */
    public function test_the_menu_offers_no_way_to_order(): void
    {
        $category = Category::factory()->create();
        MenuItem::factory()->create(['category_id' => $category->id]);

        $html = $this->get(route('menu'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString('Add to order', $html);
        $this->assertStringNotContainsString(route('pos.home'), $html);
    }

    public function test_an_empty_menu_says_so_rather_than_showing_a_bare_page(): void
    {
        $this->get(route('menu'))
            ->assertOk()
            ->assertSee('being updated')
            ->assertSee('077 291 5469');
    }

    public function test_the_landing_page_sends_people_to_the_full_menu(): void
    {
        $category = Category::factory()->create();
        MenuItem::factory()->create(['category_id' => $category->id]);

        $this->get('/')
            ->assertOk()
            ->assertSee(route('menu'))
            ->assertSee('View the full menu');
    }

    /** The footer credits the builder; staff reach the till by URL. */
    public function test_the_public_pages_carry_the_engineering_credit_and_no_staff_link(): void
    {
        foreach (['/', route('menu')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('Coredile')
                ->assertSee('https://coredile.com')
                ->assertSee('of Sri Lanka')
                ->assertDontSee('Staff login');
        }
    }
}
