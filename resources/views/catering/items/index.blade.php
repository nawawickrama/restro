<x-layouts.app title="Catering items">
    <x-slot:header>Catering items</x-slot:header>
    <x-slot:subheader>
        What the kitchen quotes for functions — kept apart from the menu the till sells
    </x-slot:subheader>

    <x-slot:actions>
        <x-btn :href="route('catering.index')" variant="secondary">
            <x-icon name="arrow-left"/>
            Diary
        </x-btn>

        <x-btn :href="route('catering-items.create')">
            <x-icon name="plus"/>
            New item
        </x-btn>
    </x-slot:actions>

    @if ($items->isEmpty())
        <x-card class="text-center">
            <p class="text-lg text-slate-500 dark:text-slate-400">No catering items yet.</p>
            <p class="mt-1 text-base text-slate-500 dark:text-slate-400">
                Add the dishes you cater, with the unit each is normally ordered in.
            </p>
            <x-btn :href="route('catering-items.create')" class="mt-4">Add the first one</x-btn>
        </x-card>
    @else
        <div class="space-y-3">
            @foreach ($items as $item)
                <x-card class="flex flex-wrap items-center gap-4">
                    <div class="min-w-48 flex-1">
                        <p class="text-lg font-bold text-slate-900 dark:text-white">{{ $item->name }}</p>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                            Usually ordered in {{ $item->default_unit->label() }}
                            @if ($item->booking_items_count > 0)
                                · on {{ $item->booking_items_count }}
                                {{ Str::plural('booking', $item->booking_items_count) }}
                            @endif
                        </p>
                    </div>

                    <p class="text-xl font-bold tabular-nums text-slate-900 dark:text-white">
                        {{ $item->price !== null ? money($item->price) : '—' }}
                        <span class="text-sm font-medium text-slate-500 dark:text-slate-400">
                            @if ($item->price !== null) / {{ rtrim($item->default_unit->label(), 's') }} @endif
                        </span>
                    </p>

                    @if ($item->is_active)
                        <x-badge classes="bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">
                            Active
                        </x-badge>
                    @else
                        <x-badge classes="bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                            Switched off
                        </x-badge>
                    @endif

                    <div class="flex items-center gap-2">
                        <x-btn :href="route('catering-items.edit', $item)" variant="secondary" size="sm">
                            <x-icon name="pencil" class="size-5"/>
                            Edit
                        </x-btn>

                        <x-confirm-form :action="route('catering-items.destroy', $item)"
                                        method="DELETE"
                                        title="Delete {{ $item->name }}?"
                                        message="Items already written onto a booking sheet are switched off instead, so past sheets keep reading the way the kitchen worked from them."
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
    @endif
</x-layouts.app>
