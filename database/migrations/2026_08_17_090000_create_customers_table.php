<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Promotes customers from details typed onto an order to a record of their own.
 *
 * Orders keep their own `customer_name` and `customer_phone` columns. Those are
 * snapshots, exactly like the item prices: renaming a customer, or deleting
 * them entirely, must never rewrite what a past receipt said.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();

            // The number as the cashier typed it, and the same number reduced
            // to digits. "077 123 4567" and "0771234567" are one person, and
            // the unique index on the reduced form is what enforces that.
            $table->string('phone', 32);
            $table->string('phone_digits', 32)->unique();

            $table->string('source', 20);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index(['source', 'created_at']);
            $table->index('created_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Null on delete: removing a customer record must not take their
            // order history with it.
            $table->foreignId('customer_id')->nullable()->after('user_id')
                ->constrained('customers')->nullOnDelete();
        });

        $this->backfill();
    }

    /**
     * Build customer records out of the orders already taken, so the module
     * opens with the restaurant's real history rather than an empty list.
     */
    private function backfill(): void
    {
        $orders = DB::table('orders')
            ->whereNotNull('customer_phone')
            ->where('customer_phone', '!=', '')
            ->orderBy('id')
            ->get(['id', 'customer_phone', 'customer_name', 'type', 'created_at']);

        $customers = [];

        foreach ($orders as $order) {
            $digits = preg_replace('/\D/', '', (string) $order->customer_phone);

            if ($digits === '') {
                continue;
            }

            // The earliest order is the one that introduced them, so it decides
            // the source; a later order fills in a name if the first had none.
            if (! isset($customers[$digits])) {
                $customers[$digits] = [
                    'name' => $order->customer_name,
                    'phone' => $order->customer_phone,
                    'phone_digits' => $digits,
                    'source' => match ($order->type) {
                        'dine_in' => 'dine_in',
                        'phone_takeaway' => 'phone',
                        default => 'walk_in',
                    },
                    'note' => null,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->created_at,
                ];
            } elseif (blank($customers[$digits]['name']) && filled($order->customer_name)) {
                $customers[$digits]['name'] = $order->customer_name;
            }
        }

        if ($customers === []) {
            return;
        }

        foreach (array_chunk($customers, 200) as $chunk) {
            DB::table('customers')->insert($chunk);
        }

        // Point every order at the record built from its number.
        foreach (DB::table('customers')->select('id', 'phone_digits')->cursor() as $customer) {
            DB::table('orders')
                ->whereNotNull('customer_phone')
                ->whereRaw("REGEXP_REPLACE(customer_phone, '[^0-9]', '') = ?", [$customer->phone_digits])
                ->update(['customer_id' => $customer->id]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });

        Schema::dropIfExists('customers');
    }
};
