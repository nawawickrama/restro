@props(['title' => null, 'back' => null, 'heading' => null, 'subheading' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('layouts.partials.head')
</head>
<body class="h-full overflow-hidden">
{{-- Full-screen shell for the POS. No sidebar, no scrolling chrome: the screen
     belongs to the order. --}}
<div class="flex h-dvh flex-col bg-slate-100 dark:bg-slate-950">
    <header class="flex shrink-0 items-center gap-3 border-b border-slate-200 bg-white px-4 py-3
                   dark:border-slate-800 dark:bg-slate-900">
        @if ($back)
            <x-btn :href="$back" variant="secondary" size="md" aria-label="Back">
                <x-icon name="arrow-left"/>
                <span class="hidden sm:inline">Back</span>
            </x-btn>
        @endif

        <div class="min-w-0 flex-1">
            <p class="truncate text-xl font-bold text-slate-900 sm:text-2xl dark:text-white">
                {{ $heading ?? $settings->restaurantName() }}
            </p>
            @if ($subheading)
                <p class="truncate text-sm font-medium text-slate-500 dark:text-slate-400">{{ $subheading }}</p>
            @endif
        </div>

        @isset($actions)
            <div class="flex items-center gap-2">{{ $actions }}</div>
        @endisset

        <button type="button"
                x-data="themeToggle"
                x-on:click="toggle()"
                class="touch-target rounded-xl p-2 text-slate-500 hover:bg-slate-200/70 dark:text-slate-400 dark:hover:bg-slate-800"
                aria-label="Toggle dark mode">
            <x-icon name="sun" x-show="dark"/>
            <x-icon name="moon" x-show="!dark"/>
        </button>
    </header>

    <main class="min-h-0 flex-1 overflow-hidden">
        {{ $slot }}
    </main>
</div>
</body>
</html>
