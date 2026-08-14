{{-- Phone orders only.

     The details are taken after the food, so this panel starts as a prompt and
     becomes a summary once the caller has given their number. The number is
     required; the name is not, because plenty of callers never offer one. --}}

<div x-data="{ open: false }">
    @if ($order->needsCustomerPhone())
        <button type="button"
                x-on:click="open = true"
                class="flex w-full touch-target items-center gap-3 rounded-2xl border-2 border-dashed
                       border-amber-400 bg-amber-50 px-4 py-3 text-left transition hover:bg-amber-100
                       dark:border-amber-500/50 dark:bg-amber-500/10 dark:hover:bg-amber-500/20">
            <x-icon name="phone" class="size-6 shrink-0 text-amber-600 dark:text-amber-400"/>
            <span class="min-w-0">
                <span class="block text-base font-bold text-amber-900 dark:text-amber-200">
                    Add mobile number
                </span>
                <span class="block text-sm font-medium text-amber-700 dark:text-amber-300">
                    Needed before checkout
                </span>
            </span>
        </button>
    @else
        <button type="button"
                x-on:click="open = true"
                class="flex w-full touch-target items-center gap-3 rounded-2xl border border-slate-200
                       bg-slate-50 px-4 py-3 text-left transition hover:bg-slate-100
                       dark:border-slate-700 dark:bg-slate-800/60 dark:hover:bg-slate-800">
            <x-icon name="phone" class="size-6 shrink-0 text-slate-500 dark:text-slate-400"/>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-base font-bold text-slate-900 dark:text-white">
                    {{ $order->customer_phone }}
                </span>
                <span class="block truncate text-sm font-medium text-slate-500 dark:text-slate-400">
                    {{ $order->customer_name ?: 'No name given' }}
                </span>
            </span>
            <x-icon name="pencil" class="size-5 shrink-0 text-slate-400"/>
        </button>
    @endif

    <template x-teleport="body">
        <div x-show="open"
             x-cloak
             x-on:keydown.escape.window="open = false"
             class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/60 p-4 backdrop-blur-sm sm:items-center"
             role="dialog"
             aria-modal="true">
            <div x-on:click.outside="open = false"
                 class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl dark:bg-slate-900">
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Customer details</h2>
                <p class="mt-1 text-base text-slate-500 dark:text-slate-400">
                    The mobile number is how the counter finds them on collection.
                </p>

                <form action="{{ route('pos.orders.customer', $order) }}" method="POST" class="mt-4 space-y-4">
                    @csrf

                    <x-field label="Mobile number" name="customer_phone" required>
                        <x-input name="customer_phone"
                                 type="tel"
                                 inputmode="tel"
                                 :value="old('customer_phone', $order->customer_phone)"
                                 autocomplete="off"
                                 x-ref="phone"
                                 required/>
                    </x-field>

                    <x-field label="Customer name" name="customer_name" hint="Optional.">
                        <x-input name="customer_name"
                                 :value="old('customer_name', $order->customer_name)"
                                 autocomplete="off"/>
                    </x-field>

                    <x-field label="Note" name="note" hint="Optional. Collection time, directions, anything useful.">
                        <x-textarea name="note" rows="2">{{ old('note', $order->note) }}</x-textarea>
                    </x-field>

                    <div class="flex flex-col gap-3 sm:flex-row-reverse">
                        <x-btn size="lg" class="sm:flex-1">Save details</x-btn>
                        <x-btn type="button" variant="secondary" size="lg" class="sm:flex-1" x-on:click="open = false">
                            Cancel
                        </x-btn>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
