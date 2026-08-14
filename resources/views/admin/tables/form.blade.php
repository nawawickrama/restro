@php($editing = $table->exists)

<x-layouts.app :title="$editing ? 'Edit table' : 'New table'">
    <x-slot:header>{{ $editing ? 'Edit table' : 'New table' }}</x-slot:header>

    <x-slot:actions>
        <x-btn :href="route('tables.index')" variant="secondary">
            <x-icon name="arrow-left"/>
            Back
        </x-btn>
    </x-slot:actions>

    <x-card>
        <form action="{{ $editing ? route('tables.update', $table) : route('tables.store') }}"
              method="POST"
              class="space-y-6">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-6">
                <x-field label="Name" name="name" required hint="What the staff call it, such as “Table 5” or “Balcony 2”."
                         class="sm:col-span-2 xl:col-span-3">
                    <x-input name="name" :value="old('name', $table->name)" required autofocus/>
                </x-field>

                <x-field label="Seats" name="seats" hint="Optional. Shown on the POS table card."
                         class="xl:col-span-1">
                    <x-input name="seats" type="number" min="1" :value="old('seats', $table->seats)"/>
                </x-field>

                <x-field label="Display order" name="sort_order" hint="Lower numbers appear first on the POS."
                         class="xl:col-span-2">
                    <x-input name="sort_order" type="number" min="0" :value="old('sort_order', $table->sort_order ?? 0)"/>
                </x-field>
            </div>

            <x-toggle name="is_active"
                      :checked="$table->is_active ?? true"
                      label="In service"
                      hint="Inactive tables are hidden from the POS."/>

            <div class="flex flex-col gap-3 border-t border-slate-100 pt-6 sm:flex-row-reverse sm:justify-start
                        dark:border-slate-800">
                <x-btn size="lg" class="sm:w-44">{{ $editing ? 'Save changes' : 'Create table' }}</x-btn>
                <x-btn :href="route('tables.index')" variant="secondary" size="lg" class="sm:w-44">Cancel</x-btn>
            </div>
        </form>
    </x-card>
</x-layouts.app>
