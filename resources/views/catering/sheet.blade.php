<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    @include('layouts.partials.head', ['title' => 'Sheet '.$booking->reference])
</head>
<body class="min-h-full bg-slate-200 py-6 dark:bg-slate-950">

{{-- Screen-only controls, on one row. Everything below prints. --}}
<div class="no-print mx-auto mb-6 flex max-w-3xl items-stretch gap-2 px-4">
    <x-btn :href="route('catering.index', ['month' => $booking->event_date->format('Y-m')])"
           variant="secondary" size="md" class="flex-1 whitespace-nowrap px-3">
        <x-icon name="arrow-left" class="size-5 shrink-0"/>
        <span class="truncate">Diary</span>
    </x-btn>

    <x-btn :href="route('catering.edit', $booking)" variant="secondary" size="md"
           class="flex-1 whitespace-nowrap px-3">
        <x-icon name="pencil" class="size-5 shrink-0"/>
        <span class="truncate">Edit</span>
    </x-btn>

    <x-btn type="button" size="md" class="flex-1 whitespace-nowrap px-3" onclick="window.print()">
        <x-icon name="printer" class="size-5 shrink-0"/>
        <span class="truncate">Print</span>
    </x-btn>
</div>

{{-- The sheet the kitchen actually works from: what to cook, how much, for
     when. Printed on ordinary paper rather than the till roll. --}}
<div class="report-sheet mx-auto max-w-3xl bg-white p-8 text-slate-900 shadow-lg print:shadow-none">
    <header class="flex flex-wrap items-start justify-between gap-4 border-b-2 border-slate-900 pb-4">
        <div>
            <h1 class="text-2xl font-bold uppercase">{{ $settings->restaurantName() }}</h1>
            <p class="text-sm">{{ $settings->get('restaurant_address') }}</p>
            <p class="text-sm">Tel: {{ $settings->get('restaurant_phone') }}</p>
        </div>

        <div class="text-right">
            <p class="text-lg font-bold">Catering sheet</p>
            <p class="font-mono text-sm">{{ $booking->reference }}</p>
            <p class="text-sm font-semibold">{{ $booking->status->label() }}</p>
        </div>
    </header>

    <section class="mt-6 grid grid-cols-2 gap-x-8 gap-y-3 text-base sm:grid-cols-3">
        <div>
            <p class="text-xs font-bold tracking-wider uppercase text-slate-600">Date</p>
            <p class="mt-0.5 font-semibold">{{ $booking->event_date->format('l, j F Y') }}</p>
        </div>

        @if ($booking->timeLabel())
            <div>
                <p class="text-xs font-bold tracking-wider uppercase text-slate-600">Time</p>
                <p class="mt-0.5 font-semibold">{{ $booking->timeLabel() }}</p>
            </div>
        @endif

        @if ($booking->pax)
            <div>
                <p class="text-xs font-bold tracking-wider uppercase text-slate-600">Guests</p>
                <p class="mt-0.5 font-semibold">{{ number_format($booking->pax) }}</p>
            </div>
        @endif

        <div>
            <p class="text-xs font-bold tracking-wider uppercase text-slate-600">Customer</p>
            <p class="mt-0.5 font-semibold">{{ $booking->customerLabel() }}</p>
        </div>

        @if ($booking->customer_phone)
            <div>
                <p class="text-xs font-bold tracking-wider uppercase text-slate-600">Mobile</p>
                <p class="mt-0.5 font-mono font-semibold">{{ $booking->customer_phone }}</p>
            </div>
        @endif

        @if ($booking->venue)
            <div class="col-span-2 sm:col-span-3">
                <p class="text-xs font-bold tracking-wider uppercase text-slate-600">Venue</p>
                <p class="mt-0.5 font-semibold">{{ $booking->venue }}</p>
            </div>
        @endif
    </section>

    <section class="mt-8">
        <h2 class="border-b border-slate-300 pb-1 text-sm font-bold tracking-wider uppercase">To cook</h2>

        @if ($booking->items->isEmpty())
            <p class="mt-4 text-base">Nothing has been put on this sheet yet.</p>
        @else
            <table class="mt-4 w-full text-base">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-sm">
                        <th class="py-1.5 font-semibold">Dish</th>
                        <th class="py-1.5 text-right font-semibold">Quantity</th>
                        <th class="py-1.5 text-right font-semibold">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($booking->items as $line)
                        <tr class="border-b border-slate-100 align-top">
                            <td class="py-2">
                                {{ $line->name }}
                                @if ($line->note)
                                    <span class="block text-sm italic text-slate-600">{{ $line->note }}</span>
                                @endif
                            </td>
                            <td class="py-2 text-right font-semibold tabular-nums whitespace-nowrap">
                                {{ $line->amount() }}
                            </td>
                            <td class="py-2 text-right tabular-nums whitespace-nowrap">
                                {{ (float) $line->line_total > 0 ? money($line->line_total) : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>

                @if ((float) $booking->total > 0)
                    <tfoot>
                        <tr class="border-t-2 border-slate-900">
                            <td class="py-2 font-bold" colspan="2">Total</td>
                            <td class="py-2 text-right font-bold tabular-nums">{{ money($booking->total) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        @endif
    </section>

    @if ($booking->note)
        <section class="mt-8 break-inside-avoid">
            <h2 class="border-b border-slate-300 pb-1 text-sm font-bold tracking-wider uppercase">Notes</h2>
            <p class="mt-3 whitespace-pre-line text-base">{{ $booking->note }}</p>
        </section>
    @endif

    <footer class="mt-10 border-t border-slate-300 pt-3 text-xs text-slate-600">
        Booked by {{ $booking->user->name }} on {{ $booking->created_at->format('d M Y') }}.
        Printed {{ now()->format('d M Y, g:i A') }}.
    </footer>
</div>
</body>
</html>
