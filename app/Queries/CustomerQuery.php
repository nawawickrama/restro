<?php

namespace App\Queries;

use App\Enums\OrderStatus;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reads the customer list.
 *
 * Written the same way as the order history, and for the same reason: a
 * restaurant that keeps a number from every phone order gathers thousands of
 * these. One page is ever loaded, the counting and totalling happen in SQL
 * rather than by walking relations in PHP, and search is anchored so it can
 * use an index.
 */
class CustomerQuery
{
    public function paginate(CustomerFilters $filters): LengthAwarePaginator
    {
        return $this->build($filters)
            ->orderBy($filters->sortColumn(), $filters->direction)
            // A stable tie-breaker, so two customers sharing a value cannot
            // swap places between pages and appear twice or not at all.
            ->orderBy('id', $filters->direction)
            ->paginate($filters->perPage)
            ->withQueryString();
    }

    /**
     * Headline counts for the current filter.
     *
     * Deliberately built from the plain filtered query rather than the listing
     * one: the per-customer totals are subquery aliases, and SQL cannot refer
     * to an alias from another expression in the same select.
     *
     * @return array{customers: int, with_orders: int}
     */
    public function summary(CustomerFilters $filters): array
    {
        return [
            'customers' => $this->filtered($filters)->count(),
            'with_orders' => $this->filtered($filters)
                ->whereHas('orders', fn (Builder $q) => $q->where('status', OrderStatus::Completed))
                ->count(),
        ];
    }

    /** The filters on their own, with nothing aggregated. @return Builder<Customer> */
    private function filtered(CustomerFilters $filters): Builder
    {
        return Customer::query()
            ->when($filters->source, fn (Builder $q, $source) => $q->ofSource($source))
            ->when($filters->search !== '', fn (Builder $q) => $this->applySearch($q, $filters->search));
    }

    /** The filtered list with each customer's figures. @return Builder<Customer> */
    private function build(CustomerFilters $filters): Builder
    {
        // Counted and totalled by the database, so the page costs the same
        // whether it shows 25 customers or 100. Completed orders only — an
        // abandoned order is not money the customer has spent.
        $completed = fn (Builder $q) => $q->where('status', OrderStatus::Completed);

        return $this->filtered($filters)
            ->withCount(['orders as orders_count' => $completed])
            ->withSum(['orders as orders_sum_total' => $completed], 'total')
            ->withMax(['orders as last_order_at' => $completed], 'created_at');
    }

    /**
     * Search by name or number.
     *
     * Anchored to the start of the value, since a leading wildcard cannot use
     * an index. A number is matched against its digits-only form, so a search
     * typed without spaces still finds one that was saved with them.
     *
     * @param  Builder<Customer>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $term = str_replace(['%', '_'], ['\%', '\_'], $search);
        $digits = Customer::normalisePhone($search);

        $query->where(function (Builder $q) use ($term, $digits) {
            $q->where('name', 'like', $term.'%')
                ->orWhere('phone', 'like', $term.'%');

            if ($digits !== '') {
                $q->orWhere('phone_digits', 'like', $digits.'%');
            }
        });
    }
}
