@props(['name', 'checked' => false, 'label' => 'Active', 'hint' => null])

{{-- A large switch rather than a small checkbox: this gets tapped with a
     finger, and the hidden field means "off" is actually submitted. --}}
<label class="flex cursor-pointer items-center gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4
              dark:border-slate-800 dark:bg-slate-800/50">
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox"
           id="{{ $name }}"
           name="{{ $name }}"
           value="1"
           @checked(old($name, $checked))
           {{ $attributes }}
           class="field-check">
    <span>
        <span class="block text-base font-semibold text-slate-800 dark:text-slate-100">{{ $label }}</span>
        @if ($hint)
            <span class="block text-sm text-slate-500 dark:text-slate-400">{{ $hint }}</span>
        @endif
    </span>
</label>
