<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RestaurantTableRequest;
use App\Models\RestaurantTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RestaurantTableController extends Controller
{
    public function index(): View
    {
        $tables = RestaurantTable::query()
            ->ordered()
            ->with('activeOrder')
            ->withCount('orders')
            ->get();

        return view('admin.tables.index', compact('tables'));
    }

    public function create(): View
    {
        return view('admin.tables.form', ['table' => new RestaurantTable(['is_active' => true])]);
    }

    public function store(RestaurantTableRequest $request): RedirectResponse
    {
        RestaurantTable::query()->create($request->validated());

        return redirect()->route('tables.index')->with('status', 'Table created.');
    }

    public function edit(RestaurantTable $table): View
    {
        return view('admin.tables.form', compact('table'));
    }

    public function update(RestaurantTableRequest $request, RestaurantTable $table): RedirectResponse
    {
        $table->update($request->validated());

        return redirect()->route('tables.index')->with('status', 'Table updated.');
    }

    public function destroy(RestaurantTable $table): RedirectResponse
    {
        if ($table->activeOrder()->exists()) {
            return back()->with('error', "{$table->name} has an open order and cannot be deleted.");
        }

        // Once a table has history the foreign key protects it, so it is
        // deactivated instead and simply stops appearing in the POS.
        if ($table->orders()->exists()) {
            $table->update(['is_active' => false]);

            return redirect()->route('tables.index')
                ->with('status', "{$table->name} has past orders, so it was deactivated instead of deleted.");
        }

        $table->delete();

        return redirect()->route('tables.index')->with('status', 'Table deleted.');
    }
}
