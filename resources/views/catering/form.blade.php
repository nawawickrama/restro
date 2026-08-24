@php
    use App\Enums\CateringStatus;

    $editing = $booking->exists;

    // The catalogue, handed to the row editor so picking a dish fills in its
    // usual unit and price without a round trip.
    $cataloguePayload = $catalogue->map(fn ($item) => [
        'id' => $item->id,
        'name' => $item->name,
        'unit' => $item->default_unit->value,
        'price' => $item->price !== null ? (float) $item->price : null,
    ])->values();

    $existingLines = old('items', $editing
        ? $booking->items->map(fn ($line) => [
            'catering_item_id' => $line->catering_item_id,
            'name' => $line->name,
            'quantity' => rtrim(rtrim(number_format((float) $line->quantity, 3, '.', ''), '0'), '.'),
            'unit' => $line->unit->value,
            'unit_price' => $line->unit_price !== null ? (float) $line->unit_price : null,
            'note' => $line->note,
        ])->values()->all()
        : []);
@endphp

<x-layouts.app :title="$editing ? 'Edit booking' : 'New catering booking'">
    <x-slot:header>{{ $editing ? 'Edit booking' : 'New catering booking' }}</x-slot:header>
    <x-slot:subheader>
        {{ $editing ? $booking->reference : 'Who it is for, how many, and what the kitchen is cooking' }}
    </x-slot:subheader>

    <x-slot:actions>
        <x-btn :href="route('catering.index')" variant="secondary">
            <x-icon name="arrow-left"/>
            Back to the diary
        </x-btn>
    </x-slot:actions>

    <form action="{{ $editing ? route('catering.update', $booking) : route('catering.store') }}"
          method="POST"
          class="space-y-6">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-card class="space-y-6">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">The booking</h2>

            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-6">
                <x-field label="Event date" name="event_date" required class="xl:col-span-2">
                    <x-input name="event_date" type="date"
                             :value="old('event_date', $booking->event_date?->toDateString())" required/>
                </x-field>

                <x-field label="Time" name="event_time" hint="Optional." class="xl:col-span-2">
                    <x-input name="event_time" type="time" :value="old('event_time', $booking->event_time)"/>
                </x-field>

                <x-field label="Guests" name="pax" hint="Number of pax." class="xl:col-span-2">
                    <x-input name="pax" type="number" min="1" inputmode="numeric"
                             :value="old('pax', $booking->pax)"/>
                </x-field>

                <x-field label="Customer name" name="customer_name" class="xl:col-span-2">
                    <x-input name="customer_name" :value="old('customer_name', $booking->customer_name)"
                             autocomplete="off"/>
                </x-field>

                <x-field label="Mobile number" name="customer_phone"
                         hint="Files the booking against the customer record."
                         class="xl:col-span-2">
                    <x-input name="customer_phone" type="tel" inputmode="tel"
                             :value="old('customer_phone', $booking->customer_phone)" autocomplete="off"/>
                </x-field>

                <x-field label="Status" name="status" class="xl:col-span-2">
                    <x-select name="status">
                        @foreach (CateringStatus::cases() as $status)
                            <option value="{{ $status->value }}"
                                @selected(old('status', $booking->status?->value ?? CateringStatus::Enquiry->value) === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field label="Venue" name="venue" hint="Where the food is going." class="sm:col-span-2 xl:col-span-6">
                    <x-input name="venue" :value="old('venue', $booking->venue)"/>
                </x-field>

                <x-field label="Note" name="note" hint="Anything the kitchen or the driver needs to know."
                         class="sm:col-span-2 xl:col-span-6">
                    <x-textarea name="note" rows="2">{{ old('note', $booking->note) }}</x-textarea>
                </x-field>
            </div>
        </x-card>

        {{-- The sheet. Rows are added and removed in place; each carries its own
             quantity and unit, because the same kitchen sends out curry by the
             kilo and rice by the portion. --}}
        <x-card padded="false"
                x-data="cateringSheet({
                    catalogue: {{ Js::from($cataloguePayload) }},
                    lines: {{ Js::from($existingLines) }},
                    currency: {{ Js::from($settings->currencySymbol()) }}
                })">
            <div class="flex flex-wrap items-center justify-between gap-3 p-5 sm:p-6">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">What we are cooking</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        A quantity and a unit for each dish — 5 kg, 200 portions, 30 litres.
                    </p>
                </div>

                <x-btn type="button" variant="secondary" size="md" x-on:click="addLine()">
                    <x-icon name="plus" class="size-5"/>
                    Add a dish
                </x-btn>
            </div>

            <div class="border-t border-slate-100 dark:border-slate-800">
                <p x-show="lines.length === 0"
                   class="px-5 py-10 text-center text-base text-slate-500 sm:px-6 dark:text-slate-400">
                    Nothing on the sheet yet. Add the first dish.
                </p>

                <template x-for="(line, index) in lines" :key="line.key">
                    <div class="grid gap-4 border-b border-slate-100 p-5 last:border-0 sm:p-6
                                lg:grid-cols-12 dark:border-slate-800">
                        <div class="lg:col-span-4">
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">
                                Dish
                            </label>

                            {{-- Picked from the catalogue, or typed for a one-off. --}}
                            <input type="hidden" :name="`items[${index}][catering_item_id]`"
                                   :value="line.catering_item_id ?? ''">

                            <input type="text"
                                   :name="`items[${index}][name]`"
                                   x-model="line.name"
                                   :list="`catering-catalogue`"
                                   x-on:input="matchCatalogue(line)"
                                   placeholder="Start typing, or pick from the list"
                                   class="field-control">
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">
                                Quantity
                            </label>
                            <input type="number" step="0.001" min="0.001" inputmode="decimal"
                                   :name="`items[${index}][quantity]`"
                                   x-model="line.quantity"
                                   class="field-control">
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">
                                Unit
                            </label>
                            <select :name="`items[${index}][unit]`" x-model="line.unit" class="field-control">
                                @foreach ($units as $unit)
                                    <option value="{{ $unit->value }}">{{ $unit->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">
                                Price per unit
                            </label>
                            <input type="number" step="0.01" min="0" inputmode="decimal"
                                   :name="`items[${index}][unit_price]`"
                                   x-model="line.unit_price"
                                   placeholder="Optional"
                                   class="field-control">
                        </div>

                        <div class="flex items-end gap-2 lg:col-span-2">
                            <div class="min-w-0 flex-1">
                                <label class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">
                                    Line
                                </label>
                                <p class="flex h-12 items-center font-bold tabular-nums text-slate-900 dark:text-white"
                                   x-text="lineTotal(line)"></p>
                            </div>

                            <button type="button"
                                    x-on:click="removeLine(index)"
                                    class="mb-1 flex size-12 shrink-0 items-center justify-center rounded-xl text-rose-600
                                           transition hover:bg-rose-50 dark:hover:bg-rose-500/10"
                                    :aria-label="`Remove ${line.name || 'this dish'}`">
                                <x-icon name="trash" class="size-5"/>
                            </button>
                        </div>

                        <div class="lg:col-span-12">
                            <input type="text"
                                   :name="`items[${index}][note]`"
                                   x-model="line.note"
                                   placeholder="Note for this dish — less spice, deliver separately…"
                                   class="field-control">
                        </div>
                    </div>
                </template>

                <datalist id="catering-catalogue">
                    @foreach ($catalogue as $item)
                        <option value="{{ $item->name }}"></option>
                    @endforeach
                </datalist>
            </div>

            <div class="flex items-baseline justify-between border-t border-slate-100 p-5 sm:p-6 dark:border-slate-800">
                <span class="text-lg font-bold text-slate-900 dark:text-white">Total</span>
                <span class="text-2xl font-bold tabular-nums text-slate-900 dark:text-white" x-text="total()"></span>
            </div>
        </x-card>

        <div class="flex flex-col gap-3 sm:flex-row-reverse sm:justify-start">
            <x-btn size="lg" class="sm:w-44">{{ $editing ? 'Save booking' : 'Create booking' }}</x-btn>
            <x-btn :href="route('catering.index')" variant="secondary" size="lg" class="sm:w-44">Cancel</x-btn>

            @if ($editing)
                <x-confirm-form :action="route('catering.destroy', $booking)"
                                method="DELETE"
                                title="Delete {{ $booking->reference }}?"
                                message="The booking and its sheet are removed. This cannot be undone."
                                confirm="Delete booking"
                                size="lg"
                                class="sm:ml-auto sm:w-44">
                    <x-slot:trigger>Delete</x-slot:trigger>
                </x-confirm-form>
            @endif
        </div>
    </form>
</x-layouts.app>
