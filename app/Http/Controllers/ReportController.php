<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Services\SettingsService;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports come in three shapes over the same figures: a screen to look at, a
 * plain sheet to print or hand to an accountant, and a CSV to open in a
 * spreadsheet. All three read the same period object and the same service, so
 * they can never disagree.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): View
    {
        $period = ReportPeriod::fromRequest($request);

        return view('reports.index', $this->data($period));
    }

    /** The plain version: no bars, no colour, made to come out of a printer. */
    public function print(Request $request): View
    {
        $period = ReportPeriod::fromRequest($request);

        return view('reports.plain', $this->data($period) + [
            'autoPrint' => $request->boolean('print'),
        ]);
    }

    /**
     * The same report as a spreadsheet.
     *
     * Streamed rather than built in memory, so a long custom range cannot
     * exhaust PHP's memory limit on a modest server.
     */
    public function download(Request $request): StreamedResponse
    {
        $period = ReportPeriod::fromRequest($request);
        $data = $this->data($period);

        return response()->streamDownload(function () use ($data, $period) {
            $out = fopen('php://output', 'w');

            // A BOM, so Excel opens UTF-8 names correctly instead of mangling them.
            fwrite($out, "\xEF\xBB\xBF");

            $this->writeCsv($out, $data, $period);

            fclose($out);
        }, $period->filename($this->settings->restaurantName()), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return array<string, mixed> */
    private function data(ReportPeriod $period): array
    {
        return [
            'period' => $period,
            'summary' => $this->reports->salesSummary($period->from, $period->to),
            'byType' => $this->reports->salesByOrderType($period->from, $period->to),
            'topItems' => $this->reports->salesByItem($period->from, $period->to, 'desc'),
            'slowItems' => $this->reports->salesByItem($period->from, $period->to, 'asc'),

            // A day-by-day table only says something over more than one day.
            'daily' => $period->days() > 1
                ? $this->reports->dailyBreakdown($period->from, $period->to)
                : collect(),
        ];
    }

    /**
     * @param  resource  $out
     * @param  array<string, mixed>  $data
     */
    private function writeCsv($out, array $data, ReportPeriod $period): void
    {
        $put = function (array $row) use ($out) {
            fputcsv($out, array_map($this->safeCell(...), $row));
        };

        $put(['Sales report', $this->settings->restaurantName()]);
        $put(['Period', $period->name()]);
        $put(['From', $period->from->toDateString()]);
        $put(['To', $period->to->toDateString()]);
        $put(['Generated', now()->format('Y-m-d H:i')]);
        $put([]);

        // Amounts are written as bare numbers, with no currency symbol or
        // thousands separator, so a spreadsheet can add them up.
        $put(['Summary']);
        $put(['Total sales', number_format($data['summary']['total'], 2, '.', '')]);
        $put(['Orders', $data['summary']['orders']]);
        $put(['Average order', number_format($data['summary']['average'], 2, '.', '')]);

        foreach ($data['summary']['by_method'] as $method => $amount) {
            $put([ucfirst($method), number_format($amount, 2, '.', '')]);
        }

        $put([]);
        $put(['Sales by order type']);
        $put(['Type', 'Orders', 'Total']);

        foreach ($data['byType'] as $row) {
            $put([$row['label'], $row['orders'], number_format($row['total'], 2, '.', '')]);
        }

        if ($data['daily']->isNotEmpty()) {
            $put([]);
            $put(['Sales by day']);
            $put(['Date', 'Day', 'Orders', 'Total']);

            foreach ($data['daily'] as $day) {
                $put([
                    $day->date->toDateString(),
                    $day->date->format('D'),
                    $day->orders,
                    number_format($day->total, 2, '.', ''),
                ]);
            }
        }

        $put([]);
        $put(['Sales by item']);
        $put(['Item', 'Quantity', 'Total']);

        foreach ($data['topItems'] as $item) {
            $put([$item->name, $item->quantity, number_format($item->total, 2, '.', '')]);
        }
    }

    /**
     * Neutralise spreadsheet formula injection.
     *
     * Menu item names are typed by staff. A name beginning =, +, - or @ is
     * executed as a formula when the CSV is opened, so it is prefixed with an
     * apostrophe to force it to be read as text.
     */
    private function safeCell(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
