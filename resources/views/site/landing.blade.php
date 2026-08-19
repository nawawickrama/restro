@php
    // Everything the page says about the restaurant, in one place at the top,
    // so a change of number or address is a single edit.
    $phone = '077 291 5469';
    $phoneLink = '+94772915469';
    $email = 'info@kdfoods.lk';
    $address = 'No 1351/1, Biyagama Road, Kelaniya, Sri Lanka 11870';
    $directions = 'https://www.google.com/maps/search/?api=1&query='.urlencode($address);

    $social = [
        ['Facebook', 'https://www.facebook.com/kdfoodscatering'],
        ['Instagram', '#'],
        ['TikTok', '#'],
    ];

    // Search engines read this to show the address, phone and hours directly
    // in results and on maps. Built here rather than inline: Blade's @json
    // directive cannot parse a nested multi-line array.
    $schema = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Restaurant',
        'name' => 'K&D Foods & Catering',
        'slogan' => 'Every Meal. A Signature Experience.',
        'image' => asset('images/storefront.jpg'),
        'logo' => asset('images/logo.png'),
        'telephone' => $phoneLink,
        'email' => $email,
        'servesCuisine' => 'Sri Lankan',
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => 'No 1351/1, Biyagama Road',
            'addressLocality' => 'Kelaniya',
            'postalCode' => '11870',
            'addressCountry' => 'LK',
        ],
        'sameAs' => ['https://www.facebook.com/kdfoodscatering'],
    ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);

    $sections = [
        ['#offering', 'What we do'],
        ['#place', 'The place'],
        ['#menu', 'Menu'],
        ['#events', 'Events'],
        ['#contact', 'Contact'],
    ];
@endphp

<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>K&amp;D Foods &amp; Catering — Restaurant, Catering &amp; Events in Kelaniya</title>
    <meta name="description"
          content="K&amp;D Foods &amp; Catering in Kelaniya, Sri Lanka. Dine in, take away, and full-service catering for weddings, corporate functions and celebrations. Every meal, a signature experience.">

    <meta property="og:title" content="K&amp;D Foods &amp; Catering">
    <meta property="og:description" content="Restaurant • Catering • Events — Kelaniya, Sri Lanka">
    <meta property="og:image" content="{{ asset('images/storefront.jpg') }}">
    <meta property="og:type" content="restaurant">
    <meta name="theme-color" content="#0b0a09">

    <link rel="icon" href="{{ asset('images/logo.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script type="application/ld+json">{!! $schema !!}</script>
</head>

<body class="min-h-full bg-kd-night font-sans text-kd-cream antialiased">

{{-- ------------------------------------------------------------------ nav --}}
<header x-data="{ open: false, scrolled: false }"
        x-on:scroll.window="scrolled = window.scrollY > 40"
        class="fixed inset-x-0 top-0 z-50 transition-colors duration-300"
        :class="scrolled || open ? 'bg-kd-night/95 backdrop-blur-md border-b border-white/10' : ''">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-5 py-4 lg:px-10">
        <a href="#top" class="flex shrink-0 items-center gap-3">
            <img src="{{ asset('images/logo.png') }}" alt="K&amp;D Foods &amp; Catering" class="h-11 w-auto lg:h-14">
        </a>

        <nav class="hidden items-center gap-8 lg:flex">
            @foreach ($sections as [$href, $label])
                <a href="{{ $href }}"
                   class="text-sm font-semibold tracking-wide text-kd-cream/70 uppercase transition hover:text-kd-gold">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <div class="flex items-center gap-3">
            <a href="tel:{{ $phoneLink }}"
               class="hidden items-center gap-2 bg-kd-red px-5 py-3 text-sm font-bold tracking-wide text-white
                      uppercase transition hover:bg-kd-gold hover:text-kd-night sm:inline-flex">
                Call {{ $phone }}
            </a>

            <button type="button"
                    x-on:click="open = !open"
                    class="p-2 text-kd-cream lg:hidden"
                    aria-label="Menu">
                <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path x-show="!open" stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/>
                    <path x-show="open" x-cloak stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/>
                </svg>
            </button>
        </div>
    </div>

    <div x-show="open" x-cloak class="border-t border-white/10 lg:hidden">
        <div class="space-y-1 px-5 py-4">
            @foreach ($sections as [$href, $label])
                <a href="{{ $href }}"
                   x-on:click="open = false"
                   class="block py-3 text-lg font-semibold tracking-wide text-kd-cream/80 uppercase">
                    {{ $label }}
                </a>
            @endforeach

            <a href="tel:{{ $phoneLink }}"
               class="mt-3 block bg-kd-red px-5 py-4 text-center font-bold tracking-wide text-white uppercase">
                Call {{ $phone }}
            </a>
        </div>
    </div>
