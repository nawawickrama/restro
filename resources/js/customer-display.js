import { createBus, Message } from './display-channel';

/**
 * The customer-facing screen.
 *
 * Shows nothing of its own accord: it renders whatever the cashier's window
 * last broadcast. It has no controls, because a screen that cannot be operated
 * cannot be put into a state nobody asked for.
 */
export default function customerDisplay({ restaurant, welcome }) {
    return {
        restaurant,
        welcome,

        screen: 'idle',
        order: null,
        payment: null,
        completed: null,

        clock: '',
        justAdded: null,
        fullscreen: false,

        init() {
            const bus = createBus();

            bus.on(Message.State, (state) => this.apply(state));

            // Ask where we are, in case the display opened mid-order or was
            // refreshed. The cashier replies with its last state.
            bus.post(Message.Request);

            // Tell the POS we are up, so it can show the display as connected.
            bus.post(Message.Alive);
            setInterval(() => bus.post(Message.Alive), 3000);
            window.addEventListener('beforeunload', () => bus.post(Message.Closing));

            this.tick();
            setInterval(() => this.tick(), 10000);

            document.addEventListener('fullscreenchange', () => {
                this.fullscreen = document.fullscreenElement !== null;
            });
        },

        tick() {
            this.clock = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },

        apply(state) {
            const previous = this.order;

            this.screen = state.screen ?? 'idle';
            this.order = state.order ?? null;
            this.payment = state.payment ?? null;
            this.completed = state.completed ?? null;

            if (this.screen === 'order') {
                this.flagNewLine(previous, this.order);
                this.$nextTick(() => this.scrollToLatest());
            }
        },

        /**
         * Highlight whatever just changed, so the customer's eye lands on the
         * item being rung up rather than having to re-read the whole list.
         */
        flagNewLine(previous, current) {
            if (!current?.items?.length) {
                this.justAdded = null;

                return;
            }

            const before = new Map((previous?.items ?? []).map((item) => [item.id, item.quantity]));
            const changed = current.items.find((item) => before.get(item.id) !== item.quantity);

            if (!changed) {
                return;
            }

            this.justAdded = changed.id;
            clearTimeout(this.highlightTimer);
            this.highlightTimer = setTimeout(() => (this.justAdded = null), 2500);
        },

        scrollToLatest() {
            const list = this.$refs.lines;

            list?.scrollTo({ top: list.scrollHeight, behavior: 'smooth' });
        },

        /** The window opens with browser chrome; one tap clears it. */
        goFullscreen() {
            document.documentElement.requestFullscreen?.().catch(() => {});
        },
    };
}
