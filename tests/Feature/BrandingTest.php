<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The restaurant's own identity: its logo on the screens staff and customers
 * see, its colours in the interface, and its credit line on the paper.
 */
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_logo_appears_on_the_screens_that_carry_the_identity(): void
    {
        $admin = $this->admin();

        // Signing in, before there is any session to speak of.
        $this->get(route('login'))->assertOk()->assertSee('images/logo.png');

        // The back office sidebar and the POS header.
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('images/logo.png');
        $this->actingAs($admin)->get(route('pos.home'))->assertOk()->assertSee('images/logo.png');
    }

    public function test_the_receipt_prints_the_logo_and_the_software_credit(): void
    {
        $cashier = $this->cashier();
        $item = MenuItem::factory()->create(['price' => 400]);

        $this->actingAs($cashier)->post(route('pos.takeaway.store'));
        $order = Order::query()->sole();
        $this->actingAs($cashier)->postJson(route('pos.orders.items.store', $order), ['menu_item_id' => $item->id]);
        $this->actingAs($cashier)->post(route('pos.orders.checkout.store', $order), [
            'method' => PaymentMethod::Cash->value,
            'tendered' => 400,
        ]);

        $this->actingAs($cashier)->get(route('orders.receipt', $order))
            ->assertOk()
            ->assertSee('images/logo.png')
            ->assertSee('grayscale(1)', false)      // flattened for a thermal head
            ->assertSee('Software By - Nawawickrama (0779389533)');
    }

    /** The credit is a setting, so the number can change without a deploy. */
    public function test_the_software_credit_can_be_edited_and_removed(): void
    {
        $admin = $this->admin();
        $item = MenuItem::factory()->create(['price' => 400]);

        $this->actingAs($admin)->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Software credit');

        $this->actingAs($admin)->put(route('settings.update'), [
            'restaurant_name' => 'K & D Foods',
            'currency_symbol' => 'Rs.',
            'tax_percentage' => '0',
            'software_credit' => '',
        ])->assertRedirect();

        $this->assertSame('', app(SettingsService::class)->get('software_credit'));

        $this->actingAs($admin)->post(route('pos.takeaway.store'));
        $order = Order::query()->sole();
        $this->actingAs($admin)->postJson(route('pos.orders.items.store', $order), ['menu_item_id' => $item->id]);

        $this->actingAs($admin)->get(route('orders.receipt', $order))
            ->assertOk()
            ->assertDontSee('Nawawickrama');
    }

    /**
     * The brand is red, so a solid red button means "main action". A
     * destructive trigger has to stay visibly different from it, or a cashier
     * reaching for Checkout can hit Cancel order instead.
     */
    public function test_destructive_triggers_are_not_styled_like_the_primary_action(): void
    {
        $admin = $this->admin();
        Category::factory()->create();

        $html = $this->actingAs($admin)->get(route('categories.index'))->getContent();

        // One category on screen: one outlined delete trigger, and exactly one
        // solid red button — the confirm inside its dialog. Any more solid red
        // would mean a destructive trigger had crept back to looking primary.
        $this->assertSame(
            1,
            substr_count($html, 'border border-rose-300 bg-white text-rose-700'),
            'The delete trigger should be outlined.',
        );

        $this->assertSame(
            1,
            substr_count($html, 'bg-rose-600 text-white'),
            'Solid red belongs only to the confirm inside the dialog.',
        );
    }

    /** Inside the confirmation dialog, the final press is solid and unmistakable. */
    public function test_the_confirmation_dialog_uses_a_solid_destructive_button(): void
    {
        $admin = $this->admin();
        $category = Category::factory()->create();

        $this->actingAs($admin)->get(route('categories.index'))
            ->assertOk()
            ->assertSee('Delete '.$category->name)
            ->assertSee('bg-rose-600 text-white', false);
    }
}
