@php
    $phone = '077 291 5469';
    $phoneLink = '+94772915469';
    $address = 'No 1351/1, Biyagama Road, Kelaniya, Sri Lanka 11870';
    $directions = 'https://www.google.com/maps/search/?api=1&query='.urlencode($address);
@endphp

<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>Menu — K&amp;D Foods &amp; Catering</title>
    <meta name="description" content="The full menu at K&amp;D Foods &amp; Catering, Biyagama Road, Kelaniya.">
    <meta name="theme-color" content="#0b0a09">

    <link rel="icon" href="{{ asset('images/logo.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

{{-- Read on a phone, at a table, in whatever light the dining room has. Large
     type, high contrast, and no ordering: this page only tells you what is on
     and what it costs. --}}
<body class="min-h-full bg-kd-night font-sans text-kd-cream antialiased">

<header class="relative overflow-hidden border-b border-white/10">
    <div class="pinstripe absolute inset-0 opacity-[0.06]"></div>

    <div class="relative mx-auto max-w-4xl px-5 py-12 text-center sm:py-16">
        <a href="{{ route('home') }}">
            <img src="{{ asset('images/logo.png') }}"
                 alt="K&amp;D Foods &amp; Catering"
                 class="mx-auto h-24 w-auto sm:h-28">
        </a>

        <h1 class="mt-8 font-display text-[clamp(2.5rem,12vw,4.5rem)] leading-none uppercase">Menu</h1>

        <p class="mt-4 text-sm tracking-[0.25em] text-kd-gold uppercase">
            Every meal. A signature experience.
        </p>
    </div>
</header>

@if ($categories->isEmpty())
    <main class="mx-auto max-w-4xl px-5 py-24 text-center">
        <p class="text-lg text-kd-cream/60">Our menu is being updated. Please ask a member of staff.</p>

        <a href="tel:{{ $phoneLink }}"
           class="mt-8 inline-flex bg-kd-red px-8 py-4 font-bold tracking-wide text-white uppercase">
            Call {{ $phone }}
        </a>
    </main>
@else
    {{-- Jump links, kept within reach of a thumb while scrolling. --}}
    <nav class="sticky top-0 z-40 border-b border-white/10 bg-kd-night/95 backdrop-blur-md">
        <div class="mx-auto flex max-w-4xl gap-2 overflow-x-auto no-scrollbar px-5 py-3">
            @foreach ($categories as $category)
                <a href="#category-{{ $category->id }}"
                   class="shrink-0 rounded-full border border-white/15 px-5 py-2.5 text-sm font-bold
                          tracking-wide whitespace-nowrap text-kd-cream/75 uppercase transition
                          hover:border-kd-gold hover:text-kd-gold">
                    {{ $category->name }}
                </a>
            @endforeach
        </div>
    </nav>

    <main class="mx-auto max-w-4xl px-5 pb-24">
        @foreach ($categories as $category)
            <section id="category-{{ $category->id }}" class="scroll-mt-20 pt-14">
                <div class="flex items-center gap-4">
                    <h2 class="font-display text-3xl tracking-wide text-kd-gold uppercase sm:text-4xl">
                        {{ $category->name }}
                    </h2>
                    <span class="gold-rule h-px flex-1 opacity-40"></span>
                </div>

                <ul class="mt-8 space-y-4">
                    @foreach ($category->activeMenuItems as $item)
                        <li class="flex items-start gap-4 border-b border-white/5 pb-4 last:border-0 sm:gap-6">
                            {{-- The picture taken in the back office, if there is
                                 one. Items without a photo keep the same shape,
                                 so a half-photographed menu still lines up. --}}
                            @if ($item->imageUrl())
                                <img src="{{ $item->imageUrl() }}"
                                     alt="{{ $item->name }}"
                                     loading="lazy"
                                     decoding="async"
                                     width="112"
                                     height="112"
                                     class="size-20 shrink-0 rounded-xl bg-white/5 object-cover sm:size-28">
                            @else
                                <div class="flex size-20 shrink-0 items-center justify-center rounded-xl
                                            border border-white/10 bg-white/5 sm:size-28"
                                     aria-hidden="true">
                                    <svg class="size-7 text-kd-gold/40" fill="none" viewBox="0 0 24 24"
                                         stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M12 3v18M8 3v6a4 4 0 004 4M16 3v6a4 4 0 01-4 4"/>
                                    </svg>
                                </div>
                            @endif

                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline gap-3">
                                    <h3 class="text-lg font-semibold text-kd-cream sm:text-xl">{{ $item->name }}</h3>
                                    <span class="h-px flex-1 border-b border-dotted border-white/15"></span>
                                    <span class="shrink-0 text-lg font-bold tabular-nums text-kd-gold sm:text-xl">
                                        {{ money($item->price) }}
                                    </span>
                                </div>

                                @if ($item->description)
                                    <p class="mt-2 text-sm leading-relaxed text-kd-cream/55 sm:text-base">
                                        {{ $item->description }}
                                    </p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        <p class="mt-16 border-t border-white/10 pt-8 text-center text-sm text-kd-cream/40">
            All prices in Sri Lankan rupees and inclusive of taxes.<br>
            Please tell us about any allergies before ordering.
        </p>
    </main>
@endif

<footer class="border-t border-white/10 py-12">
    <div class="mx-auto max-w-4xl space-y-8 px-5 text-center">
        <div class="flex flex-wrap justify-center gap-3">
            <a href="tel:{{ $phoneLink }}"
               class="inline-flex items-center bg-kd-red px-7 py-4 text-sm font-bold tracking-wide
                      text-white uppercase transition hover:bg-kd-gold hover:text-kd-night">
                Call {{ $phone }}
            </a>

            <a href="{{ $directions }}" target="_blank" rel="noopener"
               class="inline-flex items-center border border-kd-cream/25 px-7 py-4 text-sm font-bold
                      tracking-wide text-kd-cream uppercase transition hover:border-kd-gold hover:text-kd-gold">
                Find us
            </a>
        </div>

        <p class="text-sm leading-relaxed text-kd-cream/50">
            No 1351/1, Biyagama Road, Kelaniya<br>Sri Lanka 11870
        </p>

        <div class="space-y-2 text-sm text-kd-cream/40">
            <p><a href="{{ route('home') }}" class="transition hover:text-kd-gold">kdfoods.lk</a></p>

            <p class="flex flex-wrap items-center justify-center gap-1.5">
                Engineered by
                <a href="https://coredile.com" target="_blank" rel="noopener"
                   class="font-semibold text-kd-cream/70 transition hover:text-kd-gold">Coredile</a>
                with
                <svg class="size-4 text-kd-red" viewBox="0 0 24 24" fill="currentColor" aria-label="love">
                    <path d="M12 21s-7.5-4.6-9.6-9A5.4 5.4 0 0 1 12 6.6 5.4 5.4 0 0 1 21.6 12c-2.1 4.4-9.6 9-9.6 9z"/>
                </svg>
                of Sri Lanka
            </p>
        </div>
    </div>
</footer>
</body>
</html>
