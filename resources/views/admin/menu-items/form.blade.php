@php($editing = $item->exists)

<x-layouts.app :title="$editing ? 'Edit menu item' : 'New menu item'">
    <x-slot:header>{{ $editing ? 'Edit menu item' : 'New menu item' }}</x-slot:header>

    <x-slot:actions>
        <x-btn :href="route('menu-items.index')" variant="secondary">
            <x-icon name="arrow-left"/>
            Back
        </x-btn>
    </x-slot:actions>

    <x-card>
        <form action="{{ $editing ? route('menu-items.update', $item) : route('menu-items.store') }}"
              method="POST"
              enctype="multipart/form-data"
              class="space-y-6">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            {{-- A six-column track, so each field gets a width that suits it
                 rather than an equal share: a price box does not need as much
                 room as a name. Collapses to two columns, then one. --}}
            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-6">
                <x-field label="Name" name="name" required class="xl:col-span-2">
                    <x-input name="name" :value="old('name', $item->name)" required autofocus/>
                </x-field>

                <x-field label="Category" name="category_id" required class="xl:col-span-2">
                    <x-select name="category_id" required>
                        <option value="">Choose a category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id', $item->category_id) == $category->id)>
                                {{ $category->name }}@unless ($category->is_active) (disabled)@endunless
                            </option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field label="Price" name="price" required
                         :hint="$editing ? 'Affects new orders only.' : null">
                    <x-input name="price"
                             type="number"
                             step="0.01"
                             min="0"
                             inputmode="decimal"
                             :value="old('price', $item->price ? (float) $item->price : null)"
                             required/>
                </x-field>

                <x-field label="Display order" name="sort_order" hint="Lowest first.">
                    <x-input name="sort_order" type="number" min="0" :value="old('sort_order', $item->sort_order ?? 0)"/>
                </x-field>
            </div>

            <x-field label="Description" name="description" hint="Optional.">
                <x-textarea name="description" rows="2">{{ old('description', $item->description) }}</x-textarea>
            </x-field>

            {{-- Optional photo. The POS shows it on the item tile, which makes
                 a busy menu much faster to scan than names alone. --}}
            <x-field label="Photo"
                     name="image"
                     :hint="'Optional. JPG, PNG or WebP, up to '.App\Services\MenuItemImageService::maxUploadLabel().'.'">
                <div x-data="{
                        preview: {{ Js::from($item->imageUrl()) }},
                        hadImage: {{ Js::from((bool) $item->imageUrl()) }},
                        removing: false,
                        tooBig: false,
                        maxBytes: {{ App\Services\MenuItemImageService::maxUploadKilobytes() * 1024 }},
                        choose(event) {
                            const file = event.target.files[0];
                            if (!file) return;

                            // Catch it here rather than after a slow upload the
                            // server was always going to reject.
                            if (file.size > this.maxBytes) {
                                this.tooBig = true;
                                $refs.file.value = '';
                                return;
                            }

                            this.tooBig = false;
                            this.preview = URL.createObjectURL(file);
                            this.removing = false;
                        },
                        clear() {
                            this.preview = null;
                            this.tooBig = false;
                            this.removing = this.hadImage;
                            $refs.file.value = '';
                        },
                     }"
                     class="flex flex-wrap items-center gap-4">

                    <input type="hidden" name="remove_image" :value="removing ? 1 : 0">

                    <div class="flex size-28 shrink-0 items-center justify-center overflow-hidden rounded-2xl border
                                border-slate-200 bg-slate-100 dark:border-slate-700 dark:bg-slate-800">
                        <img x-show="preview" x-cloak :src="preview" alt=""
                             class="size-full object-cover">
                        <x-icon x-show="!preview" name="menu-book" class="size-8 text-slate-400"/>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <x-btn type="button" variant="secondary" size="md" x-on:click="$refs.file.click()">
                            <span x-text="preview ? 'Change photo' : 'Choose photo'"></span>
                        </x-btn>

                        <x-btn type="button" variant="ghost" size="md" x-show="preview" x-cloak x-on:click="clear()">
                            Remove
                        </x-btn>
                    </div>

                    <input type="file"
                           name="image"
                           id="image"
                           accept="image/jpeg,image/png,image/webp"
                           x-ref="file"
                           x-on:change="choose($event)"
                           class="sr-only">

                    <p x-show="tooBig"
                       x-cloak
                       class="w-full text-sm font-medium text-rose-600 dark:text-rose-400"
                       role="alert">
                        That photo is larger than {{ App\Services\MenuItemImageService::maxUploadLabel() }}.
                        Pick a smaller one, or ask your developer to raise the upload limit in php.ini.
                    </p>
                </div>
            </x-field>

            <x-toggle name="is_active"
                      :checked="$item->is_active ?? true"
                      label="Available for sale"
                      hint="Disabled items cannot be added to new orders."/>

            {{-- Save sits on the right on a wide screen, where the eye finishes
                 the form; on a phone it stacks on top, where the thumb is. --}}
            <div class="flex flex-col gap-3 border-t border-slate-100 pt-6 sm:flex-row-reverse sm:justify-start
                        dark:border-slate-800">
                <x-btn size="lg" class="sm:w-44">{{ $editing ? 'Save changes' : 'Create item' }}</x-btn>
                <x-btn :href="route('menu-items.index')" variant="secondary" size="lg" class="sm:w-44">Cancel</x-btn>
            </div>
        </form>
    </x-card>
</x-layouts.app>
