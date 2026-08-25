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

                <x-field label="Add artwork" name="images"
                         :hint="'One image per package — Package 1, Package 2. JPG, PNG or WebP, up to '.App\Support\UploadLimit::label().' each. Choose several at once; each is named from its filename and can be renamed below.'"
                         class="sm:col-span-2 xl:col-span-6">
                    <input type="file"
                           name="images[]"
                           id="images"
                           accept="image/jpeg,image/png,image/webp"
                           multiple
                           class="field-control file:mr-4 file:rounded-lg file:border-0 file:bg-slate-100
                                  file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-700
                                  dark:file:bg-slate-700 dark:file:text-slate-100">
                </x-field>
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
                <form action="{{ route('catering-catalogues.pages.update', $catalogue) }}" method="POST"
                      class="border-t border-slate-100 dark:border-slate-800">
                    @csrf
                    @method('PUT')

                    <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6 lg:grid-cols-3">
                        @foreach ($catalogue->images as $image)
                            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700">
                                <a href="{{ $image->url() }}" target="_blank" class="block">
                                    <img src="{{ $image->url() }}" alt="{{ $image->displayTitle($loop->iteration) }}"
                                         loading="lazy"
                                         class="aspect-4/3 w-full bg-slate-100 object-cover dark:bg-slate-800">
                                </a>

                                <div class="space-y-3 p-3">
                                    <input type="text"
                                           name="pages[{{ $image->id }}][title]"
                                           value="{{ old("pages.{$image->id}.title", $image->title) }}"
                                           placeholder="Package {{ $loop->iteration }}"
                                           class="field-control">

                                    <div class="flex items-center gap-2">
                                        <label class="sr-only" for="order-{{ $image->id }}">Order</label>
                                        <input type="number" min="0" id="order-{{ $image->id }}"
                                               name="pages[{{ $image->id }}][sort_order]"
                                               value="{{ old("pages.{$image->id}.sort_order", $image->sort_order) }}"
                                               class="field-control w-24"
                                               title="Display order">

                                        <span class="flex-1"></span>

                                        <x-confirm-form :action="route('catering-catalogues.pages.destroy', [$catalogue, $image])"
                                                        method="DELETE"
                                                        title="Remove this artwork?"
                                                        message="The image is deleted and disappears from the public catalogue."
                                                        confirm="Remove artwork"
                                                        size="sm">
                                            <x-slot:trigger>
                                                <x-icon name="trash" class="size-5"/>
                                            </x-slot:trigger>
                                        </x-confirm-form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="border-t border-slate-100 p-5 sm:p-6 dark:border-slate-800">
                        <x-btn size="md">Save names &amp; order</x-btn>
                    </div>
                </form>
            @endif
        </x-card>
    @endif
</x-layouts.app>