</header>

{{-- ---------------------------------------------------------------- hero --}}
<section id="top" class="relative flex min-h-dvh items-center overflow-hidden">
    {{-- The restaurant itself, at night, as photographed. --}}
    <img src="{{ asset('images/storefront.jpg') }}"
         alt="K&amp;D Foods &amp; Catering, Biyagama Road, Kelaniya, at night"
         class="absolute inset-0 size-full object-cover"
         fetchpriority="high">

    <div class="absolute inset-0 bg-gradient-to-r from-kd-night via-kd-night/85 to-kd-night/40"></div>
    <div class="absolute inset-0 bg-gradient-to-t from-kd-night via-transparent to-kd-night/70"></div>
    <div class="pinstripe absolute inset-y-0 right-0 w-1/3 opacity-[0.07]"></div>

    <div class="relative mx-auto w-full max-w-7xl px-5 pt-32 pb-20 lg:px-10">
        <p class="reveal flex items-center gap-3 text-xs font-bold tracking-[0.35em] text-kd-gold uppercase">
            <span class="h-px w-10 bg-kd-gold"></span>
            Kelaniya, Sri Lanka
        </p>

        <h1 class="reveal mt-6 font-display text-[clamp(2.8rem,9vw,7.5rem)] leading-[0.88] tracking-tight uppercase">
            Every meal.<br>
            <span class="text-kd-gold">A signature</span><br>
            experience.
        </h1>

        <p class="reveal mt-8 max-w-xl text-lg leading-relaxed text-kd-cream/75 sm:text-xl">
            A restaurant, a catering kitchen and an events team on Biyagama Road — cooking the food
            people in Kelaniya come back for.
        </p>

        <div class="reveal mt-10 flex flex-wrap gap-4">
            <a href="tel:{{ $phoneLink }}"
               class="inline-flex items-center gap-3 bg-kd-red px-8 py-5 text-base font-bold tracking-wide
                      text-white uppercase transition hover:bg-kd-gold hover:text-kd-night">
                Order by phone
            </a>

            <a href="{{ $directions }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-3 border border-kd-cream/30 px-8 py-5 text-base font-bold
                      tracking-wide text-kd-cream uppercase transition hover:border-kd-gold hover:text-kd-gold">
                Find us
            </a>
        </div>

        <div class="reveal mt-16 flex flex-wrap items-center gap-x-8 gap-y-3 border-t border-white/10 pt-8">
            @foreach (['Restaurant', 'Catering', 'Events'] as $pillar)
                <span class="font-display text-xl tracking-wide text-kd-cream/60 uppercase sm:text-2xl">
                    {{ $pillar }}
                </span>
                @if (! $loop->last)
                    <span class="size-1.5 rounded-full bg-kd-red"></span>
                @endif
            @endforeach
        </div>
    </div>
</section>

