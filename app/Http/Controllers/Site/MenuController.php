<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\View\View;

/**
 * The full menu, as a public page.
 *
 * Meant to be reached by scanning a QR code at the table, so it is read on a
 * phone, in a dining room, by somebody deciding what to eat. It shows what is
 * on and what it costs, and nothing else — there is no ordering here.
 *
 * Everything comes from the same categories and items the till sells, so a
 * price change in the back office reaches the table without anybody
 * reprinting anything.
 */
class MenuController extends Controller
{
    public function __invoke(): View
    {
        $categories = Category::query()
            ->active()
            ->ordered()
            ->with('activeMenuItems')
            ->get()
            // A category with nothing sellable in it is not worth a heading.
            ->filter(fn (Category $category) => $category->activeMenuItems->isNotEmpty())
            ->values();

        return view('site.menu', compact('categories'));
    }
}
