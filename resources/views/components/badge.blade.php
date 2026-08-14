@props(['classes' => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200'])

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold whitespace-nowrap '.$classes]) }}>
    {{ $slot }}
</span>
