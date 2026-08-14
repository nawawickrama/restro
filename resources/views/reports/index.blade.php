@php
    use App\Support\ReportPeriod;

    // The bars are proportional to the busiest row, so a glance ranks them
    // without anyone having to read the numbers.
    $maxTypeTotal = max(1, collect($byType)->max('total'));
    $maxItemQuantity = max(1, $topItems->max('quantity') ?? 1);
    $maxDayTotal = max(1, $daily->max('total') ?? 1);
@endphp

<x-layouts.app title="Reports">
    <x-slot:header>Reports</x-slot:header>
    <x-slot:subheader>{{ $period->name() }} · {{ $period->label() }}</x-slot:subheader>

    <x-slot:actions>
        <x-btn :href="route('reports.print', $period->toQuery())" variant="secondary">
            <x-icon name="printer"/>
            Print
        </x-btn>

        <x-btn :href="route('reports.download', $period->toQuery())">
            <x-icon name="receipt"/>
            Download CSV
        </x-btn>
    </x-slot:actions>

    {{-- Day, week, month in one tap. Everything on the page follows. --}}
    <x-card padded="false">
        <div class="flex gap-2 overflow-x-auto no-scrollbar p-4">
            @foreach (ReportPeriod::PRESETS as $key => $label)
                <a href="{{ route('reports.index', ['period' => $key]) }}"
                   class="shrink-0 touch-target inline-flex items-center rounded-xl px-4 text-base font-semibold transition
                          {{ $period->key === $key
                              ? 'bg-brand-600 text-white shadow-sm'
                              : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700' }}">
                    {{ $label }}
                </a>
            @endforeach

            @if ($period->key === 'custom')
                <span class="shrink-0 touch-target inline-flex items-center rounded-xl bg-brand-600 px-4
                             text-base font-semibold text-white">
                    Custom
                </span>
            @endif
        </div>

        <form method="GET" class="flex flex-wrap items-end gap-3 border-t border-slate-100 p-4 dark:border-slate-800">
            <x-field label="From" name="from" class="min-w-40 flex-1">
                <x-input name="from" type="date" :value="$period->from->toDateString()"/>
            </x-field>

            <x-field label="To" name="to" class="min-w-40 flex-1">
                <x-input name="to" type="date" :value="$period->to->toDateString()"/>
            </x-field>

            <x-btn size="md">Show</x-btn>
        </form>
    </x-card>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Total sales" :value="money($summary['total'])" icon="chart" tone="brand"/>
        <x-stat label="Orders" :value="$summary['orders']" icon="receipt">
            <x-slot:footer>{{ money($summary['average']) }} average</x-slot:footer>
        </x-stat>
        <x-stat label="Cash" :value="money($summary['by_method']['cash'] ?? 0)" icon="banknotes" tone="success"/>
        <x-stat label="Card" :value="money($summary['by_method']['card'] ?? 0)" icon="credit-card"/>
    </div>

    <x-card class="space-y-5">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Sales by order type</h2>

        <div class="space-y-4">
            @foreach ($byType as $row)
                <div>
                    <div class="flex items-baseline justify-between gap-3">
                        <p class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ $row['label'] }}</p>
                        <p class="text-base font-bold text-slate-900 dark:text-white">
                            {{ money($row['total']) }}
                            <span class="ml-1 text-sm font-medium text-slate-500 dark:text-slate-400">
                                ({{ $row['orders'] }})
                            </span>
                        </p>
                    </div>

                    <div class="mt-2 h-3 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                        <div class="h-full rounded-full bg-brand-500"
                             style="width: {{ round($row['total'] / $maxTypeTotal * 100, 1) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-card>

    {{-- Over more than a day, the shape of the week is the useful part: one
         total hides which days carried it. --}}
    @if ($daily->isNotEmpty())
        <x-card padded="false">
            <div class="flex items-center justify-between px-5 py-4">
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">Day by day</h2>
                <span class="text-sm font-medium text-slate-500 dark:text-slate-400">
                    {{ $daily->count() }} days
                </span>
            </div>

            <div class="overflow-x-auto border-t border-slate-100 dark:border-slate-800">
                <table class="w-full min-w-lg text-left">
                    <thead class="bg-slate-50 dark:bg-slate-800/50">
                        <tr>
                            <th scope="col" class="px-5 py-2.5 text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">Date</th>
                            <th scope="col" class="px-5 py-2.5 text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">Orders</th>
                            <th scope="col" class="px-5 py-2.5 text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">Sales</th>
                            <th scope="col" class="w-2/5 px-5 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($daily as $day)
                            <tr class="{{ $day->date->isWeekend() ? 'bg-slate-50/60 dark:bg-slate-800/20' : '' }}">
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <span class="font-semibold text-slate-800 dark:text-slate-100">
                                        {{ $day->date->format('D j M') }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 tabular-nums text-slate-600 dark:text-slate-300">
                                    {{ $day->orders }}
                                </td>
                                <td class="px-5 py-3 font-bold tabular-nums whitespace-nowrap text-slate-900 dark:text-white">
                                    {{ money($day->total) }}
                                </td>
                                <td class="px-5 py-3">
                                    <div class="h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                                        <div class="h-full rounded-full bg-brand-500"
                                             style="width: {{ round($day->total / $maxDayTotal * 100, 1) }}%"></div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card class="space-y-4">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Best sellers</h2>

            @forelse ($topItems as $item)
                <div>
                    <div class="flex items-baseline justify-between gap-3">
                        <p class="truncate text-base font-semibold text-slate-800 dark:text-slate-100">
                            {{ $item->name }}
                        </p>
                        <p class="shrink-0 text-base font-bold text-slate-900 dark:text-white">
                            {{ $item->quantity }}
                            <span class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                · {{ money($item->total) }}
                            </span>
                        </p>
                    </div>
                    <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                        <div class="h-full rounded-full bg-emerald-500"
                             style="width: {{ round($item->quantity / $maxItemQuantity * 100, 1) }}%"></div>
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-base text-slate-500 dark:text-slate-400">
                    Nothing was sold in this period.
                </p>
            @endforelse
        </x-card>

        <x-card class="space-y-4">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Slowest movers</h2>

            @forelse ($slowItems as $item)
                <div class="flex items-baseline justify-between gap-3">
                    <p class="truncate text-base font-semibold text-slate-800 dark:text-slate-100">{{ $item->name }}</p>
                    <p class="shrink-0 text-base font-bold text-slate-900 dark:text-white">
                        {{ $item->quantity }}
                        <span class="text-sm font-medium text-slate-500 dark:text-slate-400">
                            · {{ money($item->total) }}
                        </span>
                    </p>
                </div>
            @empty
                <p class="py-6 text-center text-base text-slate-500 dark:text-slate-400">
                    Nothing was sold in this period.
                </p>
            @endforelse
        </x-card>
    </div>
</x-layouts.app>
