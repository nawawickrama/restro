@props(['label', 'value', 'icon' => null, 'tone' => 'default'])

@php
    $tones = [
        'default' => 'text-slate-900 dark:text-white',
        'brand' => 'text-brand-600 dark:text-brand-400',
        'success' => 'text-emerald-600 dark:text-emerald-400',
        'warning' => 'text-amber-600 dark:text-amber-400',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900']) }}>
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">{{ $label }}</p>
        @if ($icon)
            <x-icon :name="$icon" class="size-5 text-slate-400"/>
        @endif
    </div>

    <p class="mt-2 text-3xl font-bold {{ $tones[$tone] ?? $tones['default'] }}">{{ $value }}</p>

    @isset($footer)
        <p class="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400">{{ $footer }}</p>
    @endisset
</div>
