<?php

namespace Tests\Feature;

use App\Services\OrderNumberGenerator;
use App\Support\ReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The restaurant trades in Sri Lanka, so the application runs on its clock.
 *
 * Every date here comes from PHP rather than the database, which means this one
 * setting decides when "today" starts — for the daily order numbers, for the
 * reports, and for the catering diary. Left on UTC, a sale rung up at 3am
 * Colombo would be filed under the previous day, and the evening's takings
 * would land in tomorrow's figures.
 */
class TimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_runs_on_sri_lankan_time(): void
    {
        $this->assertSame('Asia/Colombo', config('app.timezone'));
        $this->assertSame('Asia/Colombo', date_default_timezone_get());

        // +5:30, and no daylight saving to move it.
        $this->assertSame(330, now()->utcOffset());
    }

    /** A day's orders are numbered by the local date, not the UTC one. */
    public function test_the_daily_order_number_follows_the_local_date(): void
    {
        // 20:00 UTC is already tomorrow in Colombo.
        Carbon::setTestNow(Carbon::parse('2026-08-26 20:00', 'UTC'));

        $this->assertSame('2026-08-27', today()->toDateString());
        $this->assertSame('ORD-20260827-0001', app(OrderNumberGenerator::class)->generate());

        Carbon::setTestNow();
    }

    /**
     * The evening trade belongs to the day it was earned. A restaurant closing
     * at 11pm must not see its last two hours in tomorrow's report.
     */
    public function test_an_evening_sale_falls_inside_the_same_local_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 12:00', 'Asia/Colombo'));

        $period = ReportPeriod::preset('today');
        $lateSale = Carbon::parse('2026-08-26 22:45', 'Asia/Colombo');

        $this->assertTrue(
            $lateSale->between($period->from, $period->to),
            'A 10:45pm sale should fall inside the same day’s report.',
        );

        Carbon::setTestNow();
    }

    /** The day runs midnight to midnight in Colombo, not in UTC. */
    public function test_the_reporting_day_starts_at_local_midnight(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 12:00', 'Asia/Colombo'));

        $period = ReportPeriod::preset('today');

        $this->assertSame('2026-08-26 00:00:00', $period->from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-25 18:30', $period->from->clone()->utc()->format('Y-m-d H:i'));

        Carbon::setTestNow();
    }
}
