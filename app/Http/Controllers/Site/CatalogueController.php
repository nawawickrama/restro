<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CateringCatalogue;
use Illuminate\View\View;

/**
 * The catering catalogue as a customer sees it.
 *
 * Public and read-only, so it can be sent to somebody enquiring about a
 * wedding or put behind a QR code, and it works for a person who has never
 * signed in to anything.
 */
class CatalogueController extends Controller
{
    public function index(): View
    {
        return view('site.catalogue.index', [
            'catalogues' => CateringCatalogue::query()
                ->active()
                ->ordered()
                ->with('images')
                ->get()
                // A category with no artwork has nothing to show.
                ->filter(fn (CateringCatalogue $catalogue) => $catalogue->images->isNotEmpty())
                ->values(),
        ]);
    }

    public function show(CateringCatalogue $catalogue): View
    {
        abort_unless($catalogue->is_active, 404);

        $catalogue->load('images');

        return view('site.catalogue.show', [
            'catalogue' => $catalogue,
            'others' => CateringCatalogue::query()
                ->active()
                ->ordered()
                ->whereKeyNot($catalogue->getKey())
                ->with('images')
                ->get()
                ->filter(fn (CateringCatalogue $other) => $other->images->isNotEmpty())
                ->values(),
        ]);
    }
}
