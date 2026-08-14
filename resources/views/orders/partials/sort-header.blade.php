@php
    /**
     * A sortable column heading. Expects $sort, $label, $filters and optionally
     * $align from the including view.
     *
     * Clicking the active column flips direction; a new column starts newest or
     * largest first, which is what you almost always want.
     */
    $align = $align ?? 'left';
    $active = $filters->sort === $sort;
    $direction = $active && $filters->direction === 'desc' ? 'asc' : 'desc';
@endphp

<th scope="col"
    class="px-4 py-3 {{ $align === 'right' ? 'text-right' : 'text-left' }}"
    @if ($active) aria-sort="{{ $filters->direction === 'asc' ? 'ascending' : 'descending' }}" @endif>
    <a href="{{ request()->fullUrlWithQuery($filters->toQuery(['sort' => $sort, 'direction' => $direction])) }}"
       class="inline-flex items-center gap-1.5 text-xs font-bold tracking-wider uppercase transition
              {{ $active ? 'text-brand-600 dark:text-brand-400' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200' }}">
        {{ $label }}
        <span class="text-[0.6rem] leading-none {{ $active ? '' : 'opacity-30' }}" aria-hidden="true">
            {{ $active && $filters->direction === 'asc' ? '▲' : '▼' }}
        </span>
    </a>
</th>
