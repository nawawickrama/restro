<x-layouts.app :title="$customer->displayName()">
    <x-slot:header>{{ $customer->name ?: 'No name' }}</x-slot:header>
    <x-slot:subheader>
        {{ $customer->phone }} · {{ $customer->source->label() }} ·
        added {{ $customer->created_at->format('d M Y') }}
    </x-slot:subheader>

    <x-slot:actions>
        <x-btn :href="route('customers.index')" variant="secondary">
            <x-icon name="arrow-left"/>
            All customers
        </x-btn>

        <x-btn :href="route('customers.edit', $customer)">
            <x-icon name="pencil"/>
            Edit
        </x-btn>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat label="Orders" :value="number_format($stats['orders'])" icon="receipt"/>
        <x-stat label="Total spent" :value="money($stats['spent'])" icon="chart" tone="brand"/>
        <x-stat label="Last order"
                :value="$stats['last'] ? \Illuminate\Support\Carbon::parse($stats['last'])->format('d M Y') : '—'"
                icon="clock"/>
    </div>

    @if ($customer->note)
        <x-card>
            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Note</p>
            <p class="mt-1 whitespace-pre-line text-base text-slate-800 dark:text-slate-100">{{ $customer->note }}</p>
        </x-card>
    @endif

    <x-card padded="false" class="overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Recent orders</h2>

            @can(\App\Support\Permissions::VIEW_ORDERS)
                <x-btn :href="route('orders.index', ['range' => 'all', 'search' => $customer->phone])"
                       variant="secondary" size="sm">
                    See all in history
                </x-btn>
            @endcan
        </div>

        @if ($customer->orders->isEmpty())
            <p class="border-t border-slate-100 px-5 py-8 text-center text-base text-slate-500
                      dark:border-slate-800 dark:text-slate-400">
                This customer has not ordered yet.
            </p>
        @else
            <div class="overflow-x-auto border-t border-slate-100 dark:border-slate-800">
                <table class="w-full min-w-3xl border-collapse text-left">
                    <thead class="bg-slate-50 dark:bg-slate-800/50">
                        <tr>
                            @foreach (['Order', 'Date', 'Type', 'Status', 'Total'] as $heading)
                                <th scope="col"
                                    class="px-5 py-2.5 text-xs font-bold tracking-wider text-slate-500 uppercase
                                           dark:text-slate-400 {{ $heading === 'Total' ? 'text-right' : '' }}">
                                    {{ $heading }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($customer->orders as $order)
                            <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-5 py-3">
                                    <a href="{{ route('orders.show', $order) }}"
                                       class="font-mono font-bold text-slate-900 hover:text-brand-600 dark:text-white dark:hover:text-brand-400">
                                        {{ $order->order_number }}
                                    </a>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-slate-600 dark:text-slate-300">
                                    {{ $order->created_at->format('d M Y, g:i A') }}
                                </td>
                                <td class="px-5 py-3">
                                    <x-badge :classes="$order->type->badgeClasses()">{{ $order->type->label() }}</x-badge>
                                </td>
                                <td class="px-5 py-3">
                                    <x-badge :classes="$order->status->badgeClasses()">{{ $order->status->label() }}</x-badge>
                                </td>
                                <td class="px-5 py-3 text-right font-bold tabular-nums whitespace-nowrap text-slate-900 dark:text-white">
                                    {{ money($order->total) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <x-card class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-base font-semibold text-slate-800 dark:text-slate-100">Delete this customer</p>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Their orders are kept — each one holds the name and number typed at the time.
            </p>
        </div>

        <x-confirm-form :action="route('customers.destroy', $customer)"
                        method="DELETE"
                        title="Delete {{ $customer->displayName() }}?"
                        message="The customer record is removed. Their orders, receipts and figures are untouched."
                        confirm="Delete customer"
                        size="md">
            <x-slot:trigger>
                <x-icon name="trash" class="size-5"/>
                Delete
            </x-slot:trigger>
        </x-confirm-form>
    </x-card>
</x-layouts.app>
