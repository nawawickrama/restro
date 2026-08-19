<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The restaurant's public page — the only screen here meant for customers.
 */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    /** It has to work for someone who has never signed in. */
    public function test_the_landing_page_is_public(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('A signature')
            ->assertSee('Biyagama Road');
    }

    public function test_it_carries_the_contact_details_a_customer_needs(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('077 291 5469')
            ->assertSee('info@kdfoods.lk')
            ->assertSee('Kelaniya')
            ->assertSee('11870')
            // Tappable on a phone, which is how most people will open this.
            ->assertSee('tel:+94772915469')
            ->assertSee('mailto:info@kdfoods.lk')
            ->assertSee('https://www.facebook.com/kdfoodscatering');
    }

    public function test_it_names_all_three_sides_of_the_business(): void
    {
        $response = $this->get('/')->assertOk();

        foreach (['Restaurant', 'Catering', 'Events'] as $pillar) {
            $response->assertSee($pillar);
        }
    }

    /**
     * The menu is read from the till, so the website cannot quote a price the
     * kitchen stopped charging months ago.
     */
    public function test_the_menu_section_is_read_from_the_pos(): void
    {
        $category = Category::factory()->create(['name' => 'Signature Rice']);
        MenuItem::factory()->create([
            'category_id' => $category->id,
            'name' => 'Kelaniya Fried Rice',
            'price' => 1450,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Signature Rice')
            ->assertSee('Kelaniya Fried Rice')
            ->assertSee('Rs. 1,450.00');
    }

    public function test_a_disabled_item_never_reaches_the_public_menu(): void
    {
        $category = Category::factory()->create(['name' => 'Specials']);
        MenuItem::factory()->create(['category_id' => $category->id, 'name' => 'On The Board']);
        MenuItem::factory()->inactive()->create(['category_id' => $category->id, 'name' => 'Off The Board']);

        $this->get('/')
            ->assertOk()
            ->assertSee('On The Board')
            ->assertDontSee('Off The Board');
    }

    /** An empty menu hides the section rather than printing a bare heading. */
    public function test_the_menu_section_disappears_when_there_is_nothing_to_show(): void
    {
        $this->get('/')->assertOk()->assertDontSee('A taste of the board');
    }

    public function test_it_uses_the_restaurants_own_photographs(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('images/storefront.jpg')
            ->assertSee('images/interior.jpg')
            ->assertSee('images/logo.png');
    }

    /** Staff reach the till from the footer; the POS itself is unchanged. */
    public function test_the_footer_links_staff_to_the_till(): void
    {
        $this->get('/')->assertOk()->assertSee('Staff login');

        $this->get(route('pos.home'))->assertRedirect(route('login'));
        $this->actingAs($this->cashier())->get(route('pos.home'))->assertOk();
    }

    /** Search results and map listings are built from this. */
    public function test_it_publishes_structured_data_for_search_engines(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('application/ld+json', $html);

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $schema = json_decode($matches[1] ?? '', true);

        $this->assertSame('Restaurant', $schema['@type'] ?? null);
        $this->assertSame('K&D Foods & Catering', $schema['name'] ?? null);
        $this->assertSame('Kelaniya', $schema['address']['addressLocality'] ?? null);
    }
}
