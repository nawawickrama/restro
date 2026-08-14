<?php

namespace App\Queries;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reads the order history.
 *
 * Written for a table that keeps growing: a restaurant taking 100 orders a day
 * has 36,000 rows after a year and 180,000 after five. Only one page is ever
 * loaded, every filter is a range MySQL can seek to with an index, and no
 * query wraps `created_at` in a function — doing so silently turns an indexed
 * lookup into a full table scan.
 */
class OrderHistoryQuery
{
    public function paginate(OrderHistoryFilters $filters): LengthAwarePaginator
    {
        return $this->build($filters)
            ->with(['table', 'user'])
            ->orderBy($filters->sortColumn(), $filters->direction)
            // A stable tie-breaker: without it, two orders sharing a timestamp
            // can swap places between pages and appear twice or not at all.
            ->orderBy('id', $filters->direction)
            ->paginate($filters->perPage)
            ->withQueryString();
    }

    /**
     * Headline figures for the current filter.
     *
     * Kept to one aggregate query, and bounded by the same date window as the
     * list, so it costs about what the page itself costs.
     *
     * @return array{orders: int, completed: int, revenue: float}
     */
    public function summary(OrderHistoryFilters $filters): array
    {
        $row = $this->build($filters)
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('SUM(status = ?) as completed', [OrderStatus::Completed->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN total END), 0) as revenue', [OrderStatus::Completed->value])
            ->first();

        return [
            'orders' => (int) $row->orders,
            'completed' => (int) $row->completed,
            'revenue' => round((float) $row->revenue, 2),
        ];
    }

    /** @return Builder<Order> */
    private function build(OrderHistoryFilters $filters): Builder
    {
        return Order::query()
            // Range comparison rather than whereDate(): the column stays bare,
            // so `orders_created_at_index` and its composites can be used.
            ->when($filters->from, fn (Builder $q, $from) => $q->where('created_at', '>=', $from))
            ->when($filters->to, fn (Builder $q, $to) => $q->where('created_at', '<=', $to))
            ->when($filters->type, fn (Builder $q, $type) => $q->where('type', $type))
            ->when($filters->status, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($filters->paymentStatus, fn (Builder $q, $status) => $q->where('payment_status', $status))
            ->when($filters->search !== '', fn (Builder $q) => $this->applySearch($q, $filters->search));
    }

    /**
     * Search the three things a person actually knows: the order number, the
     * customer's phone, the customer's name.
     *
     * Matches are anchored to the start of the value wherever possible, since
     * a leading wildcard cannot use an index. The one exception is a short
     * numeric term, which is treated as the daily sequence from an order
     * number ("42" finds ORD-20260812-0042) — that one scans, but only within
     * whatever date window is already selected.
     *
     * @param  Builder<Order>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $term = str_replace(['%', '_'], ['\%', '\_'], $search);

        $query->where(function (Builder $q) use ($term) {
            $q->where('order_number', 'like', $term.'%')
                ->orWhere('customer_phone', 'like', $term.'%')
                ->orWhere('customer_name', 'like', $term.'%');

            if (ctype_digit($term) && strlen($term) <= 4) {
                $q->orWhere('order_number', 'like', '%-'.str_pad($term, 4, '0', STR_PAD_LEFT));
            }
        });
    }
}
