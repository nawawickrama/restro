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
                ->map(fn ($n) => UploadedFile::fake()->image("page{$n}.jpg"))
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
