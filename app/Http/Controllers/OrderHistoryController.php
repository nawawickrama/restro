<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Queries\OrderHistoryFilters;
use App\Queries\OrderHistoryQuery;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Order history: a list, a detail view, and a reprintable receipt.
 *
 * The list is paged and filtered entirely in the database — see
 * {@see OrderHistoryQuery} for why every filter is shaped the way it is.
 */
class OrderHistoryController extends Controller
{
    public function __construct(private readonly OrderHistoryQuery $history) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Order::class);

        $filters = OrderHistoryFilters::fromRequest($request);

        return view('orders.index', [
            'filters' => $filters,
            'orders' => $this->history->paginate($filters),

            // Takings are a reporting figure, so the same permission that
            // gates the dashboard's money gates it here.
            'summary' => Auth::user()->can(Permissions::VIEW_REPORTS)
                ? $this->history->summary($filters)
                : null,
        ]);
    }

    public function show(Order $order): View
    {
        $this->authorize('view', $order);

        $order->load(['items', 'table', 'user', 'payments.user']);

        return view('orders.show', compact('order'));
    }

    /** The printable receipt, also used for reprints from history. */
    public function receipt(Order $order): View
    {
        $this->authorize('printReceipt', $order);

        $order->load(['items', 'table', 'payments']);

        return view('orders.receipt', compact('order'));
    }
}
