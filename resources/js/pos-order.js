/**
 * The order screen.
 *
 * Every tap talks to the server and the server sends the whole order back, so
 * the totals on screen are always the totals the database believes in. There is
 * no client-side price arithmetic anywhere in this file, on purpose.
 */
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

        get items() {
            const term = this.search.trim().toLowerCase();

            if (term.length) {
                return this.categories
                    .flatMap((category) => category.items)
                    .filter((item) => item.name.toLowerCase().includes(term));
            }

            const category = this.categories.find((c) => c.id === this.activeCategory);

            return category ? category.items : [];
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

                this.order = payload.order;
            } catch {
                this.error = 'Connection lost. Check the network and try again.';
            } finally {
                this.busy = false;
            }
        },
    };
}
