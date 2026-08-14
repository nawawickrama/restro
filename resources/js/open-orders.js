/**
 * Finding an open takeaway or phone order on the POS home screen.
 *
 * The open orders are already on the page, so filtering happens here rather
 * than at the server: a cashier with a customer at the counter gets the list
 * narrowing as they type the third digit of a phone number, with no round trip
 * and nothing to wait for.
 */
export default function openOrders({ orders }) {
    return {
        orders,
        search: '',

        get term() {
            return this.search.trim().toLowerCase();
        },

        get filtered() {
            if (!this.term) {
                return this.orders;
            }

            // Digits typed into the box should match a phone number however it
            // was written down — with spaces, dashes or neither.
            const digits = this.term.replace(/\D/g, '');

            return this.orders.filter(
                (order) =>
                    order.haystack.includes(this.term) ||
                    (digits.length > 0 && order.phone_digits.includes(digits)),
            );
        },

        get isSearching() {
            return this.term.length > 0;
        },

        get hasResults() {
            return this.filtered.length > 0;
        },

        clear() {
            this.search = '';
            this.$refs.search?.focus();
        },
    };
}