{{-- ------------------------------------------------------------- offering --}}
<section id="offering" class="relative border-t border-white/10 py-24 lg:py-32">
    <div class="mx-auto max-w-7xl px-5 lg:px-10">
        <div class="reveal max-w-2xl">
            <p class="text-xs font-bold tracking-[0.35em] text-kd-gold uppercase">What we do</p>
            <h2 class="mt-4 font-display text-[clamp(2rem,5vw,3.8rem)] leading-[0.95] uppercase">
                Three kitchens, one standard
            </h2>
        </div>

        <div class="mt-16 grid gap-px bg-white/10 md:grid-cols-3">
            @php
                $offerings = [
                    [
                        'Restaurant',
                        'Open for dine in and take away every day. A short, honest menu cooked to order — rice and curry, kottu, burgers, fried rice and the sides that go with them.',
                        'M12 3v18M8 3v6a4 4 0 004 4M16 3v6a4 4 0 01-4 4',
                    ],
                    [
                        'Catering',
                        'Full-service catering from our own kitchen. Buffets, set menus and live counters, portioned properly and delivered hot, for twenty guests or four hundred.',
                        'M3 11h18M5 11a7 7 0 0114 0M4 15h16M6 19h12',
                    ],
                    [
                        'Events',
                        'Weddings, homecomings, corporate functions and birthdays. We plan the menu around the room, bring the staff, and clear everything away afterwards.',
                        'M8 3v4M16 3v4M3 9h18M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z',
                    ],
                ];
            @endphp

            @foreach ($offerings as [$title, $copy, $icon])
                <article class="reveal group bg-kd-night p-8 transition-colors duration-300 hover:bg-kd-red lg:p-10">
                    <svg class="size-10 text-kd-gold transition-colors group-hover:text-white"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/>
                    </svg>

                    <h3 class="mt-8 font-display text-3xl tracking-wide uppercase">{{ $title }}</h3>

                    <p class="mt-4 leading-relaxed text-kd-cream/70 transition-colors group-hover:text-white/90">
                        {{ $copy }}
                    </p>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ------------------------------------------------------------ the place --}}
<section id="place" class="relative overflow-hidden border-t border-white/10 py-24 lg:py-32">
    <div class="mx-auto grid max-w-7xl items-center gap-14 px-5 lg:grid-cols-2 lg:gap-20 lg:px-10">
        <div class="reveal relative">
            <img src="{{ asset('images/interior.jpg') }}"
                 alt="Inside K&amp;D Foods &amp; Catering"
                 loading="lazy"
                 class="w-full object-cover">

            <div class="absolute -bottom-6 -left-6 hidden bg-kd-red px-8 py-6 sm:block">
                <p class="font-display text-4xl leading-none text-white">100%</p>
                <p class="mt-1 text-xs font-bold tracking-widest text-white/80 uppercase">Cooked to order</p>
            </div>
        </div>

        <div class="reveal">
            <p class="text-xs font-bold tracking-[0.35em] text-kd-gold uppercase">The place</p>

            <h2 class="mt-4 font-display text-[clamp(2rem,5vw,3.8rem)] leading-[0.95] uppercase">
                Built for the<br><span class="text-kd-gold">everyday</span> and<br>the occasion
            </h2>

            <p class="mt-8 leading-relaxed text-kd-cream/75">
                You will find us on Biyagama Road in Kelaniya — a dining room that is comfortable enough
                for lunch on a Tuesday and smart enough for a family celebration on a Saturday.
            </p>

            <p class="mt-4 leading-relaxed text-kd-cream/75">
                The same kitchen that plates your rice and curry caters weddings for hundreds. Nothing
                leaves it that we would not serve at our own table.
            </p>

            <div class="mt-10 grid grid-cols-2 gap-px bg-white/10 sm:grid-cols-3">
                @foreach ([['Dine in', 'Every day'], ['Take away', 'Ready fast'], ['Catering', 'Islandwide']] as [$label, $detail])
                    <div class="bg-kd-night px-5 py-6">
                        <p class="font-display text-lg tracking-wide uppercase">{{ $label }}</p>
                        <p class="mt-1 text-sm text-kd-cream/50">{{ $detail }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ------------------------------------------------------------------ menu --}}
