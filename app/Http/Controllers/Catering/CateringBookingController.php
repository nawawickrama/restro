<?php

namespace App\Http\Controllers\Catering;

use App\Enums\CateringStatus;
use App\Enums\CateringUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\CateringBookingRequest;
use App\Models\CateringBooking;
use App\Models\CateringItem;
use App\Services\CateringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The catering diary.
 *
 * A month at a time, because that is how a kitchen thinks about the work
 * ahead: which Saturdays are taken, and how many people are being fed.
 */
class CateringBookingController extends Controller
{
    public function __construct(private readonly CateringService $catering) {}

    public function index(Request $request): View
    {
        $month = $this->month($request);

        // The grid runs from the Monday before the 1st to the Sunday after the
        // last, so weeks are always whole.
        $gridStart = $month->copy()->startOfMonth()->startOfWeek();
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek();

        $bookings = CateringBooking::query()
            ->between($gridStart, $gridEnd)
            ->with('items')
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->get();

        $byDate = $bookings->groupBy(fn (CateringBooking $b) => $b->event_date->toDateString());

        return view('catering.index', [
            'month' => $month,
            // The grid is built here rather than in Blade: a view that has to
            // walk a date range needs @php blocks, and Blade cannot have those
            // in the same file as the inline @php() form without its block
            // regex swallowing everything between them.
            'days' => $this->grid($gridStart, $gridEnd, $month, $byDate),
            'payload' => $this->popupPayload($bookings),
            'summary' => [
                'bookings' => $bookings->filter(fn ($b) => $b->status->isLive())->count(),
                'pax' => $bookings->filter(fn ($b) => $b->status->isLive())->sum('pax'),
                'value' => (float) $bookings->filter(fn ($b) => $b->status->isLive())->sum('total'),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return view('catering.form', [
            'booking' => new CateringBooking([
                // Clicking a day on the calendar opens the form on that day.
                'event_date' => $this->parseDate($request->string('date')->toString()) ?? today(),
                'status' => CateringStatus::Enquiry,
            ]),
            'catalogue' => $this->catalogue(),
            'units' => CateringUnit::cases(),
        ]);
    }

    public function store(CateringBookingRequest $request): RedirectResponse
    {
        $booking = $this->catering->create($request->validated(), $request->lines(), Auth::user());

        return redirect()->route('catering.index', ['month' => $booking->event_date->format('Y-m')])
            ->with('status', "Booking {$booking->reference} saved.");
    }

    public function edit(CateringBooking $cateringBooking): View
    {
        $cateringBooking->load('items');

        return view('catering.form', [
            'booking' => $cateringBooking,
            'catalogue' => $this->catalogue(),
            'units' => CateringUnit::cases(),
        ]);
    }

    public function update(CateringBookingRequest $request, CateringBooking $cateringBooking): RedirectResponse
    {
        $this->catering->update($cateringBooking, $request->validated(), $request->lines());

        return redirect()->route('catering.index', ['month' => $cateringBooking->event_date->format('Y-m')])
            ->with('status', "Booking {$cateringBooking->reference} updated.");
    }

    public function destroy(CateringBooking $cateringBooking): RedirectResponse
    {
        $reference = $cateringBooking->reference;
        $month = $cateringBooking->event_date->format('Y-m');

        $cateringBooking->delete();

        return redirect()->route('catering.index', ['month' => $month])
            ->with('status', "Booking {$reference} deleted.");
    }

    /** The printable sheet the kitchen works from. */
    public function show(CateringBooking $cateringBooking): View
    {
        $cateringBooking->load(['items', 'user']);

        return view('catering.sheet', ['booking' => $cateringBooking]);
    }

    /**
     * One entry per cell of the month grid, whole weeks included.
     *
     * @return list<array{date: Carbon, in_month: bool, is_today: bool, bookings: mixed}>
     */
    private function grid(Carbon $start, Carbon $end, Carbon $month, $byDate): array
    {
        $days = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $days[] = [
                'date' => $day->copy(),
                'in_month' => $day->isSameMonth($month),
                'is_today' => $day->isSameDay(today()),
                'bookings' => $byDate[$day->toDateString()] ?? collect(),
            ];
        }

        return $days;
    }

    private function month(Request $request): Carbon
    {
        $value = $request->string('month')->toString();

        try {
            return $value !== '' ? Carbon::createFromFormat('Y-m', $value)->startOfMonth() : today()->startOfMonth();
        } catch (\Throwable) {
            return today()->startOfMonth();
        }
    }

    private function parseDate(string $value): ?Carbon
    {
        try {
            return $value === '' ? null : Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function catalogue()
    {
        return CateringItem::query()->active()->ordered()->get();
    }

    /**
     * Every booking on the grid, in the shape the popup renders from.
     *
     * Sent with the page rather than fetched on click: a month holds tens of
     * bookings, not thousands, and a diary that opens instantly is worth more
     * than the few kilobytes.
     */
    private function popupPayload($bookings): array
    {
        return $bookings->mapWithKeys(fn (CateringBooking $booking) => [
            $booking->id => [
                'id' => $booking->id,
                'reference' => $booking->reference,
                'customer' => $booking->customerLabel(),
                'phone' => $booking->customer_phone,
                'pax' => $booking->pax,
                'date' => $booking->event_date->format('l, j F Y'),
                'time' => $booking->timeLabel(),
                'venue' => $booking->venue,
                'status' => $booking->status->label(),
                'status_classes' => $booking->status->badgeClasses(),
                'note' => $booking->note,
                'total' => money($booking->total),
                'has_total' => (float) $booking->total > 0,
                'edit_url' => route('catering.edit', $booking),
                'sheet_url' => route('catering.show', $booking),
                'items' => $booking->items->map(fn ($item) => [
                    'name' => $item->name,
                    'amount' => $item->amount(),
                    'note' => $item->note,
                    'line_total' => (float) $item->line_total > 0 ? money($item->line_total) : null,
                ])->values(),
            ],
        ])->all();
    }
}
