@props(['href', 'icon', 'active' => false])

<a href="{{ $href }}"
   @if ($active) aria-current="page" @endif
   class="flex touch-target items-center gap-3 rounded-xl px-4 py-3 text-base font-semibold transition
          {{ $active
              ? 'bg-brand-600 text-white shadow-sm'
              : 'text-slate-600 hover:bg-slate-200/70 dark:text-slate-300 dark:hover:bg-slate-800' }}">
    <x-icon :name="$icon" class="size-6 shrink-0"/>
    <span>{{ $slot }}</span>
</a>
