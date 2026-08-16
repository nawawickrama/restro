<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $settings->restaurantName() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

{{-- The customer's screen.

     Read from about a metre away, often at an angle, by someone who is not
     operating it — so the type is large, the contrast is high, and there is
     nothing here to tap. It keeps the restaurant's own colours rather than the
     POS greys, because this is the only screen the public ever sees. --}}
<body class="h-full overflow-hidden bg-kd-ink text-kd-cream"
      x-data="customerDisplay({
          restaurant: {{ Js::from($settings->restaurantName()) }},
          welcome: {{ Js::from($settings->get('receipt_footer') ?: 'Welcome') }}
      })"
      x-on:click="goFullscreen()">

<div class="relative flex h-dvh flex-col overflow-hidden">

    {{-- A soft brand wash, so the screen is never flat black. --}}
    <div class="brand-wash pointer-events-none absolute inset-0"></div>

    {{-- ------------------------------------------------------------ IDLE --}}
    <div x-show="screen === 'idle'"
         x-cloak
         class="relative flex flex-1 flex-col items-center justify-center gap-8 p-10 text-center">
        <img src="{{ asset('images/logo.png') }}"
             alt="{{ $settings->restaurantName() }}"
             class="w-[min(52vw,34rem)] drop-shadow-2xl">

        <p class="text-[clamp(1.6rem,3.4vw,3rem)] font-semibold text-kd-gold" x-text="welcome"></p>

        <x-smile class="size-[clamp(3rem,6vw,5rem)] text-kd-gold/70"/>

        <p class="absolute bottom-8 text-[clamp(1rem,1.6vw,1.4rem)] font-medium text-kd-cream/40"
           x-text="clock"></p>
    </div>

    {{-- ----------------------------------------------------------- ORDER --}}
    <div x-show="screen === 'order'" x-cloak class="relative flex min-h-0 flex-1 flex-col">
        <header class="flex shrink-0 items-center justify-between gap-6 px-8 pt-6 pb-4">
            <img src="{{ asset('images/logo.png') }}"
                 alt="{{ $settings->restaurantName() }}"
                 class="h-[clamp(3rem,6vh,4.5rem)] w-auto">

            <div class="text-right">
                <p class="text-[clamp(1.1rem,1.8vw,1.6rem)] font-bold text-kd-gold"
                   x-text="order?.type_label"></p>
                <p class="font-mono text-[clamp(0.85rem,1.2vw,1.1rem)] text-kd-cream/50"
                   x-text="order?.order_number"></p>
            </div>
        </header>

        {{-- The lines, newest at the bottom and kept in view. --}}
        <div x-ref="lines" class="min-h-0 flex-1 overflow-y-auto no-scrollbar px-8">
            <p x-show="!order?.items?.length"
               class="flex h-full items-center justify-center text-[clamp(1.4rem,2.6vw,2.2rem)] text-kd-cream/40">
                Your order will appear here
            </p>

            <ul class="space-y-1 pb-4">
                <template x-for="item in (order?.items ?? [])" :key="item.id">
                    <li class="flex items-baseline gap-5 rounded-2xl px-4 py-3 transition-colors duration-500"
                        :class="justAdded === item.id ? 'bg-kd-gold/20' : ''">
                        <span class="w-[3ch] shrink-0 text-right text-[clamp(1.3rem,2.4vw,2rem)] font-bold text-kd-gold tabular-nums"
                              x-text="item.quantity + '×'"></span>

                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[clamp(1.3rem,2.4vw,2rem)] font-semibold"
                                  x-text="item.name"></span>
                            <span x-show="item.note"
                                  class="block truncate text-[clamp(0.85rem,1.4vw,1.1rem)] text-kd-gold/80"
                                  x-text="item.note"></span>
                        </span>

                        <span class="shrink-0 text-[clamp(1.3rem,2.4vw,2rem)] font-bold tabular-nums"
                              x-text="item.line_total_formatted"></span>
                    </li>
                </template>
            </ul>
        </div>

        {{-- Totals are pinned: the number being paid never scrolls away. --}}
        <footer class="shrink-0 border-t border-kd-cream/15 bg-black/25 px-8 py-5 backdrop-blur-sm">
            <dl class="space-y-1 text-[clamp(1rem,1.6vw,1.35rem)] text-kd-cream/70">
                <div class="flex justify-between">
                    <dt>Subtotal</dt>
                    <dd class="tabular-nums" x-text="order?.formatted?.subtotal"></dd>
                </div>
                <div class="flex justify-between" x-show="order?.discount_amount > 0">
                    <dt>Discount</dt>
                    <dd class="tabular-nums text-kd-gold"
                        x-text="'- ' + order?.formatted?.discount_amount"></dd>
                </div>
                <div class="flex justify-between" x-show="order?.tax_amount > 0">
                    <dt>Tax</dt>
                    <dd class="tabular-nums" x-text="order?.formatted?.tax_amount"></dd>
                </div>
            </dl>

            <div class="mt-3 flex items-baseline justify-between border-t border-kd-cream/15 pt-3">
                <span class="text-[clamp(1.4rem,2.6vw,2.2rem)] font-bold text-kd-gold">Total</span>
                <span class="text-[clamp(2.6rem,7vw,5.5rem)] leading-none font-bold tabular-nums"
                      x-text="order?.formatted?.total"></span>
            </div>
        </footer>
    </div>

    {{-- ---------------------------------------------------------- PAYING --}}
    <div x-show="screen === 'paying'"
         x-cloak
         class="relative flex flex-1 flex-col items-center justify-center gap-6 p-10 text-center">
        <p class="text-[clamp(1.2rem,2vw,1.8rem)] font-semibold tracking-widest text-kd-gold uppercase">
            Amount due
        </p>

        <p class="text-[clamp(3.5rem,12vw,9rem)] leading-none font-bold tabular-nums"
           x-text="payment?.total"></p>

        {{-- Cash: the change is the number the customer is waiting on. --}}
        <div x-show="payment?.method === 'cash'" class="mt-4 w-full max-w-3xl space-y-4">
            <div class="flex items-baseline justify-between text-[clamp(1.2rem,2.2vw,1.8rem)] text-kd-cream/70">
                <span>Received</span>
                <span class="tabular-nums" x-text="payment?.tendered"></span>
            </div>

            <div class="flex items-baseline justify-between rounded-3xl bg-kd-gold/15 px-8 py-6">
                <span class="text-[clamp(1.4rem,2.6vw,2.2rem)] font-bold text-kd-gold">Change</span>
                <span class="text-[clamp(2.4rem,6.5vw,5rem)] leading-none font-bold tabular-nums text-kd-gold"
                      x-text="payment?.change"></span>
            </div>
        </div>

        <p x-show="payment?.method === 'card'"
           class="mt-4 text-[clamp(1.4rem,2.8vw,2.4rem)] font-semibold text-kd-cream/80">
            Please use the card machine
        </p>
    </div>

    {{-- ------------------------------------------------------------ DONE --}}
    <div x-show="screen === 'done'"
         x-cloak
         class="relative flex flex-1 flex-col items-center justify-center gap-7 p-10 text-center">
        <x-smile class="size-[clamp(5rem,12vw,9rem)] text-kd-gold"/>

        <p class="text-[clamp(2.4rem,6vw,4.5rem)] leading-none font-bold">Thank you!</p>

        <p class="text-[clamp(1.2rem,2.2vw,1.8rem)] text-kd-cream/70">
            <span x-text="completed?.paid"></span> paid by <span x-text="completed?.method"></span>
        </p>

        <div x-show="completed?.change && completed?.change_due"
             class="rounded-2xl bg-kd-gold/15 px-8 py-4 text-[clamp(1.2rem,2.4vw,1.9rem)] font-bold text-kd-gold">
            Change <span class="tabular-nums" x-text="completed?.change"></span>
        </div>

        {{-- Only a takeaway customer waits to be called. --}}
        <div x-show="completed?.collect" class="mt-2">
            <p class="text-[clamp(1rem,1.8vw,1.4rem)] tracking-widest text-kd-cream/50 uppercase">
                Your order number
            </p>
            <p class="mt-1 font-mono text-[clamp(2rem,5vw,3.6rem)] font-bold text-kd-gold"
               x-text="completed?.collect"></p>
        </div>
    </div>

    {{-- Shown until somebody taps once; the popup opens with browser chrome
         and a tap is the gesture the browser requires to clear it. --}}
    <p x-show="!fullscreen"
       x-cloak
       class="pointer-events-none absolute right-4 bottom-3 text-xs font-medium text-kd-cream/25">
        Tap anywhere for fullscreen
    </p>
</div>
</body>
</html>