@if ($categories->isNotEmpty())
    <section id="menu" class="relative border-t border-white/10 py-24 lg:py-32">
        <div class="pinstripe absolute inset-0 opacity-[0.04]"></div>

        <div class="relative mx-auto max-w-7xl px-5 lg:px-10">
            <div class="reveal flex flex-wrap items-end justify-between gap-6">
                <div class="max-w-2xl">
                    <p class="text-xs font-bold tracking-[0.35em] text-kd-gold uppercase">Menu</p>
                    <h2 class="mt-4 font-display text-[clamp(2rem,5vw,3.8rem)] leading-[0.95] uppercase">
                        A taste of the board
                    </h2>
                </div>

                <a href="{{ route('menu') }}"
                   class="text-sm font-bold tracking-wide text-kd-gold uppercase transition hover:text-white">
                    See the full menu →
                </a>
            </div>

            {{-- Read live from the till, so the website cannot quote a price the
                 kitchen no longer charges. --}}
            <div class="mt-16 grid gap-x-16 gap-y-14 sm:grid-cols-2">
                @foreach ($categories as $category)
                    <div class="reveal">
                        <h3 class="font-display text-2xl tracking-wide text-kd-gold uppercase">
                            {{ $category->name }}
                        </h3>

                        <div class="gold-rule mt-4 h-px w-full opacity-40"></div>

                        <ul class="mt-6 space-y-5">
                            @foreach ($category->activeMenuItems as $item)
                                <li class="flex items-baseline gap-4">
                                    <span class="font-semibold text-kd-cream">{{ $item->name }}</span>
                                    <span class="h-px flex-1 border-b border-dotted border-white/20"></span>
                                    <span class="shrink-0 font-semibold tabular-nums text-kd-cream/70">
                                        {{ money($item->price) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>

            <div class="reveal mt-16 flex flex-wrap items-center gap-6 border-t border-white/10 pt-10">
                <a href="{{ route('menu') }}"
                   class="inline-flex items-center gap-3 bg-kd-gold px-8 py-5 text-base font-bold
                          tracking-wide text-kd-night uppercase transition hover:bg-white">
                    View the full menu
                </a>

                <p class="text-sm text-kd-cream/40">
                    A selection from the board. Prices include all taxes.
                </p>
            </div>
        </div>
    </section>
@endif

{{-- ---------------------------------------------------------------- events --}}
<section id="events" class="relative border-t border-white/10 py-24 lg:py-32">
    <div class="mx-auto max-w-7xl px-5 lg:px-10">
        <div class="reveal max-w-3xl">
            <p class="text-xs font-bold tracking-[0.35em] text-kd-gold uppercase">Catering &amp; events</p>
            <h2 class="mt-4 font-display text-[clamp(2rem,5vw,3.8rem)] leading-[0.95] uppercase">
                We cook. You host.
            </h2>
            <p class="mt-8 text-lg leading-relaxed text-kd-cream/75">
                Tell us the date, the number of guests and the room. We will come back with a menu,
                a price per head, and a plan for getting it all there hot.
            </p>
        </div>

        <div class="mt-16 grid gap-px bg-white/10 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Weddings', 'Homecomings and receptions, plated or buffet, with staff on the floor.'],
                ['Corporate', 'Office lunches, launches and conferences — punctual, and portioned per head.'],
                ['Celebrations', 'Birthdays, almsgivings and family gatherings, at home or at a hall.'],
                ['Bulk orders', 'Rice packets and short eats by the hundred, packed and ready to travel.'],
            ] as [$title, $copy])
                <div class="reveal bg-kd-night p-8">
                    <h3 class="font-display text-xl tracking-wide text-kd-gold uppercase">{{ $title }}</h3>
                    <p class="mt-3 text-sm leading-relaxed text-kd-cream/65">{{ $copy }}</p>
                </div>
            @endforeach
        </div>

        <div class="reveal mt-16 flex flex-wrap items-center justify-between gap-8 bg-kd-red p-10 lg:p-14">
            <div>
                <h3 class="font-display text-[clamp(1.8rem,4vw,3rem)] leading-none text-white uppercase">
                    Planning something?
                </h3>
                <p class="mt-3 text-white/80">Talk to us about dates and numbers — no obligation.</p>
            </div>

            <div class="flex flex-wrap gap-4">
                <a href="tel:{{ $phoneLink }}"
                   class="inline-flex items-center bg-white px-8 py-5 text-base font-bold tracking-wide
                          text-kd-night uppercase transition hover:bg-kd-gold">
                    {{ $phone }}
                </a>
                <a href="mailto:{{ $email }}"
                   class="inline-flex items-center border border-white/40 px-8 py-5 text-base font-bold
                          tracking-wide text-white uppercase transition hover:border-white hover:bg-white/10">
                    Email us
                </a>
            </div>
        </div>
    </div>
