<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MenuItemRequest;
use App\Models\Category;
use App\Models\MenuItem;
use App\Services\MenuItemImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MenuItemController extends Controller
{
    public function __construct(private readonly MenuItemImageService $images) {}

    public function index(Request $request): View
    {
        $categoryId = $request->integer('category');

        $items = MenuItem::query()
            ->with('category')
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($search = $request->string('search')->toString(), fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('category_id')
            ->ordered()
            ->paginate(25)
            ->withQueryString();

        return view('admin.menu-items.index', [
            'items' => $items,
            'categories' => Category::query()->ordered()->get(),
            'categoryId' => $categoryId,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.menu-items.form', [
            'item' => new MenuItem(['is_active' => true]),
            'categories' => Category::query()->ordered()->get(),
        ]);
    }

    public function store(MenuItemRequest $request): RedirectResponse
    {
        $item = MenuItem::query()->create($request->menuItemAttributes());

        if ($request->hasFile('image')) {
            $this->images->store($item, $request->file('image'));
        }

        return redirect()->route('menu-items.index')->with('status', 'Menu item created.');
    }

    public function edit(MenuItem $menuItem): View
    {
        return view('admin.menu-items.form', [
            'item' => $menuItem,
            'categories' => Category::query()->ordered()->get(),
        ]);
    }

    /** Business rule 7: this changes the menu, never the price on past orders. */
    public function update(MenuItemRequest $request, MenuItem $menuItem): RedirectResponse
    {
        $menuItem->update($request->menuItemAttributes());

        if ($request->hasFile('image')) {
            $this->images->store($menuItem, $request->file('image'));
        } elseif ($request->boolean('remove_image')) {
            $this->images->remove($menuItem);
        }

        return redirect()->route('menu-items.index')->with('status', 'Menu item updated.');
    }

    /**
     * Items that already appear on orders are switched off instead of deleted,
     * which keeps the reporting history intact.
     */
    public function destroy(MenuItem $menuItem): RedirectResponse
    {
        if ($menuItem->orderItems()->exists()) {
            $menuItem->update(['is_active' => false]);

            return redirect()->route('menu-items.index')
                ->with('status', "{$menuItem->name} has past orders, so it was disabled instead of deleted.");
        }

        $this->images->remove($menuItem);
        $menuItem->delete();

        return redirect()->route('menu-items.index')->with('status', 'Menu item deleted.');
    }
}
