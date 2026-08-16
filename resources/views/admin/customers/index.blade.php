@php
    use App\Enums\CustomerSource;
    use App\Queries\CustomerFilters;
@endphp

<x-layouts.app title="Customers">
    <x-slot:header>Customers</x-slot:header>
    <x-slot:subheader>
        Everyone whose number the restaurant has taken, however they ordered
    </x-slot:subheader>

    <x-slot:actions>
        <x-btn :href="route('customers.create')">
            <x-icon name="plus"/>
            New customer
        </x-btn>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-stat label="Customers" :value="number_format($summary['customers'])" icon="users"/>
        <x-stat label="Have ordered" :value="number_format($summary['with_orders'])" icon="receipt" tone="success"/>
    </div>

    {{-- Filtering and paging both happen in the database, so this stays quick
         as the list grows into the thousands. --}}
    <x-card padded="false">
        <form method="GET" class="divide-y divide-slate-100 dark:divide-slate-800">
            <input type="hidden" name="sort" value="{{ $filters->sort }}">
            <input type="hidden" name="direction" value="{{ $filters->direction }}">
            <input type="hidden" name="per_page" value="{{ $filters->perPage }}">

            <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                <div class="flex-1">
                    <x-search-input name="search"
                                    :value="$filters->search"
                                    placeholder="Search by name or mobile number"/>
                </div>

                <div class="flex gap-2">
                    <x-btn size="md" class="flex-1 sm:flex-none">Search</x-btn>

                    @if ($filters->isFiltered())
                        <x-btn :href="route('customers.index')" variant="secondary" size="md" class="flex-1 sm:flex-none">
                            Reset
                        </x-btn>
                    @endif
                </div>
            </div>

            {{-- How the restaurant met them. --}}
            <div class="flex gap-2 overflow-x-auto no-scrollbar p-4">
                <a href="{{ request()->fullUrlWithQuery($filters->toQuery(['source' => null])) }}"
                   class="shrink-0 touch-target inline-flex items-center rounded-xl px-4 text-base font-semibold transition
                          {{ $filters->source === null
                              ? 'bg-brand-600 text-white shadow-sm'
                              : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700' }}">
                    All
                </a>

                @foreach (CustomerSource::cases() as $source)
                    <a href="{{ request()->fullUrlWithQuery($filters->toQuery(['source' => $source->value])) }}"
                       class="shrink-0 touch-target inline-flex items-center rounded-xl px-4 text-base font-semibold transition
                              {{ $filters->source === $source
                                  ? 'bg-brand-600 text-white shadow-sm'
                                  : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700' }}">
                        {{ $source->label() }}
                    </a>
                @endforeach
            </div>
        </form>
    </x-card>

    @if ($customers->isEmpty())
        <x-card class="text-center">
            <p class="text-lg text-slate-500 dark:text-slate-400">
                {{ $filters->isFiltered() ? 'No customers match these filters.' : 'No customers yet.' }}
            </p>
            <p class="mt-1 text-base text-slate-500 dark:text-slate-400">
                Customers are added here automatically whenever a mobile number is taken on an order.
            </p>
            <x-btn :href="route('customers.create')" class="mt-4">Add one manually</x-btn>
        </x-card>
    @else
        <x-card padded="false" class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-4xl border-collapse text-left">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            @include('orders.partials.sort-header', ['sort' => 'name', 'label' => 'Customer'])
                            <th scope="col" class="px-4 py-3 text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                Mobile
                            </th>
                            <th scope="col" class="px-4 py-3 text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                Source
                            </th>
                            @include('orders.partials.sort-header', ['sort' => 'orders', 'label' => 'Orders'])
                            @include('orders.partials.sort-header', ['sort' => 'last', 'label' => 'Last order'])
                            @include('orders.partials.sort-header', ['sort' => 'spent', 'label' => 'Spent', 'align' => 'right'])
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($customers as $customer)
                            <tr class="cursor-pointer transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                onclick="window.location='{{ route('customers.show', $customer) }}'">
                                <td class="px-4 py-4">
                                    <a href="{{ route('customers.show', $customer) }}"
                                       class="text-base font-bold text-slate-900 hover:text-brand-600 dark:text-white dark:hover:text-brand-400">
                                        {{ $customer->name ?: 'No name' }}
                                    </a>
                                    @if ($customer->note)
                                        <span class="block truncate text-sm text-slate-500 dark:text-slate-400">
                                            {{ $customer->note }}
                                        </span>
                                    @endif
                                </td>

                                <td class="px-4 py-4 font-mono text-base whitespace-nowrap text-slate-700 dark:text-slate-200">
                                    {{ $customer->phone }}
                                </td>

                                <td class="px-4 py-4">
                                    <x-badge :classes="$customer->source->badgeClasses()">
                                        {{ $customer->source->label() }}
                                    </x-badge>
                                </td>

                                <td class="px-4 py-4 tabular-nums text-slate-700 dark:text-slate-200">
                                    {{ number_format($customer->orders_count) }}
                                </td>

                                <td class="px-4 py-4 whitespace-nowrap text-slate-600 dark:text-slate-300">
                                    {{ $customer->last_order_at
                                        ? \Illuminate\Support\Carbon::parse($customer->last_order_at)->format('d M Y')
                                        : '—' }}
                                </td>

                                <td class="px-4 py-4 text-right text-base font-bold tabular-nums whitespace-nowrap text-slate-900 dark:text-white">
                                    {{ money($customer->orders_sum_total ?? 0) }}
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
                    @foreach (CustomerFilters::PER_PAGE as $size)
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
                    Showing {{ number_format($customers->firstItem()) }}–{{ number_format($customers->lastItem()) }}
                    of {{ number_format($customers->total()) }}
                </p>
            </div>
        </x-card>

        {{ $customers->onEachSide(1)->links() }}
    @endif
</x-layouts.app>
