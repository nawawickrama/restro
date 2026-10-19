import { visibleItems } from './menu-items';

/**
 * The menu on its own, for checking a price or what is on without starting
 * an order. Read-only: nothing on this screen talks to the server.
 */
export default function posMenu({ categories }) {
    return {
        categories,

        activeCategory: categories.length ? categories[0].id : null,
        search: '',

        init() {
            // No order is being served from here, so the customer's screen
            // stays on its welcome.
            window.showOnCustomerDisplay?.({ screen: 'idle' });
        },

        get items() {
            return visibleItems(this.categories, this.activeCategory, this.search);
        },

        selectCategory(id) {
            this.activeCategory = id;
            this.search = '';
        },
    };
}
