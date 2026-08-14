<x-layouts.app title="Categories">
    <x-slot:header>Categories</x-slot:header>
    <x-slot:subheader>Groups the POS uses to organise the menu</x-slot:subheader>

    <x-slot:actions>
        <x-btn :href="route('categories.create')">
            <x-icon name="plus"/>
            New category
        </x-btn>
    </x-slot:actions>

    @if ($categories->isEmpty())
        <x-card class="text-center">
            <p class="text-lg text-slate-500 dark:text-slate-400">No categories yet.</p>
            <x-btn :href="route('categories.create')" class="mt-4">Create the first one</x-btn>
        </x-card>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($categories as $category)
                <x-card class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-lg font-bold text-slate-900 dark:text-white">{{ $category->name }}</p>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                            {{ $category->menu_items_count }} {{ Str::plural('item', $category->menu_items_count) }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($category->is_active)
                            <x-badge classes="bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">
                                Active
                            </x-badge>
                        @else
                            <x-badge classes="bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                Disabled
                            </x-badge>
                        @endif

                        <x-btn :href="route('categories.edit', $category)" variant="secondary" size="sm">
                            <x-icon name="pencil" class="size-5"/>
                            Edit
                        </x-btn>

                        <x-confirm-form :action="route('categories.destroy', $category)"
                                        method="DELETE"
                                        title="Delete {{ $category->name }}?"
                                        message="This cannot be undone. Categories that still hold menu items cannot be deleted."
                                        confirm="Delete category"
                                        size="sm">
                            <x-slot:trigger>
                                <x-icon name="trash" class="size-5"/>
                            </x-slot:trigger>
                        </x-confirm-form>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</x-layouts.app>
