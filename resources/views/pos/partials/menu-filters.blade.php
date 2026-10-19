{{-- Search and category tabs over the menu, shared by the order screen and the
     menu screen. Expects an Alpine scope with `search`, `categories`,
     `activeCategory` and `selectCategory()`. --}}
<div class="shrink-0 space-y-3 border-b border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <x-search-input x-model="search" placeholder="Search the menu"/>

    <div class="flex gap-2 overflow-x-auto no-scrollbar pb-1">
        <template x-for="category in categories" :key="category.id">
            <button type="button"
                    x-on:click="selectCategory(category.id)"
                    x-text="category.name"
                    :class="activeCategory === category.id && !search
                        ? 'bg-brand-600 text-white shadow-sm'
                        : 'bg-slate-200 text-slate-700 hover:bg-slate-300 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700'"
                    class="shrink-0 touch-target rounded-xl px-5 text-base font-bold transition active:scale-[0.98]">
            </button>
        </template>
    </div>
</div>
