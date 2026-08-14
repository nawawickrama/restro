@props(['name' => null, 'size' => null])

@php
    $type = $attributes->get('type', 'text');

    // Tapping anywhere on a date field opens the picker, rather than making a
    // cashier hit the small calendar icon. Older browsers ignore showPicker().
    $datePicker = in_array($type, ['date', 'time', 'datetime-local', 'month', 'week'], true)
        ? ['x-on:click' => 'try { $el.showPicker() } catch (e) {}']
        : [];
@endphp

<input {{ $attributes->merge($datePicker)->merge([
    'id' => $name,
    'name' => $name,
    'class' => 'field-control'.($size === 'lg' ? ' field-control-lg' : ''),
]) }}>
