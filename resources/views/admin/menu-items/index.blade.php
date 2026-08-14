<x-layouts.app title="Menu items">
    <x-slot:header>Menu items</x-slot:header>
    <x-slot:subheader>Prices here apply to new orders only — past orders keep what they were sold at</x-slot:subheader>

    <x-slot:actions>
        <x-btn :href="route('menu-items.create')">
            <x-icon name="plus"/>
            New item
        </x-btn>
    </x-slot:actions>

    <x-card>
        <form method="GET" class="flex flex-wrap gap-3">
            <div class="min-w-56 flex-1">
                <x-search-input name="search" :value="$search" placeholder="Search items"/>
            </div>

            <x-select name="category" class="w-auto min-w-48">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($categoryId === $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </x-select>

            <x-btn size="md">Filter</x-btn>
        </form>
    </x-card>

    @if ($items->isEmpty())
        <x-card class="text-center">
            <p class="text-lg text-slate-500 dark:text-slate-400">No menu items match.</p>
            <x-btn :href="route('menu-items.create')" class="mt-4">Add an item</x-btn>
        </x-card>
    @else
        <div class="space-y-3">
            @foreach ($items as $item)
                <x-card class="flex flex-wrap items-center gap-4">
                    <div class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-xl border
                                border-slate-200 bg-slate-100 dark:border-slate-700 dark:bg-slate-800">
                        @if ($item->imageUrl())
                            <img src="{{ $item->imageUrl() }}" alt="" class="size-full object-cover">
                        @else
                            <x-icon name="menu-book" class="size-6 text-slate-400"/>
                        @endif
                    </div>

                    <div class="min-w-48 flex-1">
                        <p class="text-lg font-bold text-slate-900 dark:text-white">{{ $item->name }}</p>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                            {{ $item->category->name }}
                            @unless ($item->category->is_active)
                                · <span class="text-amber-600 dark:text-amber-400">category disabled</span>
                            @endunless
                        </p>
                        @if ($item->description)
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $item->description }}</p>
                        @endif
                    </div>

                    <p class="text-xl font-bold text-slate-900 dark:text-white">{{ money($item->price) }}</p>

                    @if ($item->is_active)
                        <x-badge classes="bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">
                            Active
                        </x-badge>
                    @else
                        <x-badge classes="bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                            Disabled
                        </x-badge>
                    @endif

                    <div class="flex items-center gap-2">
                        <x-btn :href="route('menu-items.edit', $item)" variant="secondary" size="sm">
                            <x-icon name="pencil" class="size-5"/>
                            Edit
                        </x-btn>

                        <x-confirm-form :action="route('menu-items.destroy', $item)"
                                        method="DELETE"
                                        title="Delete {{ $item->name }}?"
                                        message="Items that appear on past orders are disabled instead of deleted, so history stays intact."
                                        confirm="Delete item"
                                        size="sm">
                            <x-slot:trigger>
                                <x-icon name="trash" class="size-5"/>
                            </x-slot:trigger>
                        </x-confirm-form>
                    </div>
                </x-card>
            @endforeach
        </div>

        {{ $items->links() }}
    @endif
</x-layouts.app>
