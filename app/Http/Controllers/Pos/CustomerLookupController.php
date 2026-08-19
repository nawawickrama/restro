<?php

namespace App\Http\Controllers\Pos;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Recognises a phone number as the cashier types it.
 *
 * A returning customer reads out their number and nothing else; without this
 * the till would take the order but forget who they are. Answering here means
 * the cashier can greet them by name and does not have to ask for it again.
 *
 * Deliberately an exact-match lookup on a whole number, never a search: a
 * cashier holding this screen could otherwise walk the prefix space and read
 * out the restaurant's entire customer list.
 */
class CustomerLookupController extends Controller
{
    /** Short of this, a "number" is a prefix and would match many people. */
    private const MINIMUM_DIGITS = 7;

    public function __invoke(Request $request): JsonResponse
    {
        $digits = Customer::normalisePhone($request->string('phone')->toString());

        if (strlen($digits) < self::MINIMUM_DIGITS) {
            return response()->json(['found' => false]);
        }

        $customer = Customer::query()
            ->where('phone_digits', $digits)
            ->withCount(['orders as orders_count' => fn ($q) => $q->where('status', OrderStatus::Completed)])
            ->withMax(['orders as last_order_at' => fn ($q) => $q->where('status', OrderStatus::Completed)], 'created_at')
            ->first();

        if (! $customer) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'name' => $customer->name,
            'orders' => (int) $customer->orders_count,
            'last_order' => $customer->last_order_at
                ? Carbon::parse($customer->last_order_at)->diffForHumans()
                : null,
        ]);
    }
}
