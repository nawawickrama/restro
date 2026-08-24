@php($editing = $item->exists)

<x-layouts.app :title="$editing ? 'Edit catering item' : 'New catering item'">
    <x-slot:header>{{ $editing ? 'Edit catering item' : 'New catering item' }}</x-slot:header>

    <x-slot:actions>
        <x-btn :href="route('catering-items.index')" variant="secondary">
            <x-icon name="arrow-left"/>
            Back
        </x-btn>
    </x-slot:actions>

    <x-card>
        <form action="{{ $editing ? route('catering-items.update', $item) : route('catering-items.store') }}"
              method="POST"
              class="space-y-6">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-6">
                <x-field label="Name" name="name" required class="xl:col-span-3">
                    <x-input name="name" :value="old('name', $item->name)" required autofocus/>
                </x-field>

                <x-field label="Usual unit" name="default_unit" required
                         hint="A booking can still order it in something else."
                         class="xl:col-span-1">
                    <x-select name="default_unit" required>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->value }}"
                                @selected(old('default_unit', $item->default_unit?->value) === $unit->value)>
                                {{ $unit->label() }}
                            </option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field label="Price per unit" name="price"
                         hint="Optional — a job can be booked before it is priced."
                         class="xl:col-span-2">
                    <x-input name="price" type="number" step="0.01" min="0" inputmode="decimal"
                             :value="old('price', $item->price !== null ? (float) $item->price : null)"/>
                </x-field>

                <x-field label="Display order" name="sort_order" hint="Lowest first."
                         class="xl:col-span-2">
                    <x-input name="sort_order" type="number" min="0" :value="old('sort_order', $item->sort_order ?? 0)"/>
                </x-field>
            </div>

            <x-toggle name="is_active"
                      :checked="$item->is_active ?? true"
                      label="Offered for catering"
                      hint="Switched-off items stay on past sheets but are not suggested on new ones."/>

            <div class="flex flex-col gap-3 border-t border-slate-100 pt-6 sm:flex-row-reverse sm:justify-start
                        dark:border-slate-800">
                <x-btn size="lg" class="sm:w-44">{{ $editing ? 'Save changes' : 'Add item' }}</x-btn>
                <x-btn :href="route('catering-items.index')" variant="secondary" size="lg" class="sm:w-44">Cancel</x-btn>
            </div>
        </form>
    </x-card>
</x-layouts.app>
