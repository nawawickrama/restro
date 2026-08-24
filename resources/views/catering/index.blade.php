@php
    use App\Enums\CateringStatus;

    $today = today();
    $previous = $month->copy()->subMonth()->format('Y-m');
    $next = $month->copy()->addMonth()->format('Y-m');
@endphp

<x-layouts.app title="Catering">
    <x-slot:header>Catering</x-slot:header>
    <x-slot:subheader>{{ $month->format('F Y') }} — what the kitchen is booked for</x-slot:subheader>

    <x-slot:actions>
        <x-btn :href="route('catering-items.index')" variant="secondary">
            <x-icon name="menu-book"/>
            Catering items
        </x-btn>

        <x-btn :href="route('catering.create', ['date' => $today->toDateString()])">
            <x-icon name="plus"/>
            New booking
        </x-btn>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat label="Bookings this month" :value="number_format($summary['bookings'])" icon="clock"/>
        <x-stat label="Guests" :value="number_format($summary['pax'])" icon="users" tone="brand"/>
        <x-stat label="Value" :value="money($summary['value'])" icon="chart" tone="success"/>
    </div>

    {{-- The whole month is handed to the page, so opening a booking is instant
         rather than a round trip. A month holds tens of bookings, not
         thousands. --}}
    <div x-data="{
            bookings: {{ Js::from($payload) }},
            open: null,
            show(id) { this.open = this.bookings[id] ?? null; },
            close() { this.open = null; },
         }">

        <x-card padded="false" class="overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3
                        dark:border-slate-800">
                <div class="flex items-center gap-2">
                    <x-btn :href="route('catering.index', ['month' => $previous])" variant="secondary" size="sm"
                           aria-label="Previous month">
                        <x-icon name="arrow-left" class="size-5"/>
                    </x-btn>

                    <x-btn :href="route('catering.index')" variant="secondary" size="sm">Today</x-btn>

                    <x-btn :href="route('catering.index', ['month' => $next])" variant="secondary" size="sm"
                           aria-label="Next month">
                        <x-icon name="arrow-left" class="size-5 rotate-180"/>
                    </x-btn>
                </div>

                <p class="text-lg font-bold text-slate-900 dark:text-white">{{ $month->format('F Y') }}</p>

                <div class="flex flex-wrap items-center gap-3 text-xs font-semibold">
                    @foreach (CateringStatus::cases() as $status)
                        <span class="flex items-center gap-1.5 text-slate-500 dark:text-slate-400">
                            <span class="size-2.5 rounded-full {{ $status->dotClasses() }}"></span>
                            {{ $status->label() }}
                        </span>
                    @endforeach
                </div>
            </div>

            <div class="overflow-x-auto">
                <div class="min-w-3xl">
                    <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                            <div class="px-3 py-2 text-center text-xs font-bold tracking-wider text-slate-500 uppercase
                                        dark:text-slate-400">
                                {{ $weekday }}
                            </div>
                        @endforeach
                    </div>

                    <div class="grid grid-cols-7">
                        @foreach ($days as $day)
                            <div class="min-h-32 border-r border-b border-slate-100 p-2 dark:border-slate-800
                                        {{ $day['in_month'] ? '' : 'bg-slate-50/60 dark:bg-slate-900/40' }}">
                                <div class="flex items-center justify-between">
                                    <span class="inline-flex size-7 items-center justify-center rounded-full text-sm font-bold
                                                 {{ $day['is_today']
                                                     ? 'bg-brand-600 text-white'
                                                     : ($day['in_month'] ? 'text-slate-700 dark:text-slate-200' : 'text-slate-400 dark:text-slate-600') }}">
                                        {{ $day['date']->day }}
                                    </span>

                                    {{-- Adding a booking starts on the day tapped. --}}
                                    <a href="{{ route('catering.create', ['date' => $day['date']->toDateString()]) }}"
                                       class="rounded-md p-1 text-slate-400 transition hover:bg-slate-100
                                              hover:text-brand-600 dark:hover:bg-slate-800"
                                       aria-label="Add a booking on {{ $day['date']->format('j F') }}">
                                        <x-icon name="plus" class="size-4"/>
                                    </a>
                                </div>

                                <div class="mt-1.5 space-y-1">
                                    @foreach ($day['bookings'] as $booking)
                                        <button type="button"
                                                x-on:click="show({{ $booking->id }})"
                                                class="flex w-full items-center gap-1.5 rounded-lg px-2 py-1.5 text-left
                                                       transition hover:bg-slate-100 dark:hover:bg-slate-800">
                                            <span class="size-2 shrink-0 rounded-full {{ $booking->status->dotClasses() }}"></span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-xs font-bold text-slate-800 dark:text-slate-100">
                                                    {{ $booking->customerLabel() }}
                                                </span>
                                                <span class="block truncate text-[0.7rem] text-slate-500 dark:text-slate-400">
                                                    {{ collect([
                                                        $booking->pax ? $booking->pax.' pax' : null,
                                                        $booking->timeLabel(),
                                                    ])->filter()->join(' · ') }}
                                                </span>
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-card>

        {{-- The booking, opened in place. --}}
        <template x-teleport="body">
            <div x-show="open"
                 x-cloak
                 x-on:keydown.escape.window="close()"
                 class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/60 p-4 backdrop-blur-sm sm:items-center"
                 role="dialog"
                 aria-modal="true">
                <div x-show="open"
                     x-transition
                     x-on:click.outside="close()"
                     class="max-h-[85dvh] w-full max-w-lg overflow-y-auto rounded-3xl bg-white shadow-2xl dark:bg-slate-900">

                    <div class="flex items-start justify-between gap-4 border-b border-slate-100 p-6 dark:border-slate-800">
                        <div class="min-w-0">
                            <h2 class="truncate text-2xl font-bold text-slate-900 dark:text-white"
                                x-text="open?.customer"></h2>
                            <p class="mt-1 font-mono text-sm text-slate-500 dark:text-slate-400"
                               x-text="open?.reference"></p>
                        </div>

                        <span class="shrink-0 rounded-full px-3 py-1 text-sm font-semibold"
                              :class="open?.status_classes"
                              x-text="open?.status"></span>
                    </div>

                    <div class="space-y-5 p-6">
                        <dl class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="text-slate-500 dark:text-slate-400">Date</dt>
                                <dd class="mt-0.5 font-semibold text-slate-900 dark:text-white" x-text="open?.date"></dd>
                            </div>

                            <div x-show="open?.time">
                                <dt class="text-slate-500 dark:text-slate-400">Time</dt>
                                <dd class="mt-0.5 font-semibold text-slate-900 dark:text-white" x-text="open?.time"></dd>
                            </div>

                            <div x-show="open?.pax">
                                <dt class="text-slate-500 dark:text-slate-400">Guests</dt>
                                <dd class="mt-0.5 font-semibold text-slate-900 dark:text-white" x-text="open?.pax"></dd>
                            </div>

                            <div x-show="open?.phone">
                                <dt class="text-slate-500 dark:text-slate-400">Mobile</dt>
                                <dd class="mt-0.5 font-semibold text-slate-900 dark:text-white" x-text="open?.phone"></dd>
                            </div>

                            <div x-show="open?.venue" class="col-span-2">
                                <dt class="text-slate-500 dark:text-slate-400">Venue</dt>
                                <dd class="mt-0.5 font-semibold text-slate-900 dark:text-white" x-text="open?.venue"></dd>
                            </div>
                        </dl>

                        <div x-show="open?.items?.length">
                            <p class="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                The sheet
                            </p>

                            <ul class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                                <template x-for="item in (open?.items ?? [])" :key="item.name + item.amount">
                                    <li class="flex items-baseline justify-between gap-4 py-2.5">
                                        <span class="min-w-0">
                                            <span class="block font-semibold text-slate-800 dark:text-slate-100"
                                                  x-text="item.name"></span>
                                            <span x-show="item.note"
                                                  class="block text-xs text-amber-700 dark:text-amber-400"
                                                  x-text="item.note"></span>
                                        </span>
                                        <span class="shrink-0 text-right">
                                            <span class="block font-bold tabular-nums text-slate-900 dark:text-white"
                                                  x-text="item.amount"></span>
                                            <span x-show="item.line_total"
                                                  class="block text-xs text-slate-500 dark:text-slate-400"
                                                  x-text="item.line_total"></span>
                                        </span>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        <p x-show="!open?.items?.length" class="text-sm text-slate-500 dark:text-slate-400">
                            Nothing has been put on this sheet yet.
                        </p>

                        <div x-show="open?.has_total"
                             class="flex items-baseline justify-between border-t border-slate-100 pt-4 dark:border-slate-800">
                            <span class="text-lg font-bold text-slate-900 dark:text-white">Total</span>
                            <span class="text-2xl font-bold tabular-nums text-slate-900 dark:text-white"
                                  x-text="open?.total"></span>
                        </div>

                        <div x-show="open?.note" class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/50">
                            <p class="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">Note</p>
                            <p class="mt-1 whitespace-pre-line text-sm text-slate-800 dark:text-slate-100"
                               x-text="open?.note"></p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3 border-t border-slate-100 p-6 dark:border-slate-800">
                        <x-btn ::href="open?.edit_url" size="md" class="flex-1">Edit booking</x-btn>
                        <x-btn ::href="open?.sheet_url" variant="secondary" size="md" class="flex-1">
                            Kitchen sheet
                        </x-btn>
                        <x-btn type="button" variant="ghost" size="md" x-on:click="close()">Close</x-btn>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-layouts.app>
