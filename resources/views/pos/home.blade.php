<x-layouts.pos title="POS" :heading="$settings->restaurantName()" subheading="Choose how the customer is ordering">
    <x-slot:actions>
        @can(\App\Support\Permissions::VIEW_ORDERS)
            <x-btn :href="route('orders.index')" variant="secondary" size="md">
                <x-icon name="receipt"/>
                <span class="hidden sm:inline">Orders</span>
            </x-btn>
        @endcan

        <x-btn :href="route('dashboard')" variant="secondary" size="md">
            <x-icon name="home"/>
            <span class="hidden sm:inline">Dashboard</span>
        </x-btn>
    </x-slot:actions>

    {{-- No order is being served from this screen, so the customer's display
         returns to the welcome state. --}}
    <div x-init="window.showOnCustomerDisplay?.({ screen: 'idle' })"
         class="h-full overflow-y-auto p-4 sm:p-6">
        <div class="mx-auto max-w-6xl space-y-8">
            <div class="space-y-4">
                <x-flash/>
            </div>

            {{-- The three ways a sale can start. Nothing else competes for
                 attention at the top of this screen. --}}
            <div class="grid gap-4 sm:grid-cols-3">
                <a href="#tables"
                   class="flex touch-target flex-col items-center justify-center gap-3 rounded-3xl bg-brand-600 p-8
                          text-white shadow-lg transition hover:bg-brand-700 active:scale-[0.98]">
                    <x-icon name="table" class="size-12"/>
                    <span class="text-2xl font-bold tracking-wide">DINE IN</span>
                    <span class="text-sm font-medium text-brand-100">Pick a table below</span>
                </a>

                <form action="{{ route('pos.takeaway.store') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="flex w-full touch-target flex-col items-center justify-center gap-3 rounded-3xl
                                   bg-amber-500 p-8 text-white shadow-lg transition hover:bg-amber-600 active:scale-[0.98]">
                        <x-icon name="bag" class="size-12"/>
                        <span class="text-2xl font-bold tracking-wide">TAKEAWAY</span>
                        <span class="text-sm font-medium text-amber-50">Walk-in customer</span>
                    </button>
                </form>

                {{-- Straight into the order screen: the caller reads out their
                     food first, and their number is taken afterwards. --}}
                <form action="{{ route('pos.phone.store') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="flex w-full touch-target flex-col items-center justify-center gap-3 rounded-3xl
                                   bg-violet-600 p-8 text-white shadow-lg transition hover:bg-violet-700 active:scale-[0.98]">
                        <x-icon name="phone" class="size-12"/>
                        <span class="text-2xl font-bold tracking-wide">PHONE ORDER</span>
                        <span class="text-sm font-medium text-violet-100">Customer is calling</span>
                    </button>
                </form>
            </div>

            {{-- Tables. Free and busy have to be tellable apart across a room,
                 so they differ in colour, border and label, not just shade. --}}
            <section id="tables" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Dine in</h2>
                    <div class="flex items-center gap-4 text-sm font-semibold">
                        <span class="flex items-center gap-2 text-slate-500 dark:text-slate-400">
                            <span class="size-3 rounded-full bg-emerald-500"></span> Available
                        </span>
                        <span class="flex items-center gap-2 text-slate-500 dark:text-slate-400">
                            <span class="size-3 rounded-full bg-rose-500"></span> Occupied
                        </span>
                    </div>
                </div>

                @if ($tables->isEmpty())
                    <x-card class="text-center">
                        <p class="text-lg text-slate-500 dark:text-slate-400">No tables have been set up yet.</p>
                        @can(\App\Support\Permissions::MANAGE_TABLES)
                            <x-btn :href="route('tables.create')" class="mt-4">Add your first table</x-btn>
                        @endcan
                    </x-card>
                @else
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($tables as $table)
                            @php($order = $table->activeOrder)

                            <form action="{{ route('pos.tables.select', $table) }}" method="POST">
                                @csrf
                                <button type="submit"
                                        class="flex w-full flex-col items-start gap-2 rounded-3xl border-4 p-5 text-left
                                               shadow-sm transition active:scale-[0.98]
                                               {{ $order
                                                   ? 'border-rose-500 bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 dark:hover:bg-rose-500/20'
                                                   : 'border-emerald-500 bg-white hover:bg-emerald-50 dark:bg-slate-900 dark:hover:bg-emerald-500/10' }}">
                                    <span class="text-xl font-bold text-slate-900 sm:text-2xl dark:text-white">
                                        {{ $table->name }}
                                    </span>

                                    <span class="text-base font-bold uppercase tracking-wide
                                                 {{ $order ? 'text-rose-700 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400' }}">
                                        {{ $order ? 'Occupied' : 'Free' }}
                                    </span>

                                    @if ($order)
                                        <span class="text-sm font-medium text-slate-600 dark:text-slate-300">
                                            {{ (int) $order->items_count }} {{ Str::plural('item', (int) $order->items_count) }}
                                            · {{ money($order->total) }}
                                        </span>
                                        <span class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                            {{ $order->created_at->diffForHumans(short: true) }}
                                        </span>
                                    @elseif ($table->seats)
                                        <span class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                            {{ $table->seats }} seats
                                        </span>
                                    @endif
                                </button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- Takeaway and phone orders that are still waiting to be paid for.

                 The orders are already on the page, so the search filters them
                 here rather than at the server: with a customer waiting at the
                 counter, the list narrows on the third digit of their number
                 with nothing to wait for. --}}
            @if ($openOrders->isNotEmpty())
                <section x-data="openOrders({ orders: {{ Js::from($openOrdersPayload) }} })" class="space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">
                            Open takeaway &amp; phone orders
                        </h2>

                        <span class="text-base font-medium text-slate-500 dark:text-slate-400">
                            <span x-text="filtered.length"></span>
                            <template x-if="isSearching">
                                <span>of {{ $openOrders->count() }}</span>
                            </template>
                            showing
                        </span>
                    </div>

                    <x-search-input size="lg"
                                    x-model="search"
                                    x-ref="search"
                                    placeholder="Search by name, mobile or order number">
                        <button type="button"
                                x-show="isSearching"
                                x-cloak
                                x-on:click="clear()"
                                class="absolute top-1/2 right-3 flex size-10 -translate-y-1/2 items-center justify-center
                                       rounded-xl text-slate-500 transition hover:bg-slate-100 active:scale-95
                                       dark:hover:bg-slate-800"
                                aria-label="Clear search">
                            <x-icon name="x" class="size-5"/>
                        </button>
                    </x-search-input>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <template x-for="order in filtered" :key="order.id">
                            <a :href="order.url"
                               class="flex flex-col gap-3 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm
                                      transition hover:border-brand-400 hover:shadow-md active:scale-[0.98]
                                      dark:border-slate-800 dark:bg-slate-900">
                                <div class="flex items-start justify-between gap-3">
                                    <span class="text-lg font-bold text-slate-900 dark:text-white" x-text="order.number"></span>
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold whitespace-nowrap"
                                          :class="order.type_classes"
                                          x-text="order.type_label"></span>
                                </div>

                                <div class="text-base font-semibold text-slate-700 dark:text-slate-200">
                                    <span x-text="order.customer"></span>
                                    <template x-if="order.phone">
                                        <span class="block text-sm font-medium text-slate-500 dark:text-slate-400"
                                              x-text="order.phone"></span>
                                    </template>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-slate-500 dark:text-slate-400"
                                          x-text="order.items"></span>
                                    <span class="text-lg font-bold text-slate-900 dark:text-white"
                                          x-text="order.total"></span>
                                </div>

                                <template x-if="order.fulfillment_label">
                                    <span class="self-start inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold"
                                          :class="order.fulfillment_classes"
                                          x-text="order.fulfillment_label"></span>
                                </template>
                            </a>
                        </template>
                    </div>

                    {{-- Nothing open matches. The order they want may already be
                         paid for, so offer the full history rather than a dead end. --}}
                    <div x-show="isSearching && !hasResults"
                         x-cloak
                         class="rounded-3xl border border-slate-200 bg-white p-8 text-center dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-lg font-semibold text-slate-700 dark:text-slate-200">
                            No open order matches “<span x-text="search"></span>”.
                        </p>

                        @can(\App\Support\Permissions::VIEW_ORDERS)
                            <p class="mt-1 text-base text-slate-500 dark:text-slate-400">
                                It may already be completed.
                            </p>

                            <a :href="'{{ route('orders.index') }}?range=all&search=' + encodeURIComponent(search.trim())"
                               class="mt-5 inline-flex touch-target items-center gap-2 rounded-xl bg-brand-600 px-6
                                      text-base font-semibold text-white transition hover:bg-brand-700">
                                <x-icon name="search" class="size-5"/>
                                Search all orders
                            </a>
                        @endcan
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-layouts.pos>
