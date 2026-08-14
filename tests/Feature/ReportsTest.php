<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\MenuItem;
use App\Services\CheckoutService;
use App\Services\OrderService;
use App\Services\ReportService;
use App\Support\ReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Daily, weekly and monthly figures, in the three shapes they are offered:
 * on screen, on paper, and as a spreadsheet.
 */
class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_presets_cover_the_periods_a_restaurant_asks_for(): void
    {
        $this->assertTrue(ReportPeriod::preset('today')->isSingleDay());
        $this->assertTrue(ReportPeriod::preset('yesterday')->from->isSameDay(today()->subDay()));

        $week = ReportPeriod::preset('week');
        $this->assertSame(7, $week->days());
        $this->assertTrue($week->from->isSameDay(today()->startOfWeek()));

        $month = ReportPeriod::preset('month');
        $this->assertSame(today()->daysInMonth, $month->days());
        $this->assertTrue($month->from->isSameDay(today()->startOfMonth()));

        $lastMonth = ReportPeriod::preset('last_month');
        $this->assertTrue($lastMonth->to->lt(today()->startOfMonth()));
    }

    public function test_explicit_dates_win_and_a_backwards_range_is_tolerated(): void
    {
        $period = ReportPeriod::fromRequest(Request::create('/reports', 'GET', [
            'period' => 'today',
            'from' => today()->toDateString(),
            'to' => today()->subDays(6)->toDateString(),
        ]));

        $this->assertSame('custom', $period->key);
        $this->assertTrue($period->from->isSameDay(today()->subDays(6)));
        $this->assertTrue($period->to->isSameDay(today()));
    }

    public function test_the_day_by_day_breakdown_includes_days_with_no_sales(): void
    {
        $this->sale(1000, today()->subDays(3));
        $this->sale(500, today());

        $days = app(ReportService::class)->dailyBreakdown(
            today()->subDays(6)->startOfDay(),
            today()->endOfDay(),
        );

        $this->assertCount(7, $days);
        $this->assertSame(1000.0, $days->firstWhere('date.timestamp', today()->subDays(3)->startOfDay()->timestamp)->total);

        // A quiet day is a zero row, not a missing one.
        $quiet = $days->firstWhere('date.timestamp', today()->subDays(5)->startOfDay()->timestamp);
        $this->assertSame(0, $quiet->orders);
        $this->assertSame(0.0, $quiet->total);
    }

    public function test_the_reports_screen_offers_every_period(): void
    {
        $this->sale(2500);

        $response = $this->actingAs($this->admin())->get(route('reports.index', ['period' => 'week']));

        $response->assertOk()
            ->assertSee('This week')
            ->assertSee('Last month')
            ->assertSee('Day by day')
            ->assertSee('Download CSV');
    }

    public function test_the_plain_report_prints_the_same_figures(): void
    {
        $this->sale(2500);

        $this->actingAs($this->admin())->get(route('reports.print', ['period' => 'today']))
            ->assertOk()
            ->assertSee('Sales report')
            ->assertSee('Rs. 2,500.00')
            ->assertSee('completed orders only', false);
    }

    public function test_the_csv_downloads_with_the_figures_in_it(): void
    {
        $this->sale(2500);

        $response = $this->actingAs($this->admin())->get(route('reports.download', ['period' => 'today']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('.csv', $response->headers->get('content-disposition'));

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Sales report', $csv);
        $this->assertStringContainsString('Sales by order type', $csv);
        $this->assertStringContainsString('Sales by item', $csv);

        // Amounts are bare numbers so a spreadsheet can add them up — no
        // currency symbol, no thousands separator. (fputcsv quotes any field
        // containing a space, hence the quotes around the label.)
        $this->assertStringContainsString('"Total sales",2500.00', $csv);
        $this->assertStringNotContainsString('Rs. 2,500.00', $csv);
    }

    public function test_a_weekly_csv_lists_every_day(): void
    {
        $this->sale(1000, today()->subDays(2));

        $csv = $this->actingAs($this->admin())
            ->get(route('reports.download', ['period' => 'week']))
            ->streamedContent();

        $this->assertStringContainsString('Sales by day', $csv);
        $this->assertStringContainsString(today()->startOfWeek()->toDateString(), $csv);
    }

    /**
     * Menu item names are typed by staff. A name starting with = would run as
     * a formula when the CSV is opened in Excel.
     */
    public function test_the_csv_neutralises_spreadsheet_formulas(): void
    {
        $item = MenuItem::factory()->create([
            'name' => '=1+1+cmd|calc',
            'price' => 100,
            'category_id' => Category::factory(),
        ]);

        $this->sale(100, today(), $item);

        $csv = $this->actingAs($this->admin())
            ->get(route('reports.download', ['period' => 'today']))
            ->streamedContent();

        $this->assertStringContainsString("'=1+1+cmd|calc", $csv);
        $this->assertStringNotContainsString(',=1+1+cmd|calc', $csv);
    }

    public function test_reports_stay_behind_the_reports_permission(): void
    {
        $cashier = $this->cashier();

        $this->actingAs($cashier)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('reports.print'))->assertForbidden();
        $this->actingAs($cashier)->get(route('reports.download'))->assertForbidden();
    }

    /** Completes an order so it counts as a sale. */
    private function sale(float $amount, $when = null, ?MenuItem $item = null): void
    {
        $orders = app(OrderService::class);
        $user = $this->admin();

        $item ??= MenuItem::factory()->create(['price' => $amount]);

        $order = $orders->startTakeaway($user);
        $orders->addItem($order, $item);
        app(CheckoutService::class)->checkout($order, PaymentMethod::Cash, $user, $amount);

        if ($when) {
            // Reports read created_at, so backdate the whole order.
            $order->forceFill(['created_at' => $when, 'completed_at' => $when])->save();
        }
    }
}
