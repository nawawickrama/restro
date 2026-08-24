<?php

namespace App\Http\Controllers\Catering;

use App\Enums\CateringUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\CateringItemRequest;
use App\Models\CateringItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The catering catalogue: the dishes the kitchen quotes for functions, kept
 * apart from the menu the till sells over the counter.
 */
class CateringItemController extends Controller
{
    public function index(): View
    {
        return view('catering.items.index', [
            'items' => CateringItem::query()->ordered()->withCount('bookingItems')->get(),
        ]);
    }

    public function create(): View
    {
        return view('catering.items.form', [
            'item' => new CateringItem(['is_active' => true, 'default_unit' => CateringUnit::Kilogram]),
            'units' => CateringUnit::cases(),
        ]);
    }

    public function store(CateringItemRequest $request): RedirectResponse
    {
        CateringItem::query()->create($request->validated());

        return redirect()->route('catering-items.index')->with('status', 'Catering item added.');
    }

    public function edit(CateringItem $cateringItem): View
    {
        return view('catering.items.form', [
            'item' => $cateringItem,
            'units' => CateringUnit::cases(),
        ]);
    }

    public function update(CateringItemRequest $request, CateringItem $cateringItem): RedirectResponse
    {
        $cateringItem->update($request->validated());

        return redirect()->route('catering-items.index')->with('status', 'Catering item updated.');
    }

    /**
     * An item already written onto a booking sheet is switched off rather than
     * deleted, so past sheets keep reading the way the kitchen worked from them.
     */
    public function destroy(CateringItem $cateringItem): RedirectResponse
    {
        if ($cateringItem->bookingItems()->exists()) {
            $cateringItem->update(['is_active' => false]);

            return redirect()->route('catering-items.index')->with(
                'status',
                "{$cateringItem->name} appears on past bookings, so it was switched off instead of deleted.",
            );
        }

        $cateringItem->delete();

        return redirect()->route('catering-items.index')->with('status', 'Catering item deleted.');
    }
}
