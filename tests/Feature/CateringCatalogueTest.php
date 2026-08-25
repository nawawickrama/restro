<?php

namespace Tests\Feature;

use App\Models\CateringCatalogue;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The catering brochure: categories of artwork, managed in the back office and
 * published at a public address that can be shared or put behind a QR code.
 */
class CateringCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private function manager()
    {
        return tap($this->admin(), fn ($u) => $u->givePermissionTo(Permissions::MANAGE_CATERING));
    }

    private function withArtwork(string $name = 'Wedding Packages', int $pages = 2): CateringCatalogue
    {
        Storage::fake('public');

        $this->actingAs($this->manager())->post(route('catering-catalogues.store'), [
            'name' => $name,
            'description' => 'Buffet and plated menus.',
            'is_active' => '1',
            'images' => collect(range(1, $pages))
                ->map(fn ($n) => UploadedFile::fake()->image("Package {$n}.jpg"))
                ->all(),
        ])->assertRedirect();

        return CateringCatalogue::query()->where('name', $name)->latest('id')->firstOrFail();
    }

    public function test_a_category_is_created_with_its_artwork(): void
    {
        $catalogue = $this->withArtwork();

        $this->assertSame('wedding-packages', $catalogue->slug);
        $this->assertCount(2, $catalogue->images);

        foreach ($catalogue->images as $image) {
            Storage::disk('public')->assertExists($image->path);
        }

        // Pages keep the order they were uploaded in.
        $this->assertSame([1, 2], $catalogue->images->pluck('sort_order')->all());
    }

    /** The whole point: a link anyone can open without signing in. */
    public function test_the_catalogue_is_public(): void
    {
        $catalogue = $this->withArtwork();

        $this->get(route('catalogue.index'))
            ->assertOk()
            ->assertSee('Wedding Packages')
            ->assertSee('Catering catalogue');

        $this->get(route('catalogue.show', $catalogue))
            ->assertOk()
            ->assertSee('Wedding Packages')
            ->assertSee('Buffet and plated menus.')
            ->assertSee($catalogue->images->first()->path);
    }

    /** The address is the name, so it can be read out or printed. */
    public function test_the_public_address_is_a_readable_slug(): void
    {
        $catalogue = $this->withArtwork('Office Lunches');

        $this->assertSame('office-lunches', $catalogue->slug);
        $this->assertStringEndsWith('/catalogue/office-lunches', route('catalogue.show', $catalogue));
    }

    /**
     * A shared or printed link must keep working, so renaming the category
     * leaves its address alone.
     */
    public function test_renaming_a_category_does_not_break_its_link(): void
    {
        $catalogue = $this->withArtwork();

        $this->actingAs($this->manager())->put(route('catering-catalogues.update', $catalogue), [
            'name' => 'Wedding & Homecoming Packages',
            'is_active' => '1',
        ])->assertRedirect();

        $catalogue->refresh();
        $this->assertSame('Wedding & Homecoming Packages', $catalogue->name);
        $this->assertSame('wedding-packages', $catalogue->slug);

        $this->get(route('catalogue.show', $catalogue))->assertOk();
    }

    public function test_two_categories_named_alike_get_distinct_addresses(): void
    {
        $first = $this->withArtwork('Wedding Packages', 1);
        $second = $this->withArtwork('Wedding Packages', 1);

        $this->assertNotSame($first->slug, $second->slug);
        $this->assertSame('wedding-packages-2', $second->slug);
    }

    public function test_a_hidden_category_is_not_published(): void
    {
        $catalogue = $this->withArtwork();

        $this->actingAs($this->manager())->put(route('catering-catalogues.update', $catalogue), [
            'name' => $catalogue->name,
            'is_active' => '0',
        ])->assertRedirect();

        $this->get(route('catalogue.index'))->assertOk()->assertDontSee('Wedding Packages');
        $this->get(route('catalogue.show', $catalogue->fresh()))->assertNotFound();
    }

    /** A category with no artwork has nothing to show, so it is not listed. */
    public function test_a_category_without_artwork_stays_off_the_public_index(): void
    {
        Storage::fake('public');

        $this->actingAs($this->manager())->post(route('catering-catalogues.store'), [
            'name' => 'Nothing Uploaded Yet',
            'is_active' => '1',
        ])->assertRedirect();

        $this->get(route('catalogue.index'))->assertOk()->assertDontSee('Nothing Uploaded Yet');
    }

    /** More pages can be added later without disturbing the ones already there. */
    public function test_pages_can_be_added_and_removed_individually(): void
    {
        $catalogue = $this->withArtwork(pages: 2);

        $this->actingAs($this->manager())->put(route('catering-catalogues.update', $catalogue), [
            'name' => $catalogue->name,
            'is_active' => '1',
            'images' => [UploadedFile::fake()->image('page3.jpg')],
        ])->assertRedirect();

        $this->assertCount(3, $catalogue->fresh()->images);

        $page = $catalogue->fresh()->images->last();
        $path = $page->path;

        $this->actingAs($this->manager())
            ->delete(route('catering-catalogues.pages.destroy', [$catalogue, $page]))
            ->assertRedirect();

        $this->assertCount(2, $catalogue->fresh()->images);
        Storage::disk('public')->assertMissing($path);
    }

    /** Deleting the category takes its files with it. */
    public function test_deleting_a_category_removes_its_artwork(): void
    {
        $catalogue = $this->withArtwork();
        $paths = $catalogue->images->pluck('path');

        $this->actingAs($this->manager())
            ->delete(route('catering-catalogues.destroy', $catalogue))
            ->assertRedirect(route('catering-catalogues.index'));

        $this->assertSame(0, CateringCatalogue::query()->count());
        $this->assertDatabaseCount('catering_catalogue_images', 0);

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    public function test_managing_the_catalogue_needs_the_catering_permission(): void
    {
        $cashier = $this->cashier();

        $this->actingAs($cashier)->get(route('catering-catalogues.index'))->assertForbidden();

        // A stranger, with the cashier's session cleared away.
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->get(route('catering-catalogues.index'))->assertRedirect(route('login'));

        // The public side stays open to everyone.
        $this->get(route('catalogue.index'))->assertOk();
    }

    /** Artwork is images, not arbitrary uploads. */
    /**
     * A category holds several packages, each an artwork in its own right —
     * Weddings has Package 1, Package 2, Package 3 — rather than pages of one
     * long brochure.
     */
    public function test_a_category_holds_several_named_packages(): void
    {
        $catalogue = $this->withArtwork('Weddings', 3);

        // Named from the files they arrived as, so nothing is retyped.
        $this->assertSame(
            ['Package 1', 'Package 2', 'Package 3'],
            $catalogue->images->pluck('title')->all(),
        );

        $this->get(route('catalogue.show', $catalogue))
            ->assertOk()
            ->assertSee('Package 1')
            ->assertSee('Package 2')
            ->assertSee('Package 3')
            // Each package is linkable on its own.
            ->assertSee('id="package-'.$catalogue->images->first()->id.'"', false);
    }

    public function test_a_package_can_be_renamed_and_reordered(): void
    {
        $catalogue = $this->withArtwork('Weddings', 3);
        $second = $catalogue->images[1];

        $this->actingAs($this->manager())
            ->post(route('catering-catalogues.pages.update', [$catalogue, $second]), [
                'title' => 'Gold Package',
                'sort_order' => 1,
            ])->assertRedirect();

        $this->assertSame('Gold Package', $second->fresh()->title);

        $this->get(route('catalogue.show', $catalogue->fresh()))
            ->assertOk()
            ->assertSee('Gold Package')
            ->assertDontSee('Package 2');
    }

    /**
     * A revised artwork replaces the picture, not the package: the name and
     * the position stay, and the file it replaced is not left on disk.
     */
    public function test_an_artwork_image_can_be_replaced_in_place(): void
    {
        $catalogue = $this->withArtwork('Weddings', 3);
        $second = $catalogue->images[1];
        $originalPath = $second->path;

        $this->actingAs($this->manager())
            ->post(route('catering-catalogues.pages.update', [$catalogue, $second]), [
                'title' => $second->title,
                'sort_order' => $second->sort_order,
                'image' => UploadedFile::fake()->image('Package 2 revised.jpg'),
            ])->assertRedirect();

        $second->refresh();

        $this->assertNotSame($originalPath, $second->path);
        Storage::disk('public')->assertMissing($originalPath);
        Storage::disk('public')->assertExists($second->path);

        // Still Package 2, still second.
        $this->assertSame('Package 2', $second->title);
        $this->assertSame(2, $second->sort_order);
        $this->assertCount(3, $catalogue->fresh()->images);
    }

    /** One artwork cannot be edited through another category's address. */
    public function test_an_artwork_cannot_be_edited_through_the_wrong_category(): void
    {
        $weddings = $this->withArtwork('Weddings', 1);
        $lunches = $this->withArtwork('Office Lunches', 1);

        $this->actingAs($this->manager())
            ->post(route('catering-catalogues.pages.update', [$lunches, $weddings->images->first()]), [
                'title' => 'Sneaked In',
            ])->assertNotFound();

        $this->assertSame('Package 1', $weddings->images->first()->fresh()->title);
    }

    /** A camera filename is no name at all, so the position stands in. */
    public function test_an_unnamed_package_falls_back_to_its_position(): void
    {
        Storage::fake('public');

        $this->actingAs($this->manager())->post(route('catering-catalogues.store'), [
            'name' => 'Office Lunches',
            'is_active' => '1',
            'images' => [UploadedFile::fake()->image('IMG_2381.jpg')],
        ])->assertRedirect();

        $catalogue = CateringCatalogue::query()->where('slug', 'office-lunches')->sole();

        $this->assertNull($catalogue->images->first()->title);
        $this->assertSame('Package 1', $catalogue->images->first()->displayTitle(1));

        $this->get(route('catalogue.show', $catalogue))->assertOk()->assertSee('Package 1');
    }

    public function test_a_non_image_upload_is_refused(): void
    {
        Storage::fake('public');

        $this->actingAs($this->manager())->post(route('catering-catalogues.store'), [
            'name' => 'Bad Upload',
            'is_active' => '1',
            'images' => [UploadedFile::fake()->create('menu.pdf', 40, 'application/pdf')],
        ])->assertSessionHasErrors('images.0');

        $this->assertSame(0, CateringCatalogue::query()->count());
    }
}
