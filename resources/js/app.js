import Alpine from 'alpinejs';
import openOrders from './open-orders';
import posOrder from './pos-order';

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

Alpine.start();
