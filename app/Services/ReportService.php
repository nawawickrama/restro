<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The three numbers a small restaurant actually needs: what came in today,
 * where it came from, and what sold.
 *
 * Only completed orders count as sales; open and cancelled orders are noise.
 */
class ReportService
{
    /**
     * @return array{total: float, orders: int, by_method: array<string, float>, average: float}
     */
    public function salesSummary(Carbon $from, Carbon $to): array
    {
        $orders = Order::query()
            ->completed()
            ->whereBetween('created_at', [$from, $to]);

        $total = (float) (clone $orders)->sum('total');
        $count = (clone $orders)->count();

        $byMethod = Payment::query()
            ->whereBetween('paid_at', [$from, $to])
            ->whereHas('order', fn ($q) => $q->completed())
            ->selectRaw('method, SUM(amount) as amount')
            ->groupBy('method')
            ->pluck('amount', 'method')
            ->map(fn ($amount) => (float) $amount)
            ->all();

        // Always report every method, so a zero-card day still shows a card row.
        foreach (PaymentMethod::cases() as $method) {
            $byMethod[$method->value] ??= 0.0;
        }

        return [
            'total' => round($total, 2),
            'orders' => $count,
            'by_method' => $byMethod,
            'average' => $count > 0 ? round($total / $count, 2) : 0.0,
        ];
    }

    /**
     * @return array<string, array{label: string, orders: int, total: float}>
     */
    public function salesByOrderType(Carbon $from, Carbon $to): array
    {
        $rows = Order::query()
            ->completed()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('type, COUNT(*) as orders, SUM(total) as total')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $result = [];

        foreach (OrderType::cases() as $type) {
            $row = $rows->get($type->value);

            $result[$type->value] = [
                'label' => $type->label(),
                'orders' => (int) ($row->orders ?? 0),
                'total' => round((float) ($row->total ?? 0), 2),
            ];
        }

        return $result;
    }

    /**
     * Best and worst sellers, counted from the order lines so the figures
     * survive menu items being renamed or deleted.
     *
     * @return Collection<int, object{name: string, quantity: int, total: float}>
     */
    public function salesByItem(Carbon $from, Carbon $to, string $direction = 'desc', int $limit = 15): Collection
    {
        return OrderItem::query()
            ->whereHas('order', fn ($q) => $q->completed()->whereBetween('created_at', [$from, $to]))
            ->selectRaw('name, SUM(quantity) as quantity, SUM(line_total) as total')
            ->groupBy('name')
            ->orderBy('quantity', $direction === 'asc' ? 'asc' : 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (object) [
                'name' => $row->name,
                'quantity' => (int) $row->quantity,
                'total' => round((float) $row->total, 2),
            ]);
    }

    /**
     * Takings day by day, which is the whole point of a weekly or monthly
     * report — a single total tells you nothing about which days carried it.
     *
     * Days with no sales are included as zeroes so the table has no gaps.
     *
     * @return Collection<int, object{date: Carbon, orders: int, total: float}>
     */
    public function dailyBreakdown(Carbon $from, Carbon $to): Collection
    {
        // The range predicate is what uses the index; grouping by the date of
        // the already-filtered rows is cheap.
        $rows = Order::query()
            ->completed()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as orders, SUM(total) as total')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $days = collect();

        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            $row = $rows->get($day->toDateString());

            $days->push((object) [
                'date' => $day->copy(),
                'orders' => (int) ($row->orders ?? 0),
                'total' => round((float) ($row->total ?? 0), 2),
            ]);
        }

        return $days;
    }

    /** Live counts for the dashboard: what is happening right now. */
    public function liveCounts(): array
    {
        return [
            'occupied_tables' => Order::query()
                ->where('status', OrderStatus::Open)
                ->where('type', OrderType::DineIn)
                ->count(),
            'open_orders' => Order::query()->open()->count(),
            'pending_phone_orders' => Order::query()
                ->open()
                ->where('type', OrderType::PhoneTakeaway)
                ->count(),
        ];
    }
}
