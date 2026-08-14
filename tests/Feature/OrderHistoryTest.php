<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Queries\OrderHistoryFilters;
use App\Queries\OrderHistoryQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The order history has to stay quick as the table grows — a restaurant taking
 * 100 orders a day passes 36,000 rows inside a year. These tests pin down the
 * properties that keep it that way.
 */
class OrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The regression guard for the original bug: whereDate() wraps created_at
     * in CAST(), which makes the index unusable and turns every page load into
     * a full table scan.
     */
    public function test_date_filters_never_wrap_the_timestamp_in_a_function(): void
    {
        foreach (['today', 'yesterday', 'week', 'month'] as $range) {
            $sql = $this->sqlFor(['range' => $range]);

            $this->assertStringNotContainsStringIgnoringCase('cast(', $sql, "[{$range}] must not cast created_at");
            $this->assertStringNotContainsStringIgnoringCase('date(', $sql, "[{$range}] must not wrap created_at");
            $this->assertStringContainsString('"created_at" >=', str_replace('`', '"', $sql));
        }
    }

    public function test_only_one_page_of_rows_is_ever_loaded(): void
    {
        $user = $this->cashier();
        Order::factory()->count(60)->create(['user_id' => $user->id]);

        $paginator = $this->paginate(['range' => 'all', 'per_page' => 25]);

        $this->assertCount(25, $paginator->items());
        $this->assertSame(60, $paginator->total());
        $this->assertSame(3, $paginator->lastPage());
    }

    /** Sort and page size come off the URL, so they are matched to a whitelist. */
    public function test_sort_and_page_size_reject_anything_not_whitelisted(): void
    {
        $filters = OrderHistoryFilters::fromRequest(Request::create('/orders', 'GET', [
            'sort' => 'total; DROP TABLE orders',
            'direction' => 'sideways',
            'per_page' => 5000,
        ]));

        $this->assertSame('created_at', $filters->sortColumn());
        $this->assertSame('desc', $filters->direction);
        $this->assertSame(25, $filters->perPage);

        // And the resulting SQL is untouched by the attempted injection.
        $this->assertStringNotContainsString('DROP TABLE', $this->sqlFor([
            'sort' => 'total; DROP TABLE orders',
        ]));
    }

    public function test_sorting_is_stable_across_pages_when_timestamps_collide(): void
    {
        $user = $this->cashier();
        $moment = now()->subHour();
        Order::factory()->count(10)->create(['user_id' => $user->id, 'created_at' => $moment]);

        $first = $this->paginate(['range' => 'all', 'per_page' => 5], page: 1);
        $second = $this->paginate(['range' => 'all', 'per_page' => 5], page: 2);

        $ids = array_merge(
            collect($first->items())->pluck('id')->all(),
            collect($second->items())->pluck('id')->all(),
        );

        // No row appears twice and none goes missing.
        $this->assertCount(10, array_unique($ids));
    }

    public function test_the_date_range_filters_to_the_right_window(): void
    {
        $user = $this->cashier();
        Order::factory()->create(['user_id' => $user->id, 'created_at' => now()]);
        Order::factory()->create(['user_id' => $user->id, 'created_at' => now()->subDay()]);
        Order::factory()->create(['user_id' => $user->id, 'created_at' => now()->subDays(20)]);

        $this->assertSame(1, $this->paginate(['range' => 'today'])->total());
        $this->assertSame(1, $this->paginate(['range' => 'yesterday'])->total());
        $this->assertSame(2, $this->paginate(['range' => 'week'])->total());
        $this->assertSame(3, $this->paginate(['range' => 'all'])->total());
    }

    public function test_a_custom_date_range_is_honoured(): void
    {
        $user = $this->cashier();
        Order::factory()->create(['user_id' => $user->id, 'created_at' => now()->subDays(10)]);
        Order::factory()->create(['user_id' => $user->id, 'created_at' => now()->subDays(40)]);

        $total = $this->paginate([
            'range' => 'custom',
            'from' => now()->subDays(15)->toDateString(),
            'to' => now()->toDateString(),
        ])->total();

        $this->assertSame(1, $total);
    }

    /**
     * The filter bar submits one form, so narrowing by type must not throw away
     * the selected date range — the previous version forced every submit to
     * 'custom' with a hidden field and silently lost the preset.
     */
    public function test_changing_a_filter_keeps_the_selected_date_range(): void
    {
        $filters = OrderHistoryFilters::fromRequest(Request::create('/orders', 'GET', [
            'range' => 'week',
            'type' => 'dine_in',
            'search' => 'nimal',
        ]));

        $this->assertSame('week', $filters->range);
        $this->assertTrue($filters->from->isSameDay(today()->subDays(6)));
    }

    /** Typing a date means a custom range, whatever preset was selected. */
    public function test_supplying_dates_switches_the_range_to_custom(): void
    {
        $filters = OrderHistoryFilters::fromRequest(Request::create('/orders', 'GET', [
            'range' => 'today',
            'from' => now()->subDays(3)->toDateString(),
        ]));

        $this->assertSame('custom', $filters->range);
        $this->assertTrue($filters->from->isSameDay(today()->subDays(3)));
    }

    public function test_active_filters_are_listed_so_they_can_be_removed_one_at_a_time(): void
    {
        $filters = OrderHistoryFilters::fromRequest(Request::create('/orders', 'GET', [
            'type' => 'dine_in',
            'status' => 'completed',
            'search' => 'nimal',
        ]));

        $this->assertSame(3, $filters->activeCount());
        $this->assertSame(['search', 'type', 'status'], array_keys($filters->activeChips()));

        // Removing one leaves the others in the link.
        $query = $filters->toQuery(['type' => null]);
        $this->assertNull($query['type']);
        $this->assertSame('completed', $query['status']);
        $this->assertSame('nimal', $query['search']);
    }

    public function test_the_filter_bar_shows_the_active_filters(): void
    {
        $user = $this->cashier();
        Order::factory()->dineIn()->create(['user_id' => $user->id]);

        $this->actingAs($this->admin())
            ->get(route('orders.index', ['range' => 'all', 'type' => 'dine_in', 'search' => 'nimal']))
            ->assertOk()
            ->assertSee('Filtered by')
            ->assertSee('Dine In')
            ->assertSee('“nimal”', false);
    }

    public function test_search_finds_an_order_by_number_phone_or_name(): void
    {
        $cashier = $this->cashier();
        $this->actingAs($cashier)->post(route('pos.phone.store'));
        $order = Order::query()->sole();

        $this->actingAs($cashier)->post(route('pos.orders.customer', $order), [
            'customer_phone' => '0771234567',
            'customer_name' => 'Nimal Perera',
        ]);

        foreach ([$order->order_number, '0771', 'Nimal', substr($order->order_number, -4)] as $term) {
            $this->assertSame(
                1,
                $this->paginate(['range' => 'all', 'search' => $term])->total(),
                "Searching for [{$term}] should find the order.",
            );
        }

        $this->assertSame(0, $this->paginate(['range' => 'all', 'search' => 'nobody'])->total());
    }

    /** A wildcard typed into the search box is a literal, not a pattern. */
    public function test_search_escapes_wildcards(): void
    {
        $user = $this->cashier();
        Order::factory()->create(['user_id' => $user->id, 'customer_name' => 'Nimal']);

        $this->assertSame(0, $this->paginate(['range' => 'all', 'search' => '%'])->total());
    }

    /**
     * The real N+1 test: the number of queries must be the same whether the
     * page shows 5 orders or 100, and whether or not they have tables attached.
     */
    public function test_the_query_count_does_not_grow_with_the_number_of_rows(): void
    {
        $cashier = $this->cashier();
        $admin = $this->admin();

        Order::factory()->count(5)->dineIn()->create(['user_id' => $cashier->id]);

        // The first request of the process warms the permission and settings
        // caches; measuring it would compare a cold run against a warm one.
        $this->actingAs($admin)->get(route('orders.index'))->assertOk();

        $small = $this->countQueriesFor($admin, ['range' => 'all', 'per_page' => 100]);

        Order::factory()->count(95)->dineIn()->create(['user_id' => $cashier->id]);
        $large = $this->countQueriesFor($admin, ['range' => 'all', 'per_page' => 100]);

        $this->assertSame(
            $small,
            $large,
            "5 orders took {$small} queries, 100 took {$large} — something runs per row.",
        );

        // And that fixed number stays small: page, count, two eager loads,
        // summary, settings.
        $this->assertLessThanOrEqual(8, $large, "Expected a handful of queries, ran {$large}.");
    }

    private function countQueriesFor($user, array $query): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)->get(route('orders.index', $query))->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_the_summary_is_hidden_from_staff_without_the_reports_permission(): void
    {
        $user = $this->cashier();
        Order::factory()->completed()->create(['user_id' => $user->id, 'total' => 1500]);

        $this->actingAs($this->admin())->get(route('orders.index'))
            ->assertOk()
            ->assertSee('Revenue');

        $this->actingAs($user)->get(route('orders.index'))
            ->assertOk()
            ->assertDontSee('Revenue');
    }

    public function test_the_summary_counts_only_completed_orders(): void
    {
        $user = $this->cashier();
        Order::factory()->completed()->create(['user_id' => $user->id, 'total' => 1000]);
        Order::factory()->completed()->create(['user_id' => $user->id, 'total' => 500]);
        Order::factory()->cancelled()->create(['user_id' => $user->id, 'total' => 9999]);
        Order::factory()->create(['user_id' => $user->id, 'total' => 4444]);

        $summary = app(OrderHistoryQuery::class)->summary(
            OrderHistoryFilters::fromRequest(Request::create('/orders', 'GET', ['range' => 'all'])),
        );

        $this->assertSame(4, $summary['orders']);
        $this->assertSame(2, $summary['completed']);
        $this->assertSame(1500.0, $summary['revenue']);
    }

    public function test_the_table_renders_with_sortable_headings(): void
    {
        $user = $this->cashier();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $this->actingAs($this->admin())->get(route('orders.index', ['range' => 'all']))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Date &amp; time', false)
            ->assertSee('sort=total');
    }

    /** @return LengthAwarePaginator */
    private function paginate(array $query, int $page = 1)
    {
        // The paginator reads the page from the resolver, so it has to be set
        // before the query runs rather than on the result.
        Paginator::currentPageResolver(fn () => $page);

        $filters = OrderHistoryFilters::fromRequest(Request::create('/orders', 'GET', $query));

        return app(OrderHistoryQuery::class)->paginate($filters);
    }

    private function sqlFor(array $query): string
    {
        $filters = OrderHistoryFilters::fromRequest(Request::create('/orders', 'GET', $query));

        DB::enableQueryLog();
        app(OrderHistoryQuery::class)->paginate($filters);
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        return collect($log)->pluck('query')->implode(' | ');
    }
}
