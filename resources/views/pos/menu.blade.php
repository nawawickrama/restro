@php
    use App\Support\Permissions;

    // The same menu the order screen sells from, plus what only a reader
    // needs: the description, and the category, since a search reaches
    // across all of them.
    $categoriesPayload = $categories->map(fn ($category) => [
        'id' => $category->id,
        'name' => $category->name,
        'items' => $category->activeMenuItems->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'category' => $category->name,
            'price_formatted' => money($item->price),
            'image_url' => $item->imageUrl(),
        ])->values(),
    ])->values();
@endphp

<x-layouts.pos title="Menu"
               :back="route('pos.home')"
               heading="Menu"
               subheading="Prices and what's on. Nothing here starts an order.">

    <div x-data="posMenu({ categories: {{ Js::from($categoriesPayload) }} })"
         class="flex h-full flex-col overflow-hidden">

        @include('pos.partials.menu-filters')

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

            {{-- Cards, not buttons: there is nothing to tap here, so nothing
                 looks as though it could be added to an order. --}}
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5">
                <template x-for="item in items" :key="item.id">
                    <div class="flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm
                                dark:border-slate-800 dark:bg-slate-900">
                        <template x-if="item.image_url">
                            <img :src="item.image_url"
                                 :alt="item.name"
                                 loading="lazy"
                                 class="h-28 w-full shrink-0 object-cover sm:h-32">
                        </template>

                        <div class="flex flex-1 flex-col gap-1 p-4">
                            <p x-show="search"
                               x-cloak
                               class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                               x-text="item.category"></p>
                            <p class="text-base font-bold text-slate-900 dark:text-white" x-text="item.name"></p>
                            <p x-show="item.description"
                               class="line-clamp-3 text-sm text-slate-600 dark:text-slate-300"
                               x-text="item.description"></p>
                            <p class="mt-auto pt-2 text-xl font-bold text-brand-600 dark:text-brand-400"
                               x-text="item.price_formatted"></p>
                        </div>
                    </div>
                </template>
            </div>

            <p x-show="items.length === 0 && search"
               x-cloak
               class="py-12 text-center text-lg text-slate-500 dark:text-slate-400">
                Nothing on the menu matches "<span x-text="search"></span>".
            </p>
        </div>
    </div>
</x-layouts.pos>
