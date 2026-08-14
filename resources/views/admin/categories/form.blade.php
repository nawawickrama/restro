@php($editing = $category->exists)

<x-layouts.app :title="$editing ? 'Edit category' : 'New category'">
    <x-slot:header>{{ $editing ? 'Edit category' : 'New category' }}</x-slot:header>

    <x-slot:actions>
        <x-btn :href="route('categories.index')" variant="secondary">
            <x-icon name="arrow-left"/>
            Back
        </x-btn>
    </x-slot:actions>

    <x-card>
        <form action="{{ $editing ? route('categories.update', $category) : route('categories.store') }}"
              method="POST"
              class="space-y-6">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-6">
                <x-field label="Name" name="name" required class="xl:col-span-4">
                    <x-input name="name" :value="old('name', $category->name)" required autofocus/>
                </x-field>

                <x-field label="Display order" name="sort_order" hint="Lower numbers appear first in the POS."
                         class="xl:col-span-2">
                    <x-input name="sort_order" type="number" min="0" :value="old('sort_order', $category->sort_order ?? 0)"/>
                </x-field>
            </div>

            <x-toggle name="is_active"
                      :checked="$category->is_active ?? true"
                      label="Active"
                      hint="Disabled categories and their items disappear from the POS."/>

            <div class="flex flex-col gap-3 border-t border-slate-100 pt-6 sm:flex-row-reverse sm:justify-start
                        dark:border-slate-800">
                <x-btn size="lg" class="sm:w-44">{{ $editing ? 'Save changes' : 'Create category' }}</x-btn>
                <x-btn :href="route('categories.index')" variant="secondary" size="lg" class="sm:w-44">Cancel</x-btn>
            </div>
        </form>
    </x-card>
</x-layouts.app>
