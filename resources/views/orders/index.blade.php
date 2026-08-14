@php
    use App\Enums\OrderStatus;
    use App\Enums\OrderType;
    use App\Enums\PaymentStatus;
    use App\Queries\OrderHistoryFilters;
    use App\Support\Permissions;

    $ranges = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'week' => 'Last 7 days',
        'month' => 'This month',
        'all' => 'All time',
    ];
@endphp

<x-layouts.app title="Orders">
    <x-slot:header>Orders</x-slot:header>
    <x-slot:subheader>
        {{ number_format($orders->total()) }} {{ Str::plural('order', $orders->total()) }}
        @if ($filters->from)
            · {{ $filters->from->isSameDay($filters->to)
                ? $filters->from->format('d M Y')
                : $filters->from->format('d M Y').' – '.$filters->to->format('d M Y') }}
        @else
            · all time
        @endif
    </x-slot:subheader>

    <x-slot:actions>
        @can(Permissions::VIEW_POS)
            <x-btn :href="route('pos.home')">
                <x-icon name="pos"/>
                Open POS
            </x-btn>
        @endcan
    </x-slot:actions>

    {{-- Headline figures for exactly the rows below, not for all time. --}}
    @if ($summary)
        <div class="grid gap-4 sm:grid-cols-3">
            <x-stat label="Orders" :value="number_format($summary['orders'])" icon="receipt"/>
            <x-stat label="Completed" :value="number_format($summary['completed'])" icon="check" tone="success"/>
            <x-stat label="Revenue" :value="money($summary['revenue'])" icon="chart" tone="brand"/>
        </div>
    @endif

    {{-- One form for everything, so changing a filter never discards what is
         already in the search box or which dates are showing. --}}
    <x-card padded="false" x-data="{ open: {{ $filters->activeCount() > 0 || $filters->range === 'custom' ? 'true' : 'false' }} }">
        <form method="GET" class="divide-y divide-slate-100 dark:divide-slate-800">
            <input type="hidden" name="sort" value="{{ $filters->sort }}">
            <input type="hidden" name="direction" value="{{ $filters->direction }}">
            <input type="hidden" name="per_page" value="{{ $filters->perPage }}">

            {{-- Search and the way in to everything else. --}}
            <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                <div class="flex-1">
                    <x-search-input name="search"
                                    :value="$filters->search"
                                    placeholder="Search order number, mobile or customer name"/>
                </div>

                <div class="flex gap-2">
                    <x-btn name="range" value="{{ $filters->range }}" size="md" class="flex-1 sm:flex-none">
                        Search
                    </x-btn>

                    <x-btn type="button"
                           variant="secondary"
                           size="md"
                           x-on:click="open = !open"
                           x-bind:aria-expanded="open.toString()"
                           class="flex-1 sm:flex-none">
                        Filters
                        @if ($filters->activeCount())
                            <span class="ml-1 inline-flex size-6 items-center justify-center rounded-full
                                         bg-brand-600 text-sm font-bold text-white">
                                {{ $filters->activeCount() }}
                            </span>
                        @endif
                        <span x-text="open ? '▲' : '▼'" class="text-[0.6rem] leading-none opacity-60"></span>
                    </x-btn>
                </div>
            </div>

            {{-- Date presets. Each one is a submit button carrying its own range
                 value, so the search text and other filters survive the click. --}}
            <div class="flex gap-2 overflow-x-auto no-scrollbar p-4">
                @foreach ($ranges as $key => $label)
                    <button type="submit"
                            name="range"
                            value="{{ $key }}"
                            x-on:click="$refs.from.value = ''; $refs.to.value = ''"
                            class="shrink-0 touch-target rounded-xl px-4 text-base font-semibold transition
                                   active:scale-[0.98]
                                   {{ $filters->range === $key
                                       ? 'bg-brand-600 text-white shadow-sm'
                                       : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700' }}">
                        {{ $label }}
                    </button>
                @endforeach

                @if ($filters->range === 'custom')
                    <span class="shrink-0 touch-target inline-flex items-center rounded-xl bg-brand-600 px-4
                                 text-base font-semibold text-white">
                        Custom
                    </span>
                @endif
            </div>

            {{-- The rest, folded away until wanted. It opens by itself whenever
                 a filter is already on, so the list never looks narrowed for a
                 reason you cannot see. --}}
            <div x-show="open" x-cloak class="space-y-4 p-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-field label="From" name="from">
                        <x-input name="from" type="date" x-ref="from" :value="$filters->from?->toDateString()"/>
                    </x-field>

                    <x-field label="To" name="to">
                        <x-input name="to" type="date" x-ref="to" :value="$filters->to?->toDateString()"/>
                    </x-field>

                    <x-field label="Order type" name="type">
                        <x-select name="type">
                            <option value="">All types</option>
                            @foreach (OrderType::cases() as $type)
                                <option value="{{ $type->value }}" @selected($filters->type === $type)>
                                    {{ $type->label() }}
                                </option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <x-field label="Status" name="status">
                        <x-select name="status">
                            <option value="">All statuses</option>
                            @foreach (OrderStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected($filters->status === $status)>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <x-field label="Payment" name="payment_status">
                        <x-select name="payment_status">
                            <option value="">Paid and unpaid</option>
                            @foreach (PaymentStatus::cases() as $paymentStatus)
                                <option value="{{ $paymentStatus->value }}" @selected($filters->paymentStatus === $paymentStatus)>
                                    {{ $paymentStatus->label() }}
                                </option>
                            @endforeach
                        </x-select>
                    </x-field>
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-btn name="range" value="{{ $filters->range }}" size="md">Apply filters</x-btn>

                    @if ($filters->isFiltered())
                        <x-btn :href="route('orders.index')" variant="secondary" size="md">Clear all</x-btn>
                    @endif
                </div>
            </div>
        </form>

        {{-- What is currently narrowing the list, and a way to undo any one of
             them without starting over. --}}
        @if ($filters->activeChips())
            <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 px-4 py-3 dark:border-slate-800">
                <span class="text-sm font-semibold text-slate-500 dark:text-slate-400">Filtered by</span>

                @foreach ($filters->activeChips() as $key => $label)
                    <a href="{{ request()->fullUrlWithQuery($filters->toQuery([$key => null])) }}"
                       class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 py-1.5 pr-2 pl-3 text-sm
                              font-semibold text-brand-700 transition hover:bg-brand-100
                              dark:bg-brand-500/15 dark:text-brand-300 dark:hover:bg-brand-500/25">
                        {{ $label }}
                        <x-icon name="x" class="size-4"/>
                        <span class="sr-only">Remove this filter</span>
                    </a>
                @endforeach
            </div>
        @endif
    </x-card>

    @if ($orders->isEmpty())
        <x-card class="text-center">
            <p class="text-lg text-slate-500 dark:text-slate-400">No orders match these filters.</p>
            <x-btn :href="route('orders.index')" variant="secondary" class="mt-4">Reset filters</x-btn>
        </x-card>
    @else
        <x-card padded="false" class="overflow-hidden">
            {{-- Rows stay tall enough to tap; the table scrolls sideways on a
                 phone rather than crushing the columns together. --}}
            <div class="overflow-x-auto">
                <table class="w-full min-w-4xl border-collapse text-left">
                    <thead class="group border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            @include('orders.partials.sort-header', ['sort' => 'number', 'label' => 'Order'])
                            @include('orders.partials.sort-header', ['sort' => 'date', 'label' => 'Date & time'])
                            <th scope="col" class="px-4 py-3 text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                Type
                            </th>
                            <th scope="col" class="px-4 py-3 text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                Table / customer
                            </th>
                            <th scope="col" class="px-4 py-3 text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                Status
                            </th>
                            <th scope="col" class="px-4 py-3 text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                Payment
                            </th>
                            @include('orders.partials.sort-header', ['sort' => 'total', 'label' => 'Total', 'align' => 'right'])
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($orders as $order)
                            <tr class="cursor-pointer transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                onclick="window.location='{{ route('orders.show', $order) }}'">
                                <td class="px-4 py-4">
                                    <a href="{{ route('orders.show', $order) }}"
                                       class="font-mono text-base font-bold text-slate-900 hover:text-brand-600 dark:text-white dark:hover:text-brand-400">
                                        {{ $order->order_number }}
                                    </a>
                                </td>

                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="block text-base font-medium text-slate-800 dark:text-slate-100">
                                        {{ $order->created_at->format('d M Y') }}
                                    </span>
                                    <span class="block text-sm text-slate-500 dark:text-slate-400">
                                        {{ $order->created_at->format('g:i A') }} · {{ $order->user->name }}
                                    </span>
                                </td>

                                <td class="px-4 py-4">
                                    <x-badge :classes="$order->type->badgeClasses()">{{ $order->type->label() }}</x-badge>
                                </td>

                                <td class="max-w-48 truncate px-4 py-4 text-base text-slate-800 dark:text-slate-100">
                                    {{ $order->customerLabel() }}
                                </td>

                                <td class="px-4 py-4">
                                    <x-badge :classes="$order->status->badgeClasses()">{{ $order->status->label() }}</x-badge>
                                </td>

                                <td class="px-4 py-4">
                                    <x-badge :classes="$order->payment_status->badgeClasses()">
                                        {{ $order->payment_status->label() }}
                                    </x-badge>
                                </td>

                                <td class="px-4 py-4 text-right text-lg font-bold whitespace-nowrap text-slate-900 tabular-nums dark:text-white">
                                    {{ money($order->total) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 px-4 py-3
                        dark:border-slate-800">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-slate-500 dark:text-slate-400">Rows</span>
                    @foreach (OrderHistoryFilters::PER_PAGE as $size)
                        <a href="{{ request()->fullUrlWithQuery($filters->toQuery(['per_page' => $size])) }}"
                           class="rounded-lg px-3 py-1.5 text-sm font-bold transition
                                  {{ $filters->perPage === $size
                                      ? 'bg-brand-600 text-white'
                                      : 'bg-slate-200 text-slate-700 hover:bg-slate-300 dark:bg-slate-800 dark:text-slate-200' }}">
                            {{ $size }}
                        </a>
                    @endforeach
                </div>

                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                    Showing {{ number_format($orders->firstItem()) }}–{{ number_format($orders->lastItem()) }}
                    of {{ number_format($orders->total()) }}
                </p>
            </div>
        </x-card>

        {{ $orders->onEachSide(1)->links() }}
    @endif
</x-layouts.app>
