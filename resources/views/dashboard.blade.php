<x-layouts.app title="Dashboard">
    <x-slot:header>Today</x-slot:header>
    <x-slot:subheader>{{ now()->format('l, d F Y') }}</x-slot:subheader>

    <x-slot:actions>
        @can(\App\Support\Permissions::VIEW_POS)
            <x-btn :href="route('pos.home')" size="lg">
                <x-icon name="pos"/>
                Open POS
            </x-btn>
        @endcan
    </x-slot:actions>

    {{-- Money first, but only for staff allowed to see it. --}}
    @if ($summary)
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat label="Today's sales" :value="money($summary['total'])" icon="chart" tone="brand"/>
            <x-stat label="Orders" :value="$summary['orders']" icon="receipt"/>
            <x-stat label="Cash" :value="money($summary['by_method']['cash'] ?? 0)" icon="banknotes" tone="success"/>
            <x-stat label="Card" :value="money($summary['by_method']['card'] ?? 0)" icon="credit-card"/>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- What is happening in the room right now --}}
        <x-card class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">Occupied tables</h2>
                <x-badge classes="bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300">
                    {{ $occupiedTables->count() }}
                </x-badge>
            </div>

            @forelse ($occupiedTables as $table)
                <a href="{{ route('pos.orders.show', $table->activeOrder) }}"
                   class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-4 transition
                          hover:bg-slate-100 dark:bg-slate-800/50 dark:hover:bg-slate-800">
                    <div>
                        <p class="text-base font-bold text-slate-900 dark:text-white">{{ $table->name }}</p>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                            {{ $table->activeOrder->order_number }}
                            · {{ $table->activeOrder->created_at->diffForHumans(short: true) }}
                        </p>
                    </div>
                    <span class="text-lg font-bold text-slate-900 dark:text-white">
                        {{ money($table->activeOrder->total) }}
                    </span>
                </a>
            @empty
                <p class="py-6 text-center text-base text-slate-500 dark:text-slate-400">Every table is free.</p>
            @endforelse
        </x-card>

        <x-card class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">Open orders</h2>
                <x-badge classes="bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">
                    {{ $openOrders->count() }}
                </x-badge>
            </div>

            @forelse ($openOrders as $order)
                <a href="{{ route('pos.orders.show', $order) }}"
                   class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-4 transition
                          hover:bg-slate-100 dark:bg-slate-800/50 dark:hover:bg-slate-800">
                    <div class="min-w-0">
                        <p class="truncate text-base font-bold text-slate-900 dark:text-white">
                            {{ $order->order_number }}
                        </p>
                        <p class="truncate text-sm font-medium text-slate-500 dark:text-slate-400">
                            {{ $order->type->label() }} · {{ $order->customerLabel() }}
                        </p>
                    </div>
                    <span class="shrink-0 text-lg font-bold text-slate-900 dark:text-white">
                        {{ money($order->total) }}
                    </span>
                </a>
            @empty
                <p class="py-6 text-center text-base text-slate-500 dark:text-slate-400">Nothing open.</p>
            @endforelse
        </x-card>

        <x-card class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">Phone orders</h2>
                <x-badge classes="bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-300">
                    {{ $pendingPhoneOrders->count() }}
                </x-badge>
            </div>

            @forelse ($pendingPhoneOrders as $order)
                <a href="{{ route('pos.orders.show', $order) }}"
                   class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-4 transition
                          hover:bg-slate-100 dark:bg-slate-800/50 dark:hover:bg-slate-800">
                    <div class="min-w-0">
                        <p class="truncate text-base font-bold text-slate-900 dark:text-white">
                            {{ $order->customer_name }}
                        </p>
                        <p class="truncate text-sm font-medium text-slate-500 dark:text-slate-400">
                            {{ $order->customer_phone }}
                        </p>
                    </div>

                    @if ($order->fulfillment_status)
                        <x-badge :classes="$order->fulfillment_status->badgeClasses()">
                            {{ $order->fulfillment_status->label() }}
                        </x-badge>
                    @endif
                </a>
            @empty
                <p class="py-6 text-center text-base text-slate-500 dark:text-slate-400">No phone orders waiting.</p>
            @endforelse
        </x-card>
    </div>
</x-layouts.app>
