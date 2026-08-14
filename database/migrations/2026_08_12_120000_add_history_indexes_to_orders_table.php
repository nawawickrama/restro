<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the order history screen.
 *
 * Every history query is "a window of time, optionally narrowed by one facet,
 * newest first". These composites let MySQL walk the index in the order the
 * screen asks for and stop at the page size, instead of sorting a year of
 * orders to show twenty of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'orders_status_created_at_index');
            $table->index(['payment_status', 'created_at'], 'orders_payment_status_created_at_index');

            // Prefix searches on the customer name ("nim%") can use this.
            $table->index('customer_name', 'orders_customer_name_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Redundant now: the composite above serves payment_status lookups
            // on its own, since payment_status is its leading column.
            $table->dropIndex('orders_payment_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('payment_status', 'orders_payment_status_index');

            $table->dropIndex('orders_status_created_at_index');
            $table->dropIndex('orders_payment_status_created_at_index');
            $table->dropIndex('orders_customer_name_index');
        });
    }
};
