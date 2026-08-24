/**
 * The rows on a catering booking sheet.
 *
 * Each line carries its own quantity and unit, because one kitchen sends curry
 * out by the kilo, rice by the portion and faluda by the litre. Picking a dish
 * the restaurant already caters fills in the unit and price it is usually
 * quoted at; anything typed by hand is left alone.
 */
export default function cateringSheet({ catalogue, lines, currency }) {
    return {
        catalogue,
        currency,
        lines: [],
        nextKey: 0,

        init() {
            // Keys keep Alpine's x-for stable while rows are added and removed.
            this.lines = (lines ?? []).map((line) => ({
                key: this.nextKey++,
                catering_item_id: line.catering_item_id ?? null,
                name: line.name ?? '',
                quantity: line.quantity ?? '',
                unit: line.unit ?? 'kg',
                unit_price: line.unit_price ?? '',
                note: line.note ?? '',
            }));

            if (this.lines.length === 0) {
                this.addLine();
            }
        },

        addLine() {
            this.lines.push({
                key: this.nextKey++,
                catering_item_id: null,
                name: '',
                quantity: '',
                unit: 'kg',
                unit_price: '',
                note: '',
            });
        },

        removeLine(index) {
            this.lines.splice(index, 1);
        },

        /**
         * Fill in the usual unit and price when the typed name is one the
         * kitchen already caters. Never overwrites a price already entered:
         * a function is often quoted off the standard rate.
         */
        matchCatalogue(line) {
            const match = this.catalogue.find(
                (item) => item.name.toLowerCase() === (line.name ?? '').trim().toLowerCase(),
            );

            if (!match) {
                line.catering_item_id = null;

                return;
            }

            line.catering_item_id = match.id;
            line.unit = match.unit;

            if (match.price !== null && (line.unit_price === '' || line.unit_price === null)) {
                line.unit_price = match.price;
            }
        },

        money(amount) {
            return `${this.currency} ${Number(amount).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })}`;
        },

        lineAmount(line) {
            const price = parseFloat(line.unit_price);
            const quantity = parseFloat(line.quantity);

            if (Number.isNaN(price) || Number.isNaN(quantity)) {
                return 0;
            }

            return price * quantity;
        },

        lineTotal(line) {
            const amount = this.lineAmount(line);

            return amount > 0 ? this.money(amount) : '—';
        },

        total() {
            return this.money(this.lines.reduce((sum, line) => sum + this.lineAmount(line), 0));
        },
    };
}
