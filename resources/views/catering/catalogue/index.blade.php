<x-layouts.app title="Catering catalogue">
    <x-slot:header>Catering catalogue</x-slot:header>
    <x-slot:subheader>
        The artwork customers are shown — one category per package, published at a public address
    </x-slot:subheader>

    <x-slot:actions>
        <x-btn :href="route('catalogue.index')" variant="secondary" target="_blank">
            <x-icon name="search"/>
            View public page
        </x-btn>

        <x-btn :href="route('catering-catalogues.create')">
            <x-icon name="plus"/>
            New category
        </x-btn>
    </x-slot:actions>

    @if ($catalogues->isEmpty())
        <x-card class="text-center">
            <p class="text-lg text-slate-500 dark:text-slate-400">No catalogue categories yet.</p>
            <p class="mt-1 text-base text-slate-500 dark:text-slate-400">
                Create a category — "Wedding packages", "Office lunches" — then upload its artwork.
            </p>
            <x-btn :href="route('catering-catalogues.create')" class="mt-4">Create the first one</x-btn>
        </x-card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($catalogues as $catalogue)
                <x-card padded="false" class="overflow-hidden">
                    <a href="{{ route('catering-catalogues.edit', $catalogue) }}" class="block">
                        <div class="flex aspect-4/3 items-center justify-center overflow-hidden bg-slate-100
                                    dark:bg-slate-800">
                            @if ($catalogue->cover())
                                <img src="{{ $catalogue->cover()->url() }}" alt="" loading="lazy"
                                     class="size-full object-cover">
                            @else
                                <span class="text-sm font-medium text-slate-400">No artwork yet</span>
                            @endif
                        </div>
                    </a>

                    <div class="space-y-3 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-lg font-bold text-slate-900 dark:text-white">
                                    {{ $catalogue->name }}
                                </p>
                                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                    {{ $catalogue->images->count() }}
                                    {{ Str::plural('page', $catalogue->images->count()) }}
                                </p>
                            </div>

                            @if ($catalogue->is_active)
                                <x-badge classes="bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">
                                    Live
                                </x-badge>
                            @else
                                <x-badge classes="bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                    Hidden
                                </x-badge>
                            @endif
                        </div>

                        {{-- The address to share, spelled out so it can be copied. --}}
                        <p class="truncate font-mono text-xs text-slate-500 dark:text-slate-400">
                            /catalogue/{{ $catalogue->slug }}
                        </p>

                        <div class="flex gap-2">
                            <x-btn :href="route('catering-catalogues.edit', $catalogue)"
                                   variant="secondary" size="sm" class="flex-1">
                                <x-icon name="pencil" class="size-5"/>
                                Edit
                            </x-btn>

                            <x-btn :href="route('catalogue.show', $catalogue)" variant="secondary" size="sm"
                                   target="_blank" aria-label="Open the public page">
                                <x-icon name="search" class="size-5"/>
                            </x-btn>
                        </div>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</x-layouts.app>
