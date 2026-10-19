/**
 * Which menu items a screen shows: everything matching the search, across all
 * categories, while one is typed; otherwise the chosen category's items.
 *
 * Shared by the order screen and the menu screen so the two always agree on
 * what a search finds.
 */
export function visibleItems(categories, activeCategory, search) {
    const term = search.trim().toLowerCase();

    if (term.length) {
        return categories
            .flatMap((category) => category.items)
            .filter((item) => item.name.toLowerCase().includes(term));
    }

    const category = categories.find((c) => c.id === activeCategory);

    return category ? category.items : [];
}
