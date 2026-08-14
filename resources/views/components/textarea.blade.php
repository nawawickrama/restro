@props(['name' => null, 'rows' => 3])

{{-- Grows with its content where the browser supports field-sizing, so a long
     note is not read through a two-line letterbox. --}}
<textarea {{ $attributes->merge([
    'id' => $name,
    'name' => $name,
    'rows' => $rows,
    'class' => 'field-control',
]) }}>{{ $slot }}</textarea>
