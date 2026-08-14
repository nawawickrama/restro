<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()
            ->ordered()
            ->withCount('menuItems')
            ->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.categories.form', ['category' => new Category(['is_active' => true])]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::query()->create($request->validated());

        return redirect()->route('categories.index')->with('status', 'Category created.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', compact('category'));
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('categories.index')->with('status', 'Category updated.');
    }

    /**
     * A category holding menu items is switched off rather than deleted, so
     * past orders keep pointing at something real.
     */
    public function destroy(Category $category): RedirectResponse
    {
        if ($category->menuItems()->exists()) {
            return back()->with('error', 'Move or delete this category\'s items before deleting it.');
        }

        $category->delete();

        return redirect()->route('categories.index')->with('status', 'Category deleted.');
    }
}
