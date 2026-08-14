<x-layouts.app :title="$order->order_number">
    <x-slot:header>{{ $order->order_number }}</x-slot:header>
    <x-slot:subheader>
        {{ $order->created_at->format('d M Y, g:i A') }} · taken by {{ $order->user->name }}
    </x-slot:subheader>

    <x-slot:actions>
        <x-btn :href="route('orders.index')" variant="secondary">
            <x-icon name="arrow-left"/>
            All orders
        </x-btn>

        @can('printReceipt', $order)
            <x-btn :href="route('orders.receipt', $order)">
                <x-icon name="printer"/>
                Receipt
            </x-btn>
        @endcan

        @if ($order->isEditable())
            @can('update', $order)
                <x-btn :href="route('pos.orders.show', $order)" variant="success">Continue order</x-btn>
            @endcan
        @endif
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card padded="false" class="overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">Items</h2>
                </div>

                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($order->items as $item)
                        <li class="flex items-start justify-between gap-4 px-5 py-4">
                            <div class="min-w-0">
                                <p class="text-base font-semibold text-slate-900 dark:text-white">
                                    <span class="text-slate-500 dark:text-slate-400">{{ $item->quantity }}×</span>
                                    {{ $item->name }}
                                </p>
                                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                    {{ money($item->unit_price) }} each
                                </p>
                                @if ($item->note)
                                    <p class="text-sm font-medium text-amber-700 dark:text-amber-400">{{ $item->note }}</p>
                                @endif
                            </div>
                            <p class="shrink-0 text-lg font-bold text-slate-900 dark:text-white">
                                {{ money($item->line_total) }}
                            </p>
                        </li>
                    @endforeach
                </ul>

                <dl class="space-y-2 border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                    <div class="flex justify-between text-slate-600 dark:text-slate-300">
                        <dt>Subtotal</dt>
                        <dd class="font-semibold">{{ money($order->subtotal) }}</dd>
                    </div>

                    @if ((float) $order->discount_amount > 0)
                        <div class="flex justify-between text-slate-600 dark:text-slate-300">
                            <dt>Discount</dt>
                            <dd class="font-semibold text-emerald-600 dark:text-emerald-400">
                                - {{ money($order->discount_amount) }}
                            </dd>
                        </div>
                    @endif

                    @if ((float) $order->tax_amount > 0)
                        <div class="flex justify-between text-slate-600 dark:text-slate-300">
                            <dt>Tax</dt>
                            <dd class="font-semibold">{{ money($order->tax_amount) }}</dd>
                        </div>
                    @endif

                    <div class="flex justify-between border-t border-slate-200 pt-3 text-2xl font-bold
                                text-slate-900 dark:border-slate-800 dark:text-white">
                        <dt>Total</dt>
                        <dd>{{ money($order->total) }}</dd>
                    </div>
                </dl>
            </x-card>

            @if ($order->payments->isNotEmpty())
                <x-card class="space-y-4">
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">Payment</h2>

                    @foreach ($order->payments as $payment)
                        <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/50">
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-2 text-base font-semibold text-slate-800 dark:text-slate-100">
                                    <x-icon :name="$payment->method->icon()" class="size-5"/>
                                    {{ $payment->method->label() }}
                                </span>
                                <span class="text-xl font-bold text-slate-900 dark:text-white">
                                    {{ money($payment->amount) }}
                                </span>
                            </div>

                            <dl class="mt-3 space-y-1 text-sm text-slate-600 dark:text-slate-300">
                                @if ($payment->tendered !== null)
                                    <div class="flex justify-between">
                                        <dt>Received</dt>
                                        <dd>{{ money($payment->tendered) }}</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt>Change</dt>
                                        <dd>{{ money($payment->change_amount) }}</dd>
                                    </div>
                                @endif

                                @if ($payment->reference)
                                    <div class="flex justify-between">
                                        <dt>Reference</dt>
                                        <dd class="font-mono">{{ $payment->reference }}</dd>
                                    </div>
                                @endif

                                <div class="flex justify-between">
                                    <dt>Taken by</dt>
                                    <dd>{{ $payment->user->name }} · {{ $payment->paid_at->format('g:i A') }}</dd>
                                </div>
                            </dl>
                        </div>
                    @endforeach
                </x-card>
            @endif
        </div>

        <x-card class="h-fit space-y-4">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Details</h2>

            <dl class="space-y-3 text-base">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">Type</dt>
                    <dd><x-badge :classes="$order->type->badgeClasses()">{{ $order->type->label() }}</x-badge></dd>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">Status</dt>
                    <dd><x-badge :classes="$order->status->badgeClasses()">{{ $order->status->label() }}</x-badge></dd>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">Payment</dt>
                    <dd>
                        <x-badge :classes="$order->payment_status->badgeClasses()">
                            {{ $order->payment_status->label() }}
                        </x-badge>
                    </dd>
                </div>

                @if ($order->fulfillment_status)
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500 dark:text-slate-400">Collection</dt>
                        <dd>
                            <x-badge :classes="$order->fulfillment_status->badgeClasses()">
                                {{ $order->fulfillment_status->label() }}
                            </x-badge>
                        </dd>
                    </div>
                @endif

                @if ($order->table)
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500 dark:text-slate-400">Table</dt>
                        <dd class="font-semibold text-slate-900 dark:text-white">{{ $order->table->name }}</dd>
                    </div>
                @endif

                @if ($order->customer_name)
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500 dark:text-slate-400">Customer</dt>
                        <dd class="font-semibold text-slate-900 dark:text-white">{{ $order->customer_name }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500 dark:text-slate-400">Mobile</dt>
                        <dd class="font-semibold text-slate-900 dark:text-white">{{ $order->customer_phone }}</dd>
                    </div>
                @endif

                @if ($order->completed_at)
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500 dark:text-slate-400">Completed</dt>
                        <dd class="text-slate-900 dark:text-white">{{ $order->completed_at->format('g:i A') }}</dd>
                    </div>
                @endif
            </dl>

            @if ($order->note)
                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/50">
                    <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Note</p>
                    <p class="mt-1 whitespace-pre-line text-base text-slate-800 dark:text-slate-100">{{ $order->note }}</p>
                </div>
            @endif
        </x-card>
    </div>
</x-layouts.app>
