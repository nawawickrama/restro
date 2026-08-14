@props(['name' => null, 'size' => null])

{{-- A search box with the magnifier inside it. The slot is for a clear button
     or anything else that belongs at the right-hand end. --}}
<div class="relative">
    <x-icon name="search"
            class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-slate-400
                   {{ $size === 'lg' ? 'size-6' : 'size-5' }}"/>

    <input {{ $attributes->merge([
        'type' => 'search',
        'id' => $name,
        'name' => $name,
        'autocomplete' => 'off',
        'class' => 'field-control pl-12'.($size === 'lg' ? ' min-h-14 pr-14' : ''),
    ]) }}>

    {{ $slot }}
</div>
