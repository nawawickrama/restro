<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\View\View;

/**
 * The restaurant's public page.
 *
 * The only screen in this application meant for customers rather than staff,
 * and the only one reachable without signing in.
 *
 * Its menu section reads the same categories and items the till sells from, so
 * the website cannot drift out of date the way a hand-maintained page does:
 * change a price in the POS and the public page changes with it.
 */
class LandingController extends Controller
{
    /** How many dishes to show per category before it stops being a taster. */
    private const ITEMS_PER_CATEGORY = 4;

    public function __invoke(): View
    {
        $categories = Category::query()
            ->active()
            ->ordered()
            ->with(['activeMenuItems' => fn ($query) => $query->limit(self::ITEMS_PER_CATEGORY)])
            ->get()
            ->filter(fn (Category $category) => $category->activeMenuItems->isNotEmpty())
            ->take(4);

        return view('site.landing', compact('categories'));
    }
}
