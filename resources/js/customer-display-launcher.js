/**
 * Opens the customer-facing screen, on the right monitor, without anybody
 * dragging a window.
 *
 * Chrome and Edge expose the physical screen layout once the terminal has
 * granted permission, so the window can be placed on the second monitor at its
 * exact bounds. Elsewhere it opens as an ordinary window and can be moved by
 * hand, or launched from a desktop shortcut — see the README.
 *
 * The window carries a fixed name, so moving between POS screens reuses it
 * rather than opening a second one, and the display keeps running untouched
 * for the whole shift.
 */
const WINDOW_NAME = 'restro-customer-display';

export default function customerDisplayLauncher({ url }) {
    return {
        url,
        connected: false,
        blocked: false,

        init() {
            this.connected = window.customerDisplayPresent?.() ?? false;

            window.addEventListener('customer-display-presence', (event) => {
                this.connected = event.detail;
                if (this.connected) {
                    this.blocked = false;
                }
            });
        },

        async open() {
            this.blocked = false;

            const display = window.open(this.url, WINDOW_NAME, await this.features());

            if (! display) {
                // Almost always a popup blocker; say so rather than doing
                // nothing and leaving the cashier tapping a dead button.
                this.blocked = true;

                return;
            }

            display.focus();
        },

        /** Bounds of the second monitor, when the browser will tell us. */
        async features() {
            try {
                if (! ('getScreenDetails' in window)) {
                    return 'popup=yes,width=1280,height=800';
                }

                const { screens, currentScreen } = await window.getScreenDetails();
                const target = screens.find((screen) => ! screen.isPrimary) ?? currentScreen;

                // `fullscreen` is honoured once the terminal has granted the
                // window-management permission, so the display comes up
                // borderless on the right monitor with nobody pressing F11.
                return `popup=yes,fullscreen=yes,left=${target.left},top=${target.top},`
                    + `width=${target.availWidth},height=${target.availHeight}`;
            } catch {
                // Permission refused, or a browser without the API.
                return 'popup=yes,width=1280,height=800';
            }
        },
    };
}
