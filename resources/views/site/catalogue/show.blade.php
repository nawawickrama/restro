@php
    $phone = '077 291 5469';
    $phoneLink = '+94772915469';
@endphp

<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>{{ $catalogue->name }} — K&amp;D Foods &amp; Catering</title>
    <meta name="description"
          content="{{ $catalogue->description ?: $catalogue->name.' from K&D Foods & Catering, Kelaniya.' }}">
    <meta property="og:title" content="{{ $catalogue->name }} — K&amp;D Foods &amp; Catering">
    <meta property="og:image" content="{{ url($catalogue->cover()?->url() ?? asset('images/logo.png')) }}">
    <meta name="theme-color" content="#0b0a09">
    <link rel="icon" href="{{ asset('images/logo.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-full bg-kd-night font-sans text-kd-cream antialiased">

<header class="border-b border-white/10">
    <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-5 py-5">
        <a href="{{ route('catalogue.index') }}" class="flex items-center gap-3">
            <img src="{{ asset('images/logo.png') }}" alt="K&amp;D Foods &amp; Catering" class="h-12 w-auto">
        </a>

        <a href="{{ route('catalogue.index') }}"
           class="text-sm font-bold tracking-wide text-kd-cream/70 uppercase transition hover:text-kd-gold">
            ← All packages
        </a>
    </div>
</header>

<main class="mx-auto max-w-5xl px-5 py-12"
      x-data="{ zoomed: null, open(src, title) { this.zoomed = { src, title }; }, close() { this.zoomed = null; } }">
    <div class="text-center">
        <h1 class="font-display text-[clamp(1.9rem,7vw,3.5rem)] leading-none uppercase">{{ $catalogue->name }}</h1>

        @if ($catalogue->description)
            <p class="mx-auto mt-4 max-w-2xl leading-relaxed text-kd-cream/65">{{ $catalogue->description }}</p>
        @endif
    </div>

    @if ($catalogue->images->count() > 1)
        <div class="mt-10 flex flex-wrap justify-center gap-2">
            @foreach ($catalogue->images as $image)
                <a href="#package-{{ $image->id }}"
                   class="rounded-full border border-white/15 px-5 py-2.5 text-sm font-bold tracking-wide
                          text-kd-cream/75 uppercase transition hover:border-kd-gold hover:text-kd-gold">
                    {{ $image->displayTitle($loop->iteration) }}
                </a>
            @endforeach
        </div>
    @endif

    {{-- Each artwork is a package in its own right, so it gets its own heading
         and its own anchor — a link can point at one package rather than the
         whole category. Full width, because these are designed sheets meant to
         be read, not thumbnails. --}}
    <div class="mt-12 space-y-14">
        @foreach ($catalogue->images as $image)
            @php($title = $image->displayTitle($loop->iteration))

            <section id="package-{{ $image->id }}" class="scroll-mt-6">
                <div class="flex items-baseline gap-4">
                    <h2 class="font-display text-2xl tracking-wide text-kd-gold uppercase sm:text-3xl">
                        {{ $title }}
                    </h2>
                    <span class="gold-rule h-px flex-1 opacity-40"></span>
                </div>

                {{-- Tapping opens the artwork full screen: these are designed
                     sheets meant to be read, and a phone needs the room. --}}
                <button type="button"
                        x-on:click="open({{ Js::from($image->url()) }}, {{ Js::from($title) }})"
                        class="group mt-5 block w-full cursor-zoom-in border border-white/10 bg-white/5
                               transition hover:border-kd-gold/60">
                    <img src="{{ $image->url() }}"
                         alt="{{ $catalogue->name }} — {{ $title }}"
                         loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                         decoding="async"
                         class="w-full">
                </button>
            </section>
        @endforeach
    </div>

    <div class="mt-14 border-t border-white/10 pt-10 text-center">
        <p class="font-display text-2xl uppercase">Planning something?</p>
        <p class="mt-2 text-kd-cream/60">Talk to us about dates and numbers — no obligation.</p>

        <a href="tel:{{ $phoneLink }}"
           class="mt-6 inline-flex bg-kd-red px-8 py-4 text-sm font-bold tracking-wide text-white uppercase
                  transition hover:bg-kd-gold hover:text-kd-night">
            Call {{ $phone }}
        </a>
    </div>

    <div x-show="zoomed"
         x-cloak
         x-on:keydown.escape.window="close()"
         x-on:click="close()"
         class="fixed inset-0 z-50 flex flex-col items-center justify-center gap-4 bg-kd-night/95 p-4
                backdrop-blur-sm"
         role="dialog"
         aria-modal="true">
        <p class="font-display text-lg tracking-wide text-kd-gold uppercase" x-text="zoomed?.title"></p>

        <img :src="zoomed?.src" :alt="zoomed?.title"
             class="max-h-[80dvh] max-w-full cursor-zoom-out object-contain">

        <button type="button" x-on:click="close()"
                class="rounded-full border border-kd-cream/25 px-6 py-2.5 text-sm font-bold tracking-wide
                       text-kd-cream uppercase transition hover:border-kd-gold hover:text-kd-gold">
            Close
        </button>
    </div>

    @if ($others->isNotEmpty())
        <div class="mt-16 border-t border-white/10 pt-10">
            <p class="text-xs font-bold tracking-[0.3em] text-kd-gold uppercase">Other packages</p>

            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                @foreach ($others as $other)
                    <a href="{{ route('catalogue.show', $other) }}"
                       class="group border border-white/10 p-5 transition hover:border-kd-gold">
                        <span class="font-display text-lg tracking-wide uppercase">{{ $other->name }}</span>
                        <span class="mt-1 block text-sm text-kd-cream/50">
                            {{ $other->images->count() }} {{ Str::plural('package', $other->images->count()) }} →
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</main>

@include('site.catalogue.footer')
</body>
</html>
