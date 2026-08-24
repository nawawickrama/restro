<?php

namespace Tests\Feature;

use App\Models\CateringItem;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Picking a dish on a catering sheet.
 *
 * The browser paints a native <datalist> popup itself and does not follow the
 * page's theme, which left the suggestions unreadable. The list is drawn by
 * the application instead, so it is styled like everything else.
 */
class CateringSheetPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dish_picker_is_not_a_native_datalist(): void
    {
        $admin = tap($this->admin(), fn ($u) => $u->givePermissionTo(Permissions::MANAGE_CATERING));
        CateringItem::factory()->create(['name' => 'Malu Ambul Thiyal']);

        $html = $this->actingAs($admin)->get(route('catering.create'))->assertOk()->getContent();

        // No native popup for the browser to paint in its own colours.
        $this->assertStringNotContainsString('<datalist', $html);
        $this->assertStringNotContainsString('list="catering-catalogue"', $html);

        // Our own list, with the same text colours as the rest of the app.
        $this->assertStringContainsString('suggestions(line)', $html);
        $this->assertStringContainsString('text-slate-800', $html);
        $this->assertStringContainsString('dark:text-slate-100', $html);
    }

    public function test_the_catalogue_reaches_the_picker(): void
    {
        $admin = tap($this->admin(), fn ($u) => $u->givePermissionTo(Permissions::MANAGE_CATERING));

        CateringItem::factory()->create(['name' => 'Malu Ambul Thiyal', 'price' => 2800]);
        CateringItem::factory()->inactive()->create(['name' => 'No Longer Catered']);

        $catalogue = $this->actingAs($admin)->get(route('catering.create'))->assertOk()->viewData('catalogue');

        $this->assertCount(1, $catalogue);
        $this->assertSame('Malu Ambul Thiyal', $catalogue->first()->name);
    }
}
