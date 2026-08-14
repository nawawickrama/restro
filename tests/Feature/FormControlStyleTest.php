<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every text box, dropdown and date field wears one shared class. Styling them
 * ad hoc is how a form ends up looking older than the one on the next screen,
 * so these tests keep the definition in a single place.
 */
class FormControlStyleTest extends TestCase
{
    use RefreshDatabase;

    /** Nothing should hand-roll the control styling that `.field-control` owns. */
    public function test_no_view_restyles_a_control_by_hand(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $markup = file_get_contents($file);

            // The give-away of a copied control: border + background + radius
            // spelled out on the element itself.
            if (preg_match('/rounded-\w+ border border-slate-300 bg-white/', $markup)) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame([], $offenders, 'These views style a form control by hand instead of using x-input, x-select or x-textarea.');
    }

    public function test_every_admin_form_renders_its_controls_with_the_shared_class(): void
    {
        $admin = $this->admin();
        MenuItem::factory()->create();
        Category::factory()->create();
        RestaurantTable::factory()->create();

        $screens = [
            route('orders.index'),
            route('menu-items.create'),
            route('categories.create'),
            route('tables.create'),
            route('users.create'),
            route('settings.edit'),
            route('reports.index'),
        ];

        foreach ($screens as $screen) {
            $html = $this->actingAs($admin)->get($screen)->assertOk()->getContent();

            preg_match_all('/<(input|select|textarea)\b[^>]*>/', $html, $matches);

            foreach ($matches[0] as $control) {
                // Hidden fields, checkboxes and file pickers are not text boxes.
                if (preg_match('/type="(hidden|checkbox|radio|file|submit)"/', $control)) {
                    continue;
                }

                $this->assertStringContainsString(
                    'field-control',
                    $control,
                    "A control on {$screen} is missing the shared styling: {$control}",
                );
            }
        }
    }

    /**
     * Native widgets — the date picker, the select popup, scrollbars — follow
     * `color-scheme`. Without it they render in light chrome on a dark page.
     */
    public function test_the_stylesheet_tells_the_browser_which_theme_its_widgets_wear(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('color-scheme: light', $css);
        $this->assertStringContainsString('color-scheme: dark', $css);
    }

    /** Tapping a date field anywhere should open the picker, not just the icon. */
    public function test_date_fields_open_the_picker_when_tapped(): void
    {
        $html = $this->actingAs($this->admin())->get(route('orders.index'))->getContent();

        preg_match_all('/<input\b[^>]*type="date"[^>]*>/', $html, $matches);

        $this->assertNotEmpty($matches[0], 'The orders screen should have date fields.');

        foreach ($matches[0] as $control) {
            $this->assertStringContainsString('showPicker', $control);
        }
    }

    /** @return list<string> */
    private function bladeFiles(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views')),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
