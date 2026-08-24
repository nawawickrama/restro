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

<main class="mx-auto max-w-5xl px-5 py-12">
    <div class="text-center">
        <h1 class="font-display text-[clamp(1.9rem,7vw,3.5rem)] leading-none uppercase">{{ $catalogue->name }}</h1>

        @if ($catalogue->description)
            <p class="mx-auto mt-4 max-w-2xl leading-relaxed text-kd-cream/65">{{ $catalogue->description }}</p>
        @endif
    </div>

    {{-- The artwork, one page under another. Full width on a phone, because
         these are designed sheets meant to be read, not thumbnails. --}}
    <div class="mt-12 space-y-8">
        @foreach ($catalogue->images as $image)
            <figure>
                <img src="{{ $image->url() }}"
                     alt="{{ $catalogue->name }} — page {{ $loop->iteration }}"
                     loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                     decoding="async"
                     class="w-full border border-white/10 bg-white/5">

                @if ($image->caption)
                    <figcaption class="mt-3 text-center text-sm text-kd-cream/50">{{ $image->caption }}</figcaption>
                @endif
            </figure>
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

    @if ($others->isNotEmpty())
        <div class="mt-16 border-t border-white/10 pt-10">
            <p class="text-xs font-bold tracking-[0.3em] text-kd-gold uppercase">Other packages</p>

            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                @foreach ($others as $other)
                    <a href="{{ route('catalogue.show', $other) }}"
                       class="group border border-white/10 p-5 transition hover:border-kd-gold">
                        <span class="font-display text-lg tracking-wide uppercase">{{ $other->name }}</span>
                        <span class="mt-1 block text-sm text-kd-cream/50">
                            {{ $other->images->count() }} {{ Str::plural('page', $other->images->count()) }} →
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
