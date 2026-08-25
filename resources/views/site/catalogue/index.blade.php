@php
    $phone = '077 291 5469';
    $phoneLink = '+94772915469';
@endphp

<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>Catering catalogue — K&amp;D Foods &amp; Catering</title>
    <meta name="description"
          content="Catering packages from K&amp;D Foods &amp; Catering, Kelaniya — weddings, corporate functions and celebrations.">
    <meta name="theme-color" content="#0b0a09">
    <link rel="icon" href="{{ asset('images/logo.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-full bg-kd-night font-sans text-kd-cream antialiased">

<header class="relative overflow-hidden border-b border-white/10">
    <div class="pinstripe absolute inset-0 opacity-[0.06]"></div>

    <div class="relative mx-auto max-w-5xl px-5 py-12 text-center sm:py-16">
        <a href="{{ route('home') }}">
            <img src="{{ asset('images/logo.png') }}" alt="K&amp;D Foods &amp; Catering"
                 class="mx-auto h-24 w-auto sm:h-28">
        </a>

        <h1 class="mt-8 font-display text-[clamp(2rem,9vw,4rem)] leading-none uppercase">Catering catalogue</h1>

        <p class="mt-4 text-sm tracking-[0.25em] text-kd-gold uppercase">
            Weddings · Corporate · Celebrations
        </p>
    </div>
</header>

<main class="mx-auto max-w-5xl px-5 py-14">
    @if ($catalogues->isEmpty())
        <div class="text-center">
            <p class="text-lg text-kd-cream/60">Our catalogue is being updated.</p>
            <a href="tel:{{ $phoneLink }}"
               class="mt-8 inline-flex bg-kd-red px-8 py-4 font-bold tracking-wide text-white uppercase">
                Call {{ $phone }}
            </a>
        </div>
    @else
        <div class="grid gap-6 sm:grid-cols-2">
            @foreach ($catalogues as $catalogue)
                <a href="{{ route('catalogue.show', $catalogue) }}"
                   class="group block overflow-hidden border border-white/10 transition hover:border-kd-gold">
                    <div class="aspect-4/3 overflow-hidden bg-white/5">
                        <img src="{{ $catalogue->cover()->url() }}"
                             alt="{{ $catalogue->name }}"
                             loading="lazy"
                             class="size-full object-cover transition duration-500 group-hover:scale-105">
                    </div>

                    <div class="p-6">
                        <h2 class="font-display text-2xl tracking-wide uppercase">{{ $catalogue->name }}</h2>

                        @if ($catalogue->description)
                            <p class="mt-2 text-sm leading-relaxed text-kd-cream/60">{{ $catalogue->description }}</p>
                        @endif

                        <p class="mt-4 text-sm font-bold tracking-wide text-kd-gold uppercase">
                            View {{ $catalogue->images->count() }}
                            {{ Str::plural('package', $catalogue->images->count()) }} →
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</main>

@include('site.catalogue.footer')
</body>
</html>
