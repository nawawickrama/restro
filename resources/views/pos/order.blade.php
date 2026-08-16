@php
    use App\Support\Permissions;

    // Everything the order screen needs, handed to Alpine once. Item taps then
    // talk to the endpoints below and re-render from the server's reply.
    $categoriesPayload = $categories->map(fn ($category) => [
        'id' => $category->id,
        'name' => $category->name,
        'items' => $category->activeMenuItems->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->name,
            'price_formatted' => money($item->price),
            'image_url' => $item->imageUrl(),
        ])->values(),
    ])->values();

    $endpoints = [
        'addItem' => route('pos.orders.items.store', $order),
        'updateItem' => route('pos.orders.items.update', [$order, '__ITEM__']),
        'removeItem' => route('pos.orders.items.destroy', [$order, '__ITEM__']),
    ];
@endphp

<x-layouts.pos :title="$order->order_number"
               :back="route('pos.home')"
               :heading="$order->order_number"
               :subheading="$order->type->label().' · '.$order->customerLabel()">

    <div x-data="posOrder({
            order: {{ Js::from(new App\Http\Resources\OrderResource($order)) }},
            categories: {{ Js::from($categoriesPayload) }},
            endpoints: {{ Js::from($endpoints) }},
            currency: {{ Js::from($settings->currencySymbol()) }}
         })"
         class="grid h-full grid-rows-[1fr_auto] lg:grid-cols-[1fr_26rem] lg:grid-rows-1">

        {{-- ------------------------------------------------ Menu (left) --}}
        <section class="flex min-h-0 flex-col overflow-hidden">
            <div class="shrink-0 space-y-3 border-b border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <x-search-input x-model="search" placeholder="Search the menu"/>

                <div class="flex gap-2 overflow-x-auto no-scrollbar pb-1">
                    <template x-for="category in categories" :key="category.id">
                        <button type="button"
                                x-on:click="selectCategory(category.id)"
                                x-text="category.name"
                                :class="activeCategory === category.id && !search
                                    ? 'bg-brand-600 text-white shadow-sm'
                                    : 'bg-slate-200 text-slate-700 hover:bg-slate-300 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700'"
                                class="shrink-0 touch-target rounded-xl px-5 text-base font-bold transition active:scale-[0.98]">
                        </button>
                    </template>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto p-4">
                @if ($categories->isEmpty())
                    <x-card class="text-center">
                        <p class="text-lg text-slate-500 dark:text-slate-400">
                            There are no active menu items yet.
                        </p>
                        @can(Permissions::MANAGE_MENU)
                            <x-btn :href="route('menu-items.create')" class="mt-4">Add a menu item</x-btn>
                        @endcan
                    </x-card>
                @endif

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    {{-- Items with a photo lead with it; items without stay a
                         plain name-and-price tile of the same height, so a
                         half-photographed menu still lines up. --}}
                    <template x-for="item in items" :key="item.id">
                        <button type="button"
                                x-on:click="addItem(item.id)"
                                :disabled="busy"
                                class="flex min-h-28 flex-col overflow-hidden rounded-2xl border border-slate-200
                                       bg-white text-left shadow-sm transition hover:border-brand-400
                                       hover:shadow-md active:scale-[0.97] disabled:opacity-60
                                       dark:border-slate-800 dark:bg-slate-900">
                            <template x-if="item.image_url">
                                <img :src="item.image_url"
                                     :alt="item.name"
                                     loading="lazy"
                                     class="h-24 w-full shrink-0 object-cover sm:h-28">
                            </template>

                            <span class="flex flex-1 flex-col items-start justify-between gap-2 p-4">
                                <span class="text-base font-bold text-slate-900 dark:text-white" x-text="item.name"></span>
                                <span class="text-lg font-bold text-brand-600 dark:text-brand-400"
                                      x-text="item.price_formatted"></span>
                            </span>
                        </button>
                    </template>
                </div>

                <p x-show="items.length === 0 && search"
                   class="py-12 text-center text-lg text-slate-500 dark:text-slate-400">
                    Nothing on the menu matches "<span x-text="search"></span>".
                </p>
            </div>
        </section>

        {{-- --------------------------------------- Current order (right) --}}
        <aside class="flex min-h-0 flex-col border-t border-slate-200 bg-white lg:border-t-0 lg:border-l
                      dark:border-slate-800 dark:bg-slate-900">

            {{-- On phones the order collapses to a summary bar; on a terminal
                 it is always on screen next to the menu. --}}
            <button type="button"
                    x-on:click="$refs.orderPanel.classList.toggle('hidden')"
                    class="flex touch-target items-center justify-between gap-3 border-b border-slate-200 px-4 py-3
                           text-left lg:hidden dark:border-slate-800">
                <span class="text-base font-bold text-slate-900 dark:text-white">
                    Current order
                    <span class="ml-1 text-slate-500 dark:text-slate-400"
                          x-text="`(${order.item_count})`"></span>
                </span>
                <span class="text-xl font-bold text-slate-900 dark:text-white" x-text="order.formatted.total"></span>
            </button>

            <div x-ref="orderPanel" class="hidden min-h-0 flex-1 flex-col lg:flex">
                <div class="hidden shrink-0 items-center justify-between border-b border-slate-200 px-5 py-4 lg:flex
                            dark:border-slate-800">
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">Current order</h2>
                    <x-badge :classes="$order->type->badgeClasses()">{{ $order->type->label() }}</x-badge>
                </div>

                <div x-show="error"
                     x-cloak
                     class="mx-4 mt-4 rounded-xl bg-rose-50 px-4 py-3 text-base font-semibold text-rose-800
                            dark:bg-rose-500/10 dark:text-rose-300"
                     x-text="error"
                     role="alert"></div>

                {{-- Lines --}}
                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3">
                    <p x-show="isEmpty" class="py-10 text-center text-base text-slate-500 dark:text-slate-400">
                        Tap an item on the left to start the order.
                    </p>

                    <ul class="space-y-2">
                        <template x-for="item in order.items" :key="item.id">
                            <li class="rounded-2xl border border-slate-200 p-3 dark:border-slate-800">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-base font-bold text-slate-900 dark:text-white"
                                           x-text="item.name"></p>
                                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400"
                                           x-text="`${item.unit_price_formatted} each`"></p>
                                        <p x-show="item.note"
                                           x-cloak
                                           class="mt-1 text-sm font-medium text-amber-700 dark:text-amber-400"
                                           x-text="item.note"></p>
                                    </div>
                                    <p class="shrink-0 text-lg font-bold text-slate-900 dark:text-white"
                                       x-text="item.line_total_formatted"></p>
                                </div>

                                <div class="mt-3 flex items-center gap-2">
                                    <button type="button"
                                            x-on:click="decrease(item)"
                                            :disabled="busy"
                                            class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-slate-200
                                                   text-slate-700 transition active:scale-95 dark:bg-slate-800 dark:text-slate-200"
                                            :aria-label="`Reduce ${item.name}`">
                                        <x-icon name="minus" class="size-5"/>
                                    </button>

                                    <span class="w-10 text-center text-xl font-bold text-slate-900 dark:text-white"
                                          x-text="item.quantity"></span>

                                    <button type="button"
                                            x-on:click="increase(item)"
                                            :disabled="busy"
                                            class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-slate-200
                                                   text-slate-700 transition active:scale-95 dark:bg-slate-800 dark:text-slate-200"
                                            :aria-label="`Add another ${item.name}`">
                                        <x-icon name="plus" class="size-5"/>
                                    </button>

                                    <button type="button"
                                            x-on:click="openNote(item)"
                                            class="ml-auto flex size-11 items-center justify-center rounded-xl text-slate-500
                                                   transition hover:bg-slate-100 active:scale-95 dark:hover:bg-slate-800"
                                            :aria-label="`Add a note to ${item.name}`">
                                        <x-icon name="pencil" class="size-5"/>
                                    </button>

                                    <button type="button"
                                            x-on:click="remove(item)"
                                            :disabled="busy"
                                            class="flex size-11 items-center justify-center rounded-xl text-rose-600
                                                   transition hover:bg-rose-50 active:scale-95 dark:hover:bg-rose-500/10"
                                            :aria-label="`Remove ${item.name}`">
                                        <x-icon name="trash" class="size-5"/>
                                    </button>
                                </div>
                            </li>
                        </template>
                    </ul>
                </div>

                {{-- Totals and the two actions that end the screen --}}
                <div class="shrink-0 space-y-4 border-t border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/60">
                    @include('pos.partials.customer-details')

                    <dl class="space-y-1 text-base">
                        <div class="flex justify-between text-slate-600 dark:text-slate-300">
                            <dt>Subtotal</dt>
                            <dd class="font-semibold" x-text="order.formatted.subtotal"></dd>
                        </div>
                        <div class="flex justify-between text-slate-600 dark:text-slate-300"
                             x-show="order.discount_amount > 0" x-cloak>
                            <dt>Discount</dt>
                            <dd class="font-semibold text-emerald-600 dark:text-emerald-400"
                                x-text="`- ${order.formatted.discount_amount}`"></dd>
                        </div>
                        <div class="flex justify-between text-slate-600 dark:text-slate-300"
                             x-show="order.tax_amount > 0" x-cloak>
                            <dt>Tax</dt>
                            <dd class="font-semibold" x-text="order.formatted.tax_amount"></dd>
                        </div>
                        <div class="flex justify-between border-t border-slate-200 pt-2 text-2xl font-bold
                                    text-slate-900 dark:border-slate-800 dark:text-white">
                            <dt>Total</dt>
                            <dd x-text="order.formatted.total"></dd>
                        </div>
                    </dl>

                    @include('pos.partials.order-actions')

                    <div class="grid grid-cols-2 gap-3">
                        <form action="{{ route('pos.orders.hold', $order) }}" method="POST">
                            @csrf
                            <x-btn variant="secondary" size="lg" class="w-full">Hold</x-btn>
                        </form>

                        {{-- A phone order without a number cannot be paid for,
                             so the button says what is missing instead of
                             failing at the checkout screen. --}}
                        @if ($order->needsCustomerPhone())
                            <x-btn variant="secondary" size="lg" class="w-full" disabled>
                                Number needed
                            </x-btn>
                        @else
                            <x-btn :href="route('pos.orders.checkout', $order)"
                                   variant="success"
                                   size="lg"
                                   class="w-full"
                                   x-bind:class="isEmpty ? 'pointer-events-none opacity-50' : ''">
                                Checkout
                            </x-btn>
                        @endif
                    </div>
                </div>
            </div>
        </aside>

        {{-- Item note --}}
        <div x-show="noteFor !== null"
             x-cloak
             x-on:keydown.escape.window="closeNote()"
             class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/60 p-4 backdrop-blur-sm sm:items-center"
             role="dialog"
             aria-modal="true">
            <div x-on:click.outside="closeNote()"
                 class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl dark:bg-slate-900">
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Item note</h2>
                <p class="mt-1 text-base text-slate-500 dark:text-slate-400">
                    Anything the kitchen needs to know, such as "no onions".
                </p>

                <x-input type="text"
                         x-model="noteText"
                         x-on:keydown.enter="saveNote()"
                         maxlength="255"
                         placeholder="e.g. no onions"
                         class="mt-4"/>

                <div class="mt-6 flex flex-col gap-3 sm:flex-row-reverse">
                    <x-btn type="button" size="lg" class="sm:flex-1" x-on:click="saveNote()">Save note</x-btn>
                    <x-btn type="button" variant="secondary" size="lg" class="sm:flex-1" x-on:click="closeNote()">
                        Cancel
                    </x-btn>
                </div>
            </div>
        </div>
    </div>
</x-layouts.pos>
