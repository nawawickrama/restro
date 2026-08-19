<?php

namespace App\Services;

use App\Enums\CustomerSource;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Exceptions\PosOperationException;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Everything that changes an order lives here.
 *
 * Controllers stay thin and Blade stays dumb: any rule about what may be added,
 * changed, moved or cancelled is enforced in this class, inside a transaction.
 */
class OrderService
{
    public function __construct(
        private readonly OrderNumberGenerator $numbers,
        private readonly SettingsService $settings,
    ) {}

    /**
     * Open a dine-in order and, by doing so, occupy the table.
     *
     * Business rule 5: two cashiers cannot open two orders on one table. We
     * check under a row lock and let the unique index on orders.active_table_id
     * catch anything that slips between the check and the insert.
     */
    public function startDineIn(RestaurantTable $table, User $user): Order
    {
        if (! $table->is_active) {
            throw PosOperationException::tableInactive($table->name);
        }

        return DB::transaction(function () use ($table, $user): Order {
            $existing = Order::query()
                ->where('table_id', $table->id)
                ->where('type', OrderType::DineIn)
                ->where('status', OrderStatus::Open)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw PosOperationException::tableOccupied($table->name);
            }

            try {
                return $this->createOrder([
                    'type' => OrderType::DineIn,
                    'table_id' => $table->id,
                    'user_id' => $user->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw PosOperationException::tableOccupied($table->name);
            }
        });
    }

    /** Walk-in takeaway: no table, no customer details. */
    public function startTakeaway(User $user): Order
    {
        return DB::transaction(fn (): Order => $this->createOrder([
            'type' => OrderType::Takeaway,
            'user_id' => $user->id,
        ]));
    }

    /**
     * Phone takeaway.
     *
     * The order opens empty, because on a real call the customer reads out
     * their food long before they give a number. Contact details are captured
     * afterwards by {@see self::setCustomer()}, and business rule 13 is
     * enforced at checkout instead of at creation.
     */
    public function startPhoneOrder(User $user): Order
    {
        return DB::transaction(fn (): Order => $this->createOrder([
            'type' => OrderType::PhoneTakeaway,
            'user_id' => $user->id,
            'fulfillment_status' => FulfillmentStatus::Pending,
        ]));
    }

    /**
     * Record who the order is for.
     *
     * Every order type can carry a name and a number: a dine-in table may leave
     * a number for a callback, a walk-in may want their name called when the
     * food is up. All of it is optional, and the validation layer enforces the
     * one exception — a phone order must have a number (business rule 13).
     *
     * @param  array{customer_phone?: string|null, customer_name?: string|null, note?: string|null}  $customer
     */
    public function setCustomer(Order $order, array $customer): void
    {
        $this->assertEditable($order);

        $phone = $this->cleaned($customer['customer_phone'] ?? null);
        $name = $this->cleaned($customer['customer_name'] ?? null);

        DB::transaction(function () use ($order, $customer, $phone, $name): void {
            $known = $this->rememberCustomer($order, $phone, $name);

            $order->forceFill([
                // The order keeps its own copy of whatever was typed. Renaming
                // or deleting the customer record later must not rewrite it.
                'customer_phone' => $phone,

                // A number the restaurant already knows brings its name with
                // it, so a regular who only reads out their number is still
                // greeted by name on the receipt and the customer screen.
                'customer_name' => $name ?? $known?->name,

                'note' => $this->cleaned($customer['note'] ?? null),
                'customer_id' => $known?->id,
            ])->save();
        });
    }

    /**
     * Find or start the customer record behind an order.
     *
     * The number is the identity: without one there is nothing to recognise
     * somebody by later, and a name on its own would create a new "customer"
     * every time two people share one. A record that already exists keeps its
     * original source — a caller who later eats in was still met on the phone
     * — but will accept a name if the restaurant never had one.
     */
    private function rememberCustomer(Order $order, ?string $phone, ?string $name): ?Customer
    {
        if (blank($phone)) {
            return null;
        }

        $digits = Customer::normalisePhone($phone);

        if ($digits === '') {
            return null;
        }

        $existing = Customer::query()->where('phone_digits', $digits)->lockForUpdate()->first();

        if (! $existing) {
            return Customer::query()->create([
                'name' => $name,
                'phone' => $phone,
                'phone_digits' => $digits,
                'source' => CustomerSource::fromOrderType($order->type),
            ]);
        }

        if (blank($existing->name) && filled($name)) {
            $existing->update(['name' => $name]);
        }

        return $existing;
    }

    /** Blank details are stored as null, so "no name" is one value, not three. */
    private function cleaned(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Add an item, or bump the quantity of an identical line already on the
     * order so repeated taps do the obvious thing.
     *
     * Business rules 6 and 8: the price is copied onto the line at this moment,
     * and disabled items are refused.
     */
    public function addItem(Order $order, MenuItem $menuItem, int $quantity = 1, ?string $note = null): OrderItem
    {
        $this->assertEditable($order);

        $menuItem->loadMissing('category');

        if (! $menuItem->isSellable()) {
            throw PosOperationException::itemUnavailable($menuItem->name);
        }

        return DB::transaction(function () use ($order, $menuItem, $quantity, $note): OrderItem {
            $price = (float) $menuItem->price;

            // Merge only when the note and the captured price both match, so a
            // mid-order price change starts a new line instead of rewriting one.
            $line = $order->items()
                ->where('menu_item_id', $menuItem->id)
                ->where('unit_price', $price)
                ->where(fn ($q) => $note === null ? $q->whereNull('note') : $q->where('note', $note))
                ->lockForUpdate()
                ->first();

            if ($line) {
                $line->quantity += $quantity;
            } else {
                $line = $order->items()->make([
                    'menu_item_id' => $menuItem->id,
                    'name' => $menuItem->name,
                    'unit_price' => $price,
                    'quantity' => $quantity,
                    'note' => $note,
                ]);
            }

            $line->recalculate();
            $line->save();

            $this->recalculate($order);

            return $line;
        });
    }

    /** Setting a quantity of zero or less removes the line. */
    public function setQuantity(Order $order, OrderItem $item, int $quantity): void
    {
        $this->assertEditable($order);

        if ($quantity <= 0) {
            $this->removeItem($order, $item);

            return;
        }

        DB::transaction(function () use ($order, $item, $quantity): void {
            $item->quantity = $quantity;
            $item->recalculate();
            $item->save();

            $this->recalculate($order);
        });
    }

    public function removeItem(Order $order, OrderItem $item): void
    {
        $this->assertEditable($order);

        DB::transaction(function () use ($order, $item): void {
            $item->delete();

            $this->recalculate($order);
        });
    }

    public function setItemNote(Order $order, OrderItem $item, ?string $note): void
    {
        $this->assertEditable($order);

        $item->update(['note' => $note]);
    }

    /** Business rule 10: the caller must already have checked authorization. */
    public function applyDiscount(Order $order, float $amount): void
    {
        $this->assertEditable($order);

        $amount = round(max($amount, 0), 2);

        if ($amount > (float) $order->subtotal) {
            throw PosOperationException::discountTooLarge();
        }

        DB::transaction(function () use ($order, $amount): void {
            $order->discount_amount = $amount;
            $this->recalculate($order);
        });
    }

    /** Move an open dine-in order to a different table. */
    public function moveToTable(Order $order, RestaurantTable $table): void
    {
        $this->assertEditable($order);

        if ($order->type !== OrderType::DineIn) {
            throw PosOperationException::notATableOrder();
        }

        if (! $table->is_active) {
            throw PosOperationException::tableInactive($table->name);
        }

        if ($order->table_id === $table->id) {
            return;
        }

        DB::transaction(function () use ($order, $table): void {
            $occupied = Order::query()
                ->where('table_id', $table->id)
                ->where('type', OrderType::DineIn)
                ->where('status', OrderStatus::Open)
                ->lockForUpdate()
                ->exists();

            if ($occupied) {
                throw PosOperationException::tableOccupied($table->name);
            }

            try {
                $order->forceFill(['table_id' => $table->id])->save();
            } catch (UniqueConstraintViolationException) {
                throw PosOperationException::tableOccupied($table->name);
            }
        });
    }

    /**
     * Cancel an order. A dine-in table is freed as a side effect, because
     * occupancy is derived from open dine-in orders.
     */
    public function cancel(Order $order, ?string $reason = null): void
    {
        $this->assertEditable($order);

        DB::transaction(function () use ($order, $reason): void {
            $order->forceFill([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
                'note' => $reason ? trim($order->note."\nCancelled: ".$reason) : $order->note,
            ])->save();
        });
    }

    /** Phone orders only: pending -> ready -> collected. */
    public function setFulfillmentStatus(Order $order, FulfillmentStatus $status): void
    {
        $this->assertEditable($order);

        if ($order->type !== OrderType::PhoneTakeaway) {
            throw new PosOperationException('Only phone orders track a collection status.');
        }

        $order->forceFill(['fulfillment_status' => $status])->save();
    }

    /**
     * Recompute the money on an order from its lines.
     *
     * Tax is a single restaurant-wide percentage applied after the discount,
     * which is as complicated as the MVP gets.
     */
    public function recalculate(Order $order): void
    {
        $subtotal = round((float) $order->items()->sum('line_total'), 2);
        $discount = min(round((float) $order->discount_amount, 2), $subtotal);
        $taxable = round($subtotal - $discount, 2);
        $tax = round($taxable * $this->settings->taxPercentage() / 100, 2);

        $order->forceFill([
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total' => round($taxable + $tax, 2),
        ])->save();

        $order->load('items');
    }

    /** Business rules 1 and 2, enforced for every mutation in this service. */
    private function assertEditable(Order $order): void
    {
        if (! $order->isEditable()) {
            throw PosOperationException::orderLocked($order->status->value);
        }
    }

    /**
     * Insert an order, retrying if another terminal took the same daily number
     * in the same instant.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createOrder(array $attributes): Order
    {
        foreach (range(1, 3) as $attempt) {
            try {
                return Order::query()->create(array_merge([
                    'order_number' => $this->numbers->generate(),
                    'status' => OrderStatus::Open,
                    'payment_status' => PaymentStatus::Unpaid,
                    'subtotal' => 0,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'total' => 0,
                ], $attributes));
            } catch (UniqueConstraintViolationException $e) {
                // A duplicate active_table_id is a real conflict, not a number
                // clash: let the caller turn it into "table is busy".
                if (str_contains($e->getMessage(), 'active_table_id') || $attempt === 3) {
                    throw $e;
                }
            }
        }

        throw new PosOperationException('Could not allocate an order number. Please try again.');
    }
}
