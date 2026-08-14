@props([
    'action',
    'method' => 'POST',
    'title' => 'Are you sure?',
    'message' => 'This cannot be undone.',
    'confirm' => 'Confirm',
    'variant' => 'danger',
    'size' => 'md',
])

{{-- Destructive actions ask first, with buttons big enough to hit deliberately
     rather than a browser confirm() a cashier would dismiss by reflex. --}}
<div x-data="{ open: false }" {{ $attributes->only('class') }}>
    <x-btn type="button" :variant="$variant" :size="$size" x-on:click="open = true" class="w-full">
        {{ $trigger }}
    </x-btn>

    <template x-teleport="body">
        <div x-show="open"
             x-cloak
             x-on:keydown.escape.window="open = false"
             class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/60 p-4 backdrop-blur-sm sm:items-center"
             role="dialog"
             aria-modal="true">
            <div x-show="open"
                 x-transition
                 x-on:click.outside="open = false"
                 class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl dark:bg-slate-900">
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $title }}</h2>
                <p class="mt-2 text-base text-slate-600 dark:text-slate-300">{{ $message }}</p>

                <div class="mt-6 flex flex-col gap-3 sm:flex-row-reverse">
                    <form action="{{ $action }}" method="POST" class="sm:flex-1">
                        @csrf
                        @if (! in_array($method, ['GET', 'POST']))
                            @method($method)
                        @endif
                        {{ $slot }}
                        <x-btn :variant="$variant" size="lg" class="w-full">{{ $confirm }}</x-btn>
                    </form>

                    <x-btn type="button" variant="secondary" size="lg" x-on:click="open = false" class="sm:flex-1">
                        Cancel
                    </x-btn>
                </div>
            </div>
        </div>
    </template>
</div>
