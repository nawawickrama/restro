@props(['name' => null])

<select {{ $attributes->merge([
    'id' => $name,
    'name' => $name,
    'class' => 'field-control',
]) }}>
    {{ $slot }}
</select>
