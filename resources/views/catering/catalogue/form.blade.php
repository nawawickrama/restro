@php($editing = $catalogue->exists)

<x-layouts.app :title="$editing ? 'Edit catalogue' : 'New catalogue category'">
    <x-slot:header>{{ $editing ? $catalogue->name : 'New catalogue category' }}</x-slot:header>
    <x-slot:subheader>
        {{ $editing ? 'Its details and the artwork customers see' : 'A package customers can be shown' }}
    </x-slot:subheader>

    <x-slot:actions>
        <x-btn :href="route('catering-catalogues.index')" variant="secondary">
            <x-icon name="arrow-left"/>
            Back
        </x-btn>

        @if ($editing)
            <x-btn :href="route('catalogue.show', $catalogue)" variant="secondary" target="_blank">
                <x-icon name="search"/>
                View public page
            </x-btn>
        @endif
    </x-slot:actions>

    <x-card>
        <form action="{{ $editing ? route('catering-catalogues.update', $catalogue) : route('catering-catalogues.store') }}"
              method="POST"
              enctype="multipart/form-data"
              class="space-y-6">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-6">
                <x-field label="Category name" name="name" required
                         hint="What the package is called — Wedding packages, Office lunches."
                         class="xl:col-span-4">
                    <x-input name="name" :value="old('name', $catalogue->name)" required autofocus/>
                </x-field>

                <x-field label="Display order" name="sort_order" hint="Lowest first." class="xl:col-span-2">
                    <x-input name="sort_order" type="number" min="0"
                             :value="old('sort_order', $catalogue->sort_order ?? 0)"/>
                </x-field>

                <x-field label="Description" name="description"
                         hint="Optional. A line or two shown above the artwork."
                         class="sm:col-span-2 xl:col-span-6">
                    <x-textarea name="description" rows="2">{{ old('description', $catalogue->description) }}</x-textarea>
                </x-field>

            </div>

            {{-- One row per artwork: the image and the name it goes out under.
                 Rows are added and removed before anything is saved, so a
                 category can be created with its packages already named. --}}
            <div x-data="catalogueUploader({ titles: {{ Js::from(collect(old('artworks', []))->pluck('title')->all()) }} })"
                 class="space-y-4 border-t border-slate-100 pt-6 dark:border-slate-800">

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Add artwork</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            One image per package, each with the name customers see.
                            Up to {{ App\Support\UploadLimit::label() }} each.
                        </p>
                    </div>

                    <x-btn type="button" variant="secondary" size="md" x-on:click="addRow()">
                        <x-icon name="plus" class="size-5"/>
                        Add another
                    </x-btn>
                </div>

                <template x-for="(row, index) in rows" :key="row.key">
                    <div class="flex flex-wrap items-end gap-4 rounded-2xl border border-slate-200 p-4
                                dark:border-slate-700">
                        {{-- What was picked, so the wrong file is obvious. --}}
                        <div class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-xl
                                    border border-slate-200 bg-slate-100 dark:border-slate-700 dark:bg-slate-800">
                            <img x-show="row.preview" x-cloak :src="row.preview" alt="" class="size-full object-cover">
                            <x-icon x-show="!row.preview" name="menu-book" class="size-6 text-slate-400"/>
                        </div>

                        <div class="min-w-48 flex-1">
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">
                                Image
                            </label>
                            <input type="file"
                                   :name="`artworks[${index}][image]`"
                                   accept="image/jpeg,image/png,image/webp"
                                   x-on:change="choose(row, $event)"
                                   class="field-control file:mr-3 file:rounded-md file:border-0 file:bg-slate-100
                                          file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-slate-700
                                          dark:file:bg-slate-700 dark:file:text-slate-100">
                        </div>

                        <div class="min-w-48 flex-1">
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">
                                Title
                            </label>
                            <input type="text"
                                   :name="`artworks[${index}][title]`"
                                   x-model="row.title"
                                   :placeholder="`Package ${index + 1}`"
                                   maxlength="120"
                                   class="field-control">
                        </div>

                        <button type="button"
                                x-on:click="removeRow(index)"
                                class="flex size-12 shrink-0 items-center justify-center rounded-xl text-rose-600
                                       transition hover:bg-rose-50 dark:hover:bg-rose-500/10"
                                aria-label="Remove this row">
                            <x-icon name="trash" class="size-5"/>
                        </button>
                    </div>
                </template>

                @error('artworks.*.image')
                    <p class="text-sm font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <x-toggle name="is_active"
                      :checked="$catalogue->is_active ?? true"
                      label="Show on the public catalogue"
                      hint="Hidden categories keep their artwork but disappear from the public page."/>

            <div class="flex flex-col gap-3 border-t border-slate-100 pt-6 sm:flex-row-reverse sm:justify-start
                        dark:border-slate-800">
                <x-btn size="lg" class="sm:w-44">{{ $editing ? 'Save changes' : 'Create category' }}</x-btn>
                <x-btn :href="route('catering-catalogues.index')" variant="secondary" size="lg" class="sm:w-44">
                    Cancel
                </x-btn>

                @if ($editing)
                    <x-confirm-form :action="route('catering-catalogues.destroy', $catalogue)"
                                    method="DELETE"
                                    title="Delete {{ $catalogue->name }}?"
                                    message="The category and all of its artwork are removed, and its public link stops working."
                                    confirm="Delete catalogue"
                                    size="lg"
                                    class="sm:ml-auto sm:w-44">
                        <x-slot:trigger>Delete</x-slot:trigger>
                    </x-confirm-form>
                @endif
            </div>
        </form>
    </x-card>

    @if ($editing)
        <x-card padded="false">
            <div class="flex flex-wrap items-center justify-between gap-3 p-5 sm:p-6">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Packages in this category</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Each artwork is its own package. Name them as customers should see them.
                    </p>
                </div>

                <p class="font-mono text-sm text-slate-500 dark:text-slate-400">
                    {{ url('/catalogue/'.$catalogue->slug) }}
                </p>
            </div>

            @if ($catalogue->images->isEmpty())
                <p class="border-t border-slate-100 px-5 py-10 text-center text-base text-slate-500
                          sm:px-6 dark:border-slate-800 dark:text-slate-400">
                    No artwork yet. Choose the images above and save.
                </p>
            @else
                {{-- Each artwork is edited on its own: its name, its place, and
                     a revised image if one has come in. A form per artwork, so
                     replacing one cannot disturb another — and so no form is
                     nested inside another, which HTML does not allow. --}}
                <div class="grid gap-5 border-t border-slate-100 p-5 sm:grid-cols-2 sm:p-6 lg:grid-cols-3
                            dark:border-slate-800">
                    @foreach ($catalogue->images as $image)
                        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700">
                            <a href="{{ $image->url() }}" target="_blank" class="block">
                                <img src="{{ $image->url() }}"
                                     alt="{{ $image->displayTitle($loop->iteration) }}"
                                     loading="lazy"
                                     class="aspect-4/3 w-full bg-slate-100 object-cover dark:bg-slate-800">
                            </a>

                            <form action="{{ route('catering-catalogues.pages.update', [$catalogue, $image]) }}"
                                  method="POST"
                                  enctype="multipart/form-data"
                                  class="space-y-3 p-4">
                                @csrf

                                <x-field label="Title" name="title">
                                    <x-input name="title"
                                             :value="old('title', $image->title)"
                                             placeholder="Package {{ $loop->iteration }}"/>
                                </x-field>

                                <div class="grid grid-cols-2 gap-3">
                                    <x-field label="Order" name="sort_order">
                                        <x-input name="sort_order" type="number" min="0"
                                                 :value="old('sort_order', $image->sort_order)"/>
                                    </x-field>

                                    <x-field label="Replace image" name="image">
                                        <input type="file"
                                               name="image"
                                               accept="image/jpeg,image/png,image/webp"
                                               class="field-control file:mr-3 file:rounded-md file:border-0
                                                      file:bg-slate-100 file:px-2 file:py-1 file:text-xs
                                                      file:font-semibold file:text-slate-700
                                                      dark:file:bg-slate-700 dark:file:text-slate-100">
                                    </x-field>
                                </div>

                                <div class="flex items-center gap-2 pt-1">
                                    <x-btn size="sm" class="flex-1">Save</x-btn>

                                    <x-confirm-form :action="route('catering-catalogues.pages.destroy', [$catalogue, $image])"
                                                    method="DELETE"
                                                    title="Remove {{ $image->displayTitle($loop->iteration) }}?"
                                                    message="The artwork is deleted and disappears from the public catalogue."
                                                    confirm="Remove artwork"
                                                    size="sm">
                                        <x-slot:trigger>
                                            <x-icon name="trash" class="size-5"/>
                                        </x-slot:trigger>
                                    </x-confirm-form>
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>
    @endif
</x-layouts.app>
