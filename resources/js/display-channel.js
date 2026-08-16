/**
 * The link between the cashier's screen and the customer-facing one.
 *
 * Both are windows of the same browser on the same terminal, so they talk
 * directly over a BroadcastChannel — no server, no polling, no websocket
 * daemon to keep alive. The cashier screen already holds the order exactly as
 * the server last returned it, so the display is a mirror of that and can
 * never show a total the server did not calculate.
 */

const CHANNEL = 'restro-customer-display';
const FALLBACK_KEY = 'restro-customer-display-message';

export const Message = {
    /** Cashier -> display: here is what to show. */
    State: 'state',
    /** Display -> cashier: I just opened, tell me where we are. */
    Request: 'request-state',
    /** Display -> cashier: still here. */
    Alive: 'alive',
    /** Display -> cashier: I am closing. */
    Closing: 'closing',
};

/**
 * A tiny message bus across windows.
 *
 * Prefers BroadcastChannel; falls back to a localStorage write, whose `storage`
 * event fires in every *other* window of the origin — which is precisely the
 * delivery we want.
 */
export function createBus() {
    const handlers = new Map();
    const channel = 'BroadcastChannel' in window ? new BroadcastChannel(CHANNEL) : null;

    const deliver = ({ type, payload }) => {
        (handlers.get(type) ?? []).forEach((handler) => handler(payload));
    };

    if (channel) {
        channel.onmessage = (event) => deliver(event.data);
    } else {
        window.addEventListener('storage', (event) => {
            if (event.key === FALLBACK_KEY && event.newValue) {
                try {
                    deliver(JSON.parse(event.newValue));
                } catch {
                    // A malformed message is not worth breaking service over.
                }
            }
        });
    }

    return {
        post(type, payload = null) {
            // Alpine keeps component data behind a reactive Proxy, and the
            // structured clone algorithm refuses to copy one — postMessage
            // throws DataCloneError, which the order screen would surface as
            // "connection lost". Everything sent here is plain data, so a JSON
            // round trip strips the proxies and makes it cloneable.
            const message = { type, payload: payload === null ? null : JSON.parse(JSON.stringify(payload)) };

            if (channel) {
                channel.postMessage(message);

                return;
            }

            // The nonce forces a storage event even when the payload repeats.
            localStorage.setItem(
                FALLBACK_KEY,
                JSON.stringify({ ...message, nonce: Math.random() }),
            );
        },

        on(type, handler) {
            handlers.set(type, [...(handlers.get(type) ?? []), handler]);
        },

        close() {
            channel?.close();
        },
    };
}

/**
 * The cashier side of the link.
 *
 * Remembers the last state it sent so a display opened — or refreshed —
 * part-way through an order catches up immediately instead of sitting blank.
 * Tracks whether a display is listening, so the POS can show whether the
 * customer screen is actually up.
 */
export function startPublisher({ onPresenceChange } = {}) {
    const bus = createBus();
    let lastState = null;
    let lastSeen = 0;
    let present = false;

    const setPresence = (value) => {
        if (value !== present) {
            present = value;
            onPresenceChange?.(present);
        }
    };

    bus.on(Message.Request, () => {
        if (lastState) {
            bus.post(Message.State, lastState);
        }
    });

    bus.on(Message.Alive, () => {
        lastSeen = Date.now();
        setPresence(true);
    });

    bus.on(Message.Closing, () => {
        lastSeen = 0;
        setPresence(false);
    });

    // A display that stops answering has been closed or the machine slept.
    setInterval(() => setPresence(Date.now() - lastSeen < 6000), 2000);

    return {
        publish(state) {
            lastState = state;
            bus.post(Message.State, state);
        },
        isPresent: () => present,
    };
}