</section>

{{-- --------------------------------------------------------------- contact --}}
<section id="contact" class="relative border-t border-white/10 py-24 lg:py-32">
    <div class="mx-auto max-w-7xl px-5 lg:px-10">
        <div class="reveal">
            <p class="text-xs font-bold tracking-[0.35em] text-kd-gold uppercase">Contact</p>
            <h2 class="mt-4 font-display text-[clamp(2rem,5vw,3.8rem)] leading-[0.95] uppercase">Come and eat</h2>
        </div>

        <div class="mt-16 grid gap-px bg-white/10 md:grid-cols-3">
            <div class="reveal bg-kd-night p-8 lg:p-10">
                <p class="text-xs font-bold tracking-widest text-kd-gold uppercase">Find us</p>
                <p class="mt-5 text-lg leading-relaxed text-kd-cream">
                    No 1351/1, Biyagama Road<br>Kelaniya<br>Sri Lanka 11870
                </p>
                <a href="{{ $directions }}" target="_blank" rel="noopener"
                   class="mt-6 inline-block text-sm font-bold tracking-wide text-kd-gold uppercase
                          transition hover:text-white">
                    Get directions →
                </a>
            </div>

            <div class="reveal bg-kd-night p-8 lg:p-10">
                <p class="text-xs font-bold tracking-widest text-kd-gold uppercase">Call or write</p>
                <a href="tel:{{ $phoneLink }}"
                   class="mt-5 block font-display text-3xl tracking-wide transition hover:text-kd-gold">
                    {{ $phone }}
                </a>
                <a href="mailto:{{ $email }}"
                   class="mt-3 block text-lg text-kd-cream/75 transition hover:text-kd-gold">
                    {{ $email }}
                </a>
            </div>

            <div class="reveal bg-kd-night p-8 lg:p-10">
                <p class="text-xs font-bold tracking-widest text-kd-gold uppercase">Follow</p>
                <ul class="mt-5 space-y-3">
                    @foreach ($social as [$name, $url])
                        <li>
                            <a href="{{ $url }}"
                               @if ($url !== '#') target="_blank" rel="noopener" @endif
                               class="text-lg text-kd-cream/75 transition hover:text-kd-gold">
                                {{ $name }} →
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</section>

{{-- ---------------------------------------------------------------- footer --}}
<footer class="border-t border-white/10 py-14">
    <div class="mx-auto flex max-w-7xl flex-col items-center gap-8 px-5 text-center lg:flex-row
                lg:justify-between lg:px-10 lg:text-left">
        <img src="{{ asset('images/logo.png') }}" alt="K&amp;D Foods &amp; Catering" class="h-16 w-auto">

        <p class="max-w-md text-sm leading-relaxed text-kd-cream/50">
            K&amp;D Foods &amp; Catering — Biyagama Road, Kelaniya.<br>
            Every meal, a signature experience.
        </p>

        <div class="flex flex-col items-center gap-2 text-sm text-kd-cream/40 lg:items-end">
            <span>&copy; {{ date('Y') }} K&amp;D Foods &amp; Catering</span>

            <p class="flex items-center gap-1.5">
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

{{-- Sections settle into place as they come into view. Anyone who has asked
     their system for less motion simply gets them already in place. --}}
<script>
    (() => {
        const items = document.querySelectorAll('.reveal');
        const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (still || !('IntersectionObserver' in window)) {
            items.forEach((item) => item.classList.add('revealed'));

            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    observer.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -10% 0px' });

        items.forEach((item) => observer.observe(item));
    })();
</script>
</body>
</html>
