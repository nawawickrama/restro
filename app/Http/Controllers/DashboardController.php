<?php

namespace App\Http\Controllers;

use App\Enums\OrderType;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Services\ReportService;
use App\Support\Permissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function __invoke(): View
    {
        // Takings are only shown to staff allowed to see reports; the rest of
        // the dashboard (what is open right now) is useful to everyone.
        $summary = Auth::user()->can(Permissions::VIEW_REPORTS)
            ? $this->reports->salesSummary(today()->startOfDay(), today()->endOfDay())
            : null;

        $occupiedTables = RestaurantTable::query()
            ->active()
            ->ordered()
            ->whereHas('activeOrder')
            ->with('activeOrder')
            ->get();

        $openOrders = Order::query()
            ->open()
            ->with(['table', 'user'])
            ->latest()
            ->limit(10)
            ->get();

        $pendingPhoneOrders = Order::query()
            ->open()
            ->ofType(OrderType::PhoneTakeaway)
            ->latest()
            ->get();

        return view('dashboard', compact('summary', 'occupiedTables', 'openOrders', 'pendingPhoneOrders'));
    }
}
