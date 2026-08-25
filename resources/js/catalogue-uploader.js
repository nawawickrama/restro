/**
 * Adding artworks to a catalogue category.
 *
 * One row per artwork: the image, and the name it goes out under. Rows are
 * added and removed before anything is saved, so a category can be created
 * with its packages already named rather than uploaded first and labelled
 * afterwards.
 */
export default function catalogueUploader({ titles = [], startWith = 1 }) {
    return {
        rows: [],
        nextKey: 0,

        init() {
            // Titles survive a validation failure even though the files cannot,
            // so the rows come back with their names already filled in.
            const restored = titles.length ? titles : Array.from({ length: startWith }, () => '');

            this.rows = restored.map((title) => this.makeRow(title));
        },

        makeRow(title = '') {
            return { key: this.nextKey++, title, preview: null, filename: '' };
        },

        addRow() {
            this.rows.push(this.makeRow());
        },

        removeRow(index) {
            this.rows.splice(index, 1);

            // Never leave the section with nothing to fill in.
            if (this.rows.length === 0) {
                this.addRow();
            }
        },

        /** Show what was picked, and name it from the file if unnamed. */
        choose(row, event) {
            const file = event.target.files[0];

            if (!file) {
                row.preview = null;
                row.filename = '';

                return;
            }

            row.preview = URL.createObjectURL(file);
            row.filename = file.name;

            if (row.title.trim() === '') {
                row.title = this.titleFrom(file.name);
            }
        },

        /** "Package 2.jpg" becomes "Package 2"; a camera filename becomes nothing. */
        titleFrom(filename) {
            const base = filename.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ').trim();

            return /^(img|dsc|photo|image|screenshot)\b/i.test(base) ? '' : base;
        },
    };
}
