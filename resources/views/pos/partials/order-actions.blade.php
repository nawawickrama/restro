@php use App\Support\Permissions; @endphp

{{-- Secondary actions: everything that is not "add food" or "take payment".
     Each one is a normal form post, so the screen reloads with fresh state. --}}
<div class="flex flex-wrap gap-2">
    @can(Permissions::APPLY_DISCOUNTS)
        <div x-data="{ open: false }" class="flex-1">
            <x-btn type="button" variant="secondary" size="sm" class="w-full" x-on:click="open = true">
                Discount
            </x-btn>

            <template x-teleport="body">
                <div x-show="open"
                     x-cloak
                     x-on:keydown.escape.window="open = false"
                     class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/60 p-4 backdrop-blur-sm sm:items-center"
                     role="dialog"
                     aria-modal="true">
                    <div x-on:click.outside="open = false"
                         class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl dark:bg-slate-900">
                        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Discount</h2>
                        <p class="mt-1 text-base text-slate-500 dark:text-slate-400">
                            Taken off the subtotal of {{ money($order->subtotal) }}.
                        </p>

                        <form action="{{ route('pos.orders.discount', $order) }}" method="POST" class="mt-4 space-y-4">
                            @csrf
                            <x-field label="Discount amount" name="discount_amount">
                                <x-input name="discount_amount"
                                         type="number"
                                         step="0.01"
                                         min="0"
                                         inputmode="decimal"
                                         :value="old('discount_amount', (float) $order->discount_amount)"/>
                            </x-field>

                            <div class="flex flex-col gap-3 sm:flex-row-reverse">
                                <x-btn size="lg" class="sm:flex-1">Apply</x-btn>
                                <x-btn type="button" variant="secondary" size="lg" class="sm:flex-1"
                                       x-on:click="open = false">Cancel</x-btn>
                            </div>
                        </form>
                    </div>
                </div>
            </template>
        </div>
    @endcan

    @if ($order->type->usesTable() && $tables->isNotEmpty())
        <div x-data="{ open: false }" class="flex-1">
            <x-btn type="button" variant="secondary" size="sm" class="w-full" x-on:click="open = true">
                Move table
            </x-btn>

            <template x-teleport="body">
                <div x-show="open"
                     x-cloak
                     x-on:keydown.escape.window="open = false"
                     class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/60 p-4 backdrop-blur-sm sm:items-center"
                     role="dialog"
                     aria-modal="true">
                    <div x-on:click.outside="open = false"
                         class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl dark:bg-slate-900">
                        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Move to another table</h2>
                        <p class="mt-1 text-base text-slate-500 dark:text-slate-400">
                            Currently at {{ $order->table?->name ?? 'no table' }}. Occupied tables cannot be chosen.
                        </p>

                        <div class="mt-4 grid max-h-80 grid-cols-2 gap-3 overflow-y-auto sm:grid-cols-3">
                            @foreach ($tables as $table)
                                @php($busy = $table->activeOrder !== null && $table->id !== $order->table_id)

                                <form action="{{ route('pos.orders.move', $order) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="table_id" value="{{ $table->id }}">
                                    <button type="submit"
                                            @disabled($busy || $table->id === $order->table_id)
                                            class="w-full touch-target rounded-2xl border-2 p-4 text-base font-bold transition
                                                   active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40
                                                   {{ $table->id === $order->table_id
                                                       ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300'
                                                       : 'border-slate-200 text-slate-800 hover:border-brand-400 dark:border-slate-700 dark:text-slate-100' }}">
                                        {{ $table->name }}
                                        <span class="mt-1 block text-xs font-semibold uppercase
                                                     {{ $busy ? 'text-rose-600' : 'text-emerald-600' }}">
                                            {{ $table->id === $order->table_id ? 'Current' : ($busy ? 'Occupied' : 'Free') }}
                                        </span>
                                    </button>
                                </form>
                            @endforeach
                        </div>

                        <x-btn type="button" variant="secondary" size="lg" class="mt-6 w-full" x-on:click="open = false">
                            Close
                        </x-btn>
                    </div>
                </div>
            </template>
        </div>
    @endif

    @can(Permissions::CANCEL_ORDERS)
        <x-confirm-form :action="route('pos.orders.cancel', $order)"
                        title="Cancel this order?"
                        :message="'Order '.$order->order_number.' will be voided and the table freed. This cannot be undone.'"
                        confirm="Cancel order"
                        size="sm"
                        class="flex-1">
            <x-slot:trigger>Cancel order</x-slot:trigger>
        </x-confirm-form>
    @endcan
</div>

{{-- Phone orders move through pending -> ready -> collected while they wait. --}}
@if ($order->type->requiresCustomer())
    <div class="flex flex-wrap gap-2 border-t border-slate-200 pt-3 dark:border-slate-800">
        @foreach (\App\Enums\FulfillmentStatus::cases() as $status)
            <form action="{{ route('pos.orders.fulfillment', [$order, $status->value]) }}" method="POST" class="flex-1">
                @csrf
                <x-btn :variant="$order->fulfillment_status === $status ? 'primary' : 'secondary'"
                       size="sm"
                       class="w-full">
                    {{ $status->label() }}
                </x-btn>
            </form>
        @endforeach
    </div>
@endif
