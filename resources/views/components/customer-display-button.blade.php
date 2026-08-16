{{-- Opens the customer's screen and shows whether it is actually up.

     Tapped once at the start of a shift, not once per order: the display keeps
     running while the cashier moves between the POS screens.

     The URL is deliberately host-relative rather than route()'s absolute form.
     A terminal may reach the POS on a LAN address, a hostname or localhost,
     and a URL baked from APP_URL opens a window pointing at the wrong machine
     — which looks exactly like a blank white tab. --}}

<div x-data="customerDisplayLauncher({ url: '{{ route('pos.display', [], false) }}' })" class="relative">
    <x-btn type="button"
           variant="secondary"
           size="md"
           x-on:click="open()"
           aria-label="Open the customer display"
           x-bind:title="connected ? 'Customer display is running' : 'Open the customer display'">
        <x-icon name="monitor" class="size-5 shrink-0"/>

        <span class="hidden sm:inline" x-text="connected ? 'Display on' : 'Display'"></span>

        {{-- A lit dot means the customer screen is answering. --}}
        <span class="relative flex size-2.5 shrink-0">
            <span x-show="connected"
                  class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-60"></span>
            <span class="relative inline-flex size-2.5 rounded-full"
                  :class="connected ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'"></span>
        </span>
    </x-btn>

    <p x-show="blocked"
       x-cloak
       class="absolute top-full right-0 z-20 mt-2 w-64 rounded-xl bg-rose-600 px-4 py-3 text-sm font-semibold
              text-white shadow-lg">
        Your browser blocked the window. Allow pop-ups for this site, then tap again.
    </p>
</div>
