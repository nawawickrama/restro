<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('layouts.partials.head', ['title' => 'Sales report'])
</head>
<body class="min-h-full bg-slate-200 py-6 dark:bg-slate-950">

{{-- Screen-only controls, on one row at every width. --}}
<div class="no-print mx-auto mb-6 flex max-w-3xl items-stretch gap-2 px-4">
    <x-btn :href="route('reports.index', $period->toQuery())" variant="secondary" size="md"
           class="flex-1 whitespace-nowrap px-3">
        <x-icon name="arrow-left" class="size-5 shrink-0"/>
        <span class="truncate">Back</span>
    </x-btn>

    <x-btn :href="route('reports.download', $period->toQuery())" variant="secondary" size="md"
           class="flex-1 whitespace-nowrap px-3">
        <x-icon name="receipt" class="size-5 shrink-0"/>
        <span class="truncate">CSV</span>
    </x-btn>

    <x-btn type="button" size="md" class="flex-1 whitespace-nowrap px-3" onclick="window.print()">
        <x-icon name="printer" class="size-5 shrink-0"/>
        <span class="truncate">Print</span>
    </x-btn>
</div>

{{-- The report itself: black on white, no bars, no colour. Made to be printed,
     filed, or handed to whoever does the books. --}}
<div class="report-sheet mx-auto max-w-3xl bg-white p-8 text-slate-900 shadow-lg print:shadow-none">

    <header class="border-b-2 border-slate-900 pb-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold uppercase">{{ $settings->restaurantName() }}</h1>
                @if ($settings->get('restaurant_address'))
                    <p class="text-sm">{{ $settings->get('restaurant_address') }}</p>
                @endif
                @if ($settings->get('restaurant_phone'))
                    <p class="text-sm">Tel: {{ $settings->get('restaurant_phone') }}</p>
                @endif
            </div>

            <div class="text-right text-sm">
                <p class="text-lg font-bold">Sales report</p>
                <p class="font-semibold">{{ $period->name() }}</p>
                <p>{{ $period->label() }}</p>
            </div>
        </div>
    </header>

    {{-- The four numbers that matter, stated plainly. --}}
    <section class="mt-6">
        <h2 class="border-b border-slate-300 pb-1 text-sm font-bold tracking-wider uppercase">Summary</h2>

        <table class="mt-3 w-full text-base">
            <tbody>
                <tr>
                    <td class="py-1.5">Total sales</td>
                    <td class="py-1.5 text-right text-xl font-bold tabular-nums">{{ money($summary['total']) }}</td>
                </tr>
                <tr class="border-t border-slate-200">
                    <td class="py-1.5">Orders completed</td>
                    <td class="py-1.5 text-right font-semibold tabular-nums">{{ number_format($summary['orders']) }}</td>
                </tr>
                <tr class="border-t border-slate-200">
                    <td class="py-1.5">Average order</td>
                    <td class="py-1.5 text-right font-semibold tabular-nums">{{ money($summary['average']) }}</td>
                </tr>
                @foreach ($summary['by_method'] as $method => $amount)
                    <tr class="border-t border-slate-200">
                        <td class="py-1.5">{{ ucfirst($method) }}</td>
                        <td class="py-1.5 text-right font-semibold tabular-nums">{{ money($amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <section class="mt-6">
        <h2 class="border-b border-slate-300 pb-1 text-sm font-bold tracking-wider uppercase">Sales by order type</h2>

        <table class="mt-3 w-full text-base">
            <thead>
                <tr class="border-b border-slate-200 text-left text-sm">
                    <th class="py-1.5 font-semibold">Type</th>
                    <th class="py-1.5 text-right font-semibold">Orders</th>
                    <th class="py-1.5 text-right font-semibold">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($byType as $row)
                    <tr class="border-b border-slate-100">
                        <td class="py-1.5">{{ $row['label'] }}</td>
                        <td class="py-1.5 text-right tabular-nums">{{ number_format($row['orders']) }}</td>
                        <td class="py-1.5 text-right font-semibold tabular-nums">{{ money($row['total']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-slate-900">
                    <td class="py-2 font-bold">Total</td>
                    <td class="py-2 text-right font-bold tabular-nums">{{ number_format($summary['orders']) }}</td>
                    <td class="py-2 text-right font-bold tabular-nums">{{ money($summary['total']) }}</td>
                </tr>
            </tfoot>
        </table>
    </section>

    @if ($daily->isNotEmpty())
        <section class="mt-6 break-inside-avoid">
            <h2 class="border-b border-slate-300 pb-1 text-sm font-bold tracking-wider uppercase">Day by day</h2>

            <table class="mt-3 w-full text-base">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-sm">
                        <th class="py-1.5 font-semibold">Date</th>
                        <th class="py-1.5 text-right font-semibold">Orders</th>
                        <th class="py-1.5 text-right font-semibold">Sales</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($daily as $day)
                        <tr class="border-b border-slate-100">
                            <td class="py-1.5">{{ $day->date->format('D, j M Y') }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ number_format($day->orders) }}</td>
                            <td class="py-1.5 text-right font-semibold tabular-nums">{{ money($day->total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-slate-900">
                        <td class="py-2 font-bold">Total</td>
                        <td class="py-2 text-right font-bold tabular-nums">{{ number_format($daily->sum('orders')) }}</td>
                        <td class="py-2 text-right font-bold tabular-nums">{{ money($daily->sum('total')) }}</td>
                    </tr>
                </tfoot>
            </table>
        </section>
    @endif

    <section class="mt-6 break-inside-avoid">
        <h2 class="border-b border-slate-300 pb-1 text-sm font-bold tracking-wider uppercase">Sales by item</h2>

        @if ($topItems->isEmpty())
            <p class="mt-3 text-base">Nothing was sold in this period.</p>
        @else
            <table class="mt-3 w-full text-base">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-sm">
                        <th class="py-1.5 font-semibold">Item</th>
                        <th class="py-1.5 text-right font-semibold">Qty</th>
                        <th class="py-1.5 text-right font-semibold">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($topItems as $item)
                        <tr class="border-b border-slate-100">
                            <td class="py-1.5">{{ $item->name }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ number_format($item->quantity) }}</td>
                            <td class="py-1.5 text-right font-semibold tabular-nums">{{ money($item->total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <footer class="mt-8 border-t border-slate-300 pt-3 text-xs text-slate-600">
        <p>
            Generated {{ now()->format('d M Y, g:i A') }} by {{ auth()->user()->name }}.
            Figures cover completed orders only; cancelled and open orders are excluded.
        </p>
    </footer>
</div>

@if ($autoPrint)
    <script>
        window.addEventListener('load', () => window.print());
    </script>
@endif
</body>
</html>
