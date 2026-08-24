<?php

namespace App\Http\Controllers\Catering;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Site\CatalogueController;
use App\Http\Requests\CateringCatalogueRequest;
use App\Models\CateringCatalogue;
use App\Models\CateringCatalogueImage;
use App\Services\CateringCatalogueImageService;
use Illuminate\Http\RedirectResponse;
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

        $this->images->add($catalogue, $request->file('images') ?? []);

        return redirect()->route('catering-catalogues.edit', $catalogue)
            ->with('status', 'Catalogue created. Add the artwork pages below.');
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

        $added = $this->images->add($cateringCatalogue, $request->file('images') ?? []);

        return redirect()->route('catering-catalogues.edit', $cateringCatalogue)->with(
            'status',
            $added > 0
                ? "Catalogue updated and {$added} ".str('page')->plural($added).' added.'
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

    /** Remove a single page of artwork. */
    public function destroyImage(CateringCatalogue $cateringCatalogue, CateringCatalogueImage $image): RedirectResponse
    {
        abort_unless($image->catering_catalogue_id === $cateringCatalogue->id, 404);

        $this->images->remove($image);

        return back()->with('status', 'Page removed.');
    }
}
