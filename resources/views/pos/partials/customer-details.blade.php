{{-- Customer details, available on every order type.

     A phone order must have a number before it can be paid for, so it opens as
     an amber prompt. Dine-in and takeaway offer the same fields quietly: a
     table may leave a number for a callback, a walk-in may want their name
     called when the food is up, and most of the time neither is filled in. --}}

@php
    $needsPhone = $order->needsCustomerPhone();
    $hasDetails = $order->hasCustomerDetails();
@endphp

<div x-data="{ open: false }">
    @if ($needsPhone)
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
    @elseif ($hasDetails)
        <button type="button"
                x-on:click="open = true"
                class="flex w-full touch-target items-center gap-3 rounded-2xl border border-slate-200
                       bg-slate-50 px-4 py-3 text-left transition hover:bg-slate-100
                       dark:border-slate-700 dark:bg-slate-800/60 dark:hover:bg-slate-800">
            <x-icon name="users" class="size-6 shrink-0 text-slate-500 dark:text-slate-400"/>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-base font-bold text-slate-900 dark:text-white">
                    {{ $order->customer_name ?: $order->customer_phone }}
                </span>
                <span class="block truncate text-sm font-medium text-slate-500 dark:text-slate-400">
                    {{ $order->customer_name && $order->customer_phone
                        ? $order->customer_phone
                        : ($order->customer_name ? 'No number given' : 'No name given') }}
                </span>
            </span>
            <x-icon name="pencil" class="size-5 shrink-0 text-slate-400"/>
        </button>
    @else
        {{-- Nothing taken yet, and nothing has to be: a quiet, low-contrast
             offer that does not compete with Checkout. --}}
        <button type="button"
                x-on:click="open = true"
                class="flex w-full touch-target items-center justify-center gap-2 rounded-2xl border border-dashed
                       border-slate-300 px-4 py-3 text-base font-semibold text-slate-500 transition
                       hover:border-slate-400 hover:text-slate-700 dark:border-slate-700 dark:text-slate-400
                       dark:hover:border-slate-600 dark:hover:text-slate-200">
            <x-icon name="plus" class="size-5"/>
            Add customer details
            <span class="font-medium text-slate-400 dark:text-slate-500">(optional)</span>
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
                    @if ($order->type->requiresCustomer())
                        The mobile number is how the counter finds them on collection.
                    @else
                        Optional. Useful for calling the order out, or ringing back.
                    @endif
                </p>

                <form action="{{ route('pos.orders.customer', $order) }}" method="POST" class="mt-4 space-y-4">
                    @csrf

                    <x-field label="Mobile number" name="customer_phone" :required="$order->type->requiresCustomer()">
                        <x-input name="customer_phone"
                                 type="tel"
                                 inputmode="tel"
                                 :value="old('customer_phone', $order->customer_phone)"
                                 autocomplete="off"
                                 :required="$order->type->requiresCustomer()"/>
                    </x-field>

                    <x-field label="Customer name" name="customer_name" hint="Optional.">
                        <x-input name="customer_name"
                                 :value="old('customer_name', $order->customer_name)"
                                 autocomplete="off"/>
                    </x-field>

                    <x-field label="Note" name="note" hint="Optional. Collection time, allergies, anything useful.">
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
