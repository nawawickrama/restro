{{-- Shared foot of the public catalogue pages. --}}
<footer class="border-t border-white/10 py-12">
    <div class="mx-auto max-w-5xl space-y-6 px-5 text-center">
        <p class="text-sm leading-relaxed text-kd-cream/50">
            No 1351/1, Biyagama Road, Kelaniya<br>Sri Lanka 11870
        </p>

        <div class="flex flex-wrap justify-center gap-3">
            <a href="{{ route('home') }}"
               class="inline-flex items-center border border-kd-cream/25 px-6 py-3 text-sm font-bold tracking-wide
                      text-kd-cream uppercase transition hover:border-kd-gold hover:text-kd-gold">
                kdfoods.lk
            </a>

            <a href="{{ route('menu') }}"
               class="inline-flex items-center border border-kd-cream/25 px-6 py-3 text-sm font-bold tracking-wide
                      text-kd-cream uppercase transition hover:border-kd-gold hover:text-kd-gold">
                Restaurant menu
            </a>
        </div>

        <p class="flex flex-wrap items-center justify-center gap-1.5 text-sm text-kd-cream/40">
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
</footer>
