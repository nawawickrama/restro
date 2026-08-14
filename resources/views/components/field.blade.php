@props(['label' => null, 'name' => null, 'hint' => null, 'required' => false])

{{-- Label, control, hint and error as one block, so no form has to wire up
     validation display by hand. --}}
<div {{ $attributes->only('class')->merge(['class' => 'space-y-1.5']) }}>
    @if ($label)
        <label @if ($name) for="{{ $name }}" @endif
               class="flex items-center gap-1.5 text-sm font-semibold text-slate-700 dark:text-slate-300">
            {{ $label }}

            @if ($required)
                <span class="text-rose-500" aria-hidden="true">*</span>
                <span class="sr-only">(required)</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $hint }}</p>
    @endif

    @if ($name)
        @error($name)
            <p class="flex items-start gap-1.5 text-sm font-medium text-rose-600 dark:text-rose-400">
                <x-icon name="x" class="mt-0.5 size-4 shrink-0"/>
                {{ $message }}
            </p>
        @enderror
    @endif
</div>
