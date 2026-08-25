<?php

namespace App\Http\Controllers\Catering;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Site\CatalogueController;
use App\Http\Requests\CateringCatalogueRequest;
use App\Models\CateringCatalogue;
use App\Models\CateringCatalogueImage;
use App\Services\CateringCatalogueImageService;
use App\Support\UploadLimit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The catering brochure, from the back office: categories of artwork, and the
 * pages inside each. What a customer sees lives in {@see CatalogueController}.
 */
class CateringCatalogueController extends Controller
{
    public function __construct(private readonly CateringCatalogueImageService $images) {}

    public function index(): View
    {
        return view('catering.catalogue.index', [
            'catalogues' => CateringCatalogue::query()->ordered()->with('images')->get(),
        ]);
    }

    public function create(): View
    {
        return view('catering.catalogue.form', ['catalogue' => new CateringCatalogue(['is_active' => true])]);
    }

    public function store(CateringCatalogueRequest $request): RedirectResponse
    {
        $catalogue = CateringCatalogue::query()->create([
            ...$request->catalogueAttributes(),
            'slug' => CateringCatalogue::makeSlug($request->validated('name')),
        ]);

        $this->images->add($catalogue, $request->artworks());

        return redirect()->route('catering-catalogues.edit', $catalogue)
            ->with('status', 'Catalogue created.');
    }

    public function edit(CateringCatalogue $cateringCatalogue): View
    {
        $cateringCatalogue->load('images');

        return view('catering.catalogue.form', ['catalogue' => $cateringCatalogue]);
    }

    /**
     * The slug is deliberately left alone on rename: it may already be printed
     * on a card or sent to somebody, and a link that stops working is worse
     * than one whose wording has moved on.
     */
    public function update(CateringCatalogueRequest $request, CateringCatalogue $cateringCatalogue): RedirectResponse
    {
        $cateringCatalogue->update($request->catalogueAttributes());

        $added = $this->images->add($cateringCatalogue, $request->artworks());

        return redirect()->route('catering-catalogues.edit', $cateringCatalogue)->with(
            'status',
            $added > 0
                ? "Catalogue updated and {$added} ".str('artwork')->plural($added).' added.'
                : 'Catalogue updated.',
        );
    }

    public function destroy(CateringCatalogue $cateringCatalogue): RedirectResponse
    {
        $cateringCatalogue->load('images');

        $this->images->removeAll($cateringCatalogue);
        $cateringCatalogue->delete();

        return redirect()->route('catering-catalogues.index')->with('status', 'Catalogue deleted.');
    }

    /**
     * One artwork: its name, its place, and optionally a new image for it.
     *
     * Each artwork is a package in its own right, so each is edited on its own
     * rather than through a form covering the whole category. Replacing the
     * image keeps the name and the position, because a revised Package 2 is
     * still Package 2.
     */
    public function updateImage(
        Request $request,
        CateringCatalogue $cateringCatalogue,
        CateringCatalogueImage $image,
    ): RedirectResponse {
        abort_unless($image->catering_catalogue_id === $cateringCatalogue->id, 404);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.UploadLimit::kilobytes()],
        ], [
            'image.max' => 'The artwork must be smaller than '.UploadLimit::label().'.',
            'image.mimes' => 'The artwork must be a JPG, PNG or WebP file.',
        ]);

        $image->update([
            'title' => filled($validated['title'] ?? null) ? trim($validated['title']) : null,
            'sort_order' => $validated['sort_order'] ?? $image->sort_order,
        ]);

        if ($request->hasFile('image')) {
            $this->images->replace($image, $request->file('image'));

            return back()->with('status', 'Artwork replaced.');
        }

        return back()->with('status', 'Artwork updated.');
    }

    /** Remove a single artwork. */
    public function destroyImage(CateringCatalogue $cateringCatalogue, CateringCatalogueImage $image): RedirectResponse
    {
        abort_unless($image->catering_catalogue_id === $cateringCatalogue->id, 404);

        $this->images->remove($image);

        return back()->with('status', 'Page removed.');
    }
}
