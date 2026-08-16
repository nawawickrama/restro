@php
    $total = (float) $order->total;

    // Handy notes a cashier is likely to be handed, always ascending and
    // never duplicating the exact amount.
    $suggestions = collect([100, 500, 1000, 5000])
        ->map(fn ($step) => ceil($total / $step) * $step)
        ->push($total)
        ->unique()
        ->sort()
        ->values();
@endphp

<x-layouts.pos title="Checkout"
               :back="route('pos.orders.show', $order)"
               heading="Checkout"
               :subheading="$order->order_number.' · '.$order->type->label().' · '.$order->customerLabel()">

    <div class="h-full overflow-y-auto p-4 sm:p-6">
        <div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[1fr_24rem]">

            {{-- What is being paid for --}}
            <x-card padded="false" class="overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">Order summary</h2>
                </div>

                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($order->items as $item)
                        <li class="flex items-start justify-between gap-4 px-5 py-3">
                            <div class="min-w-0">
                                <p class="text-base font-semibold text-slate-900 dark:text-white">
                                    <span class="text-slate-500 dark:text-slate-400">{{ $item->quantity }}×</span>
                                    {{ $item->name }}
                                </p>
                                @if ($item->note)
                                    <p class="text-sm font-medium text-amber-700 dark:text-amber-400">{{ $item->note }}</p>
                                @endif
                            </div>
                            <p class="shrink-0 text-base font-bold text-slate-900 dark:text-white">
                                {{ money($item->line_total) }}
                            </p>
                        </li>
                    @endforeach
                </ul>

                <dl class="space-y-2 border-t border-slate-200 px-5 py-4 text-base dark:border-slate-800">
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
                            <dt>Tax ({{ rtrim(rtrim(number_format($settings->taxPercentage(), 2), '0'), '.') }}%)</dt>
                            <dd class="font-semibold">{{ money($order->tax_amount) }}</dd>
                        </div>
                    @endif

                    <div class="flex justify-between border-t border-slate-200 pt-3 text-3xl font-bold
                                text-slate-900 dark:border-slate-800 dark:text-white">
                        <dt>Total</dt>
                        <dd>{{ money($order->total) }}</dd>
                    </div>
                </dl>
            </x-card>

            {{-- Taking the money --}}
            <div class="space-y-4">
                <x-flash/>

                <x-card x-data="{
                            method: '{{ old('method', App\Enums\PaymentMethod::Cash->value) }}',
                            tendered: {{ old('tendered', $total) }},
                            total: {{ $total }},
                            get requiresTendered() {
                                return this.method === 'cash';
                            },
                            get change() {
                                return Math.max(0, this.tendered - this.total);
                            },
                            get sufficient() {
                                return !this.requiresTendered || this.tendered + 0.001 >= this.total;
                            },
                            money(amount) {
                                return `{{ $settings->currencySymbol() }} ${Number(amount).toLocaleString(undefined, {
                                    minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                            },

                            /* Mirror the payment as it is keyed in, so the
                               customer watches their change being worked out
                               rather than being told the result. */
                            mirror() {
                                window.showOnCustomerDisplay?.({
                                    screen: 'paying',
                                    payment: {
                                        method: this.method,
                                        total: this.money(this.total),
                                        tendered: this.money(this.tendered),
                                        change: this.money(this.change),
                                    },
                                });
                            },

                            init() {
                                this.mirror();
                                this.$watch('method', () => this.mirror());
                                this.$watch('tendered', () => this.mirror());
                            },
                        }">
                    <form action="{{ route('pos.orders.checkout.store', $order) }}" method="POST" class="space-y-5">
                        @csrf
                        <input type="hidden" name="method" :value="method">

                        <div>
                            <p class="mb-2 text-base font-semibold text-slate-700 dark:text-slate-200">Payment method</p>
                            <div class="grid grid-cols-2 gap-3">
                                @foreach ($methods as $paymentMethod)
                                    <button type="button"
                                            x-on:click="method = '{{ $paymentMethod->value }}'"
                                            :class="method === '{{ $paymentMethod->value }}'
                                                ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300'
                                                : 'border-slate-200 text-slate-700 hover:border-brand-300 dark:border-slate-700 dark:text-slate-200'"
                                            class="flex min-h-24 flex-col items-center justify-center gap-2 rounded-2xl
                                                   border-2 p-4 text-lg font-bold transition active:scale-[0.98]">
                                        <x-icon :name="$paymentMethod->icon()" class="size-8"/>
                                        {{ $paymentMethod->label() }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Cash: how much was handed over, and what goes back --}}
                        <div x-show="requiresTendered" x-cloak class="space-y-3">
                            <x-field label="Amount received" name="tendered">
                                <x-input name="tendered"
                                         type="number"
                                         size="lg"
                                         step="0.01"
                                         min="0"
                                         inputmode="decimal"
                                         x-model.number="tendered"/>
                            </x-field>

                            <div class="grid grid-cols-3 gap-2">
                                @foreach ($suggestions as $suggestion)
                                    <button type="button"
                                            x-on:click="tendered = {{ $suggestion }}"
                                            class="touch-target rounded-xl bg-slate-200 px-3 text-base font-bold
                                                   text-slate-700 transition active:scale-95 dark:bg-slate-800
                                                   dark:text-slate-200">
                                        {{ number_format($suggestion, 0) }}
                                    </button>
                                @endforeach
                            </div>

                            <div class="flex items-center justify-between rounded-2xl bg-emerald-50 px-5 py-4
                                        dark:bg-emerald-500/10">
                                <span class="text-lg font-semibold text-emerald-800 dark:text-emerald-300">Change</span>
                                <span class="text-3xl font-bold text-emerald-700 dark:text-emerald-400"
                                      x-text="money(change)"></span>
                            </div>

                            <p x-show="!sufficient"
                               x-cloak
                               class="text-base font-semibold text-rose-600 dark:text-rose-400">
                                That does not cover the total yet.
                            </p>
                        </div>

                        {{-- Card: an optional terminal reference --}}
                        <div x-show="!requiresTendered" x-cloak>
                            <x-field label="Reference" name="reference" hint="Optional. Terminal or approval number.">
                                <x-input name="reference" :value="old('reference')" autocomplete="off"/>
                            </x-field>
                        </div>

                        <x-btn variant="success" size="xl" class="w-full" x-bind:disabled="!sufficient">
                            <x-icon name="check"/>
                            Complete &amp; print receipt
                        </x-btn>
                    </form>
                </x-card>

                <x-btn :href="route('pos.orders.show', $order)" variant="secondary" size="lg" class="w-full">
                    Back to order
                </x-btn>
            </div>
        </div>
    </div>
</x-layouts.pos>
