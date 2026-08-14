<x-layouts.app title="Tables">
    <x-slot:header>Tables</x-slot:header>
    <x-slot:subheader>Occupied means the table has an open dine-in order right now</x-slot:subheader>

    <x-slot:actions>
        <x-btn :href="route('tables.create')">
            <x-icon name="plus"/>
            New table
        </x-btn>
    </x-slot:actions>

    @if ($tables->isEmpty())
        <x-card class="text-center">
            <p class="text-lg text-slate-500 dark:text-slate-400">No tables yet.</p>
            <x-btn :href="route('tables.create')" class="mt-4">Create the first one</x-btn>
        </x-card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($tables as $table)
                <x-card class="space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-lg font-bold text-slate-900 dark:text-white">{{ $table->name }}</p>
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                {{ $table->seats ? $table->seats.' seats' : 'Seats not set' }}
                                · {{ $table->orders_count }} {{ Str::plural('order', $table->orders_count) }}
                            </p>
                        </div>

                        @if ($table->activeOrder)
                            <x-badge classes="bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300">
                                Occupied
                            </x-badge>
                        @elseif ($table->is_active)
                            <x-badge classes="bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">
                                Available
                            </x-badge>
                        @else
                            <x-badge classes="bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                Inactive
                            </x-badge>
                        @endif
                    </div>

                    <div class="flex gap-2">
                        <x-btn :href="route('tables.edit', $table)" variant="secondary" size="sm" class="flex-1">
                            <x-icon name="pencil" class="size-5"/>
                            Edit
                        </x-btn>

                        <x-confirm-form :action="route('tables.destroy', $table)"
                                        method="DELETE"
                                        title="Delete {{ $table->name }}?"
                                        message="Tables with past orders are deactivated instead, so order history keeps working."
                                        confirm="Delete table"
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
