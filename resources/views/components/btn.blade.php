@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'submit',
])

@php
    // Every button in the app comes from here, so touch targets and focus rings
    // stay consistent without anyone having to remember the classes.
    $base = 'inline-flex items-center justify-center gap-2 rounded-xl font-semibold transition
             focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/30
             disabled:opacity-50 disabled:pointer-events-none active:scale-[0.98]';

    $sizes = [
        'sm' => 'min-h-10 px-3 text-sm',
        'md' => 'min-h-12 px-5 text-base',
        'lg' => 'min-h-14 px-6 text-lg',
        'xl' => 'min-h-20 px-8 text-xl',
    ];

    /*
     * The brand is red, so a solid red button reads as "this is the main
     * action". A destructive trigger is therefore outlined — it stays clearly
     * dangerous without competing with Checkout, and the solid red confirm
     * inside its dialog is where the deliberate final press happens.
     */
    $variants = [
        'primary' => 'bg-brand-600 text-white shadow-sm hover:bg-brand-700',
        'secondary' => 'bg-white text-slate-800 border border-slate-300 shadow-sm hover:bg-slate-50
                        dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700 dark:hover:bg-slate-700',
        'success' => 'bg-emerald-600 text-white shadow-sm hover:bg-emerald-700',
        'danger' => 'border border-rose-300 bg-white text-rose-700 shadow-sm hover:bg-rose-50
                     dark:border-rose-500/40 dark:bg-transparent dark:text-rose-300 dark:hover:bg-rose-500/10',
        'danger-solid' => 'bg-rose-600 text-white shadow-sm hover:bg-rose-700',
        'ghost' => 'text-slate-600 hover:bg-slate-200/70 dark:text-slate-300 dark:hover:bg-slate-800',
    ];

    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
