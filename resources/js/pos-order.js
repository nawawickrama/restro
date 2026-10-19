import { visibleItems } from './menu-items';

/**
 * The order screen.
 *
 * Every tap talks to the server and the server sends the whole order back, so
 * the totals on screen are always the totals the database believes in. There is
 * no client-side price arithmetic anywhere in this file, on purpose.
 *
 * A new sale opens this screen before its order exists. The first item tapped
 * creates the order, and the server answers that one with the order's own
 * address instead of the order itself; the screen moves there and carries on.
 */

// Where the menu was left when the first item moved the screen to its order.
const MENU_KEY = 'restro-pos-menu';

export default function posOrder({ order, categories, endpoints, currency }) {
    return {
        order,
        categories,
        endpoints,
        currency,

        activeCategory: categories.length ? categories[0].id : null,
        search: '',
        busy: false,
        error: '',
        noteFor: null,
        noteText: '',

        init() {
            this.restoreMenu();

            // Mirror the order onto the customer's screen straight away, so a
            // display opened mid-order catches up rather than showing idle.
            this.mirror();
        },

        /**
         * Put the menu back where it was before the first item opened the
         * order, so the move to the order's own screen goes unnoticed.
         * Storage can be unavailable; the menu then simply starts at the top.
         */
        restoreMenu() {
            try {
                const saved = JSON.parse(sessionStorage.getItem(MENU_KEY) ?? 'null');
                sessionStorage.removeItem(MENU_KEY);

                if (saved?.category && this.categories.some((c) => c.id === saved.category)) {
                    this.activeCategory = saved.category;
                }

                this.search = saved?.search ?? '';
            } catch {
                // Nothing to restore.
            }
        },

        rememberMenu() {
            try {
                sessionStorage.setItem(MENU_KEY, JSON.stringify({
                    category: this.activeCategory,
                    search: this.search,
                }));
            } catch {
                // The menu will start at the top instead.
            }
        },

        /**
         * Push the current order to the customer-facing screen.
         *
         * Wrapped defensively on purpose: the second screen is a courtesy, and
         * nothing going wrong with it may ever stop the till taking an order.
         */
        mirror() {
            try {
                window.showOnCustomerDisplay?.({ screen: 'order', order: this.order });
            } catch (error) {
                console.warn('Customer display could not be updated.', error);
            }
        },

        get items() {
            return visibleItems(this.categories, this.activeCategory, this.search);
        },

        get isEmpty() {
            return this.order.items.length === 0;
        },

        money(amount) {
            return `${this.currency} ${Number(amount).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })}`;
        },

        selectCategory(id) {
            this.activeCategory = id;
            this.search = '';
        },

        addItem(menuItemId) {
            return this.send(this.endpoints.addItem, 'POST', { menu_item_id: menuItemId, quantity: 1 });
        },

        increase(item) {
            return this.setQuantity(item, item.quantity + 1);
        },

        decrease(item) {
            return this.setQuantity(item, item.quantity - 1);
        },

        setQuantity(item, quantity) {
            if (quantity <= 0) {
                return this.remove(item);
            }

            return this.send(this.endpoints.updateItem.replace('__ITEM__',item.id), 'PATCH', { quantity });
        },

        remove(item) {
            return this.send(this.endpoints.removeItem.replace('__ITEM__',item.id), 'DELETE');
        },

        openNote(item) {
            this.noteFor = item.id;
            this.noteText = item.note ?? '';
        },

        closeNote() {
            this.noteFor = null;
            this.noteText = '';
        },

        async saveNote() {
            const id = this.noteFor;
            this.closeNote();

            await this.send(this.endpoints.updateItem.replace('__ITEM__',id), 'PATCH', {
                note: this.noteText === '' ? null : this.noteText,
            });
        },

        /** Fire a change at the server and replace local state with its answer. */
        async send(url, method, body = null) {
            if (this.busy) {
                return;
            }

            this.busy = true;
            this.error = '';

            // Stays busy while the page moves to a newly opened order, so a
            // second tap cannot open a second one.
            let leaving = false;

            try {
                const response = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: body === null ? null : JSON.stringify(body),
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    this.error = payload.message ?? 'Something went wrong. Please try again.';

                    return;
                }

                if (payload.redirect) {
                    leaving = true;
                    this.rememberMenu();
                    window.location.replace(payload.redirect);

                    return;
                }

                this.order = payload.order;
                this.mirror();
            } catch {
                this.error = 'Connection lost. Check the network and try again.';
            } finally {
                if (!leaving) {
                    this.busy = false;
                }
            }
        },
    };
}
