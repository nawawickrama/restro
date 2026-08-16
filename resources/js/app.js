import Alpine from 'alpinejs';
import customerDisplay from './customer-display';
import customerDisplayLauncher from './customer-display-launcher';
import openOrders from './open-orders';
import posOrder from './pos-order';
import { startPublisher } from './display-channel';

window.Alpine = Alpine;

/**
 * Theme is applied by an inline script in the layout head (before paint) and
 * only toggled here, so the screen never flashes white on load.
 */
Alpine.data('themeToggle', () => ({
    dark: document.documentElement.classList.contains('dark'),

    toggle() {
        this.dark = !this.dark;
        document.documentElement.classList.toggle('dark', this.dark);
        localStorage.setItem('restro-theme', this.dark ? 'dark' : 'light');
    },
}));

Alpine.data('posOrder', posOrder);
Alpine.data('openOrders', openOrders);
Alpine.data('customerDisplay', customerDisplay);
Alpine.data('customerDisplayLauncher', customerDisplayLauncher);

/**
 * One publisher per cashier page, shared by whatever on the page has something
 * to say to the customer screen. Pages call `window.showOnCustomerDisplay(...)`
 * once on load, and again whenever the order changes.
 */
const publisher = startPublisher({
    onPresenceChange: (present) => {
        window.dispatchEvent(new CustomEvent('customer-display-presence', { detail: present }));
    },
});

window.showOnCustomerDisplay = (state) => publisher.publish(state);
window.customerDisplayPresent = () => publisher.isPresent();

/**
 * Some pages hand the display a state on load — the receipt does, to put a
 * thank-you up the moment a sale completes. It travels as a JSON data island
 * rather than inline script.
 */
const initialState = document.getElementById('customer-display-state');

if (initialState) {
    try {
        publisher.publish(JSON.parse(initialState.textContent));
    } catch {
        // A malformed payload is not worth breaking the receipt over.
    }
}

Alpine.start();
