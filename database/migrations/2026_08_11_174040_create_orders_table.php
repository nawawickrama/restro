<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 32)->unique();
            $table->string('type', 20);
            $table->string('status', 20)->default('open');

            // Phone orders only: pending -> ready -> collected. Null otherwise.
            $table->string('fulfillment_status', 20)->nullable();
            $table->string('payment_status', 20)->default('unpaid');

            // No cascading actions here: MySQL forbids them on a column that a
            // generated column (active_table_id, below) is derived from. Tables
            // with order history are deactivated rather than deleted anyway.
            $table->foreignId('table_id')->nullable()->constrained('tables')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();

            // Phone order customer details live on the order itself; the MVP has
            // no customer accounts and no CRM.
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 32)->nullable();
            $table->text('note')->nullable();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            // Business rule: a table can never hold two active dine-in orders.
            //
            // This generated column mirrors table_id only while a dine-in order
            // is open, so the unique index makes the database itself reject a
            // second one. The service layer checks first and raises a friendly
            // error; this index is the backstop against a race between two
            // cashiers tapping the same table at the same moment.
            $table->unsignedBigInteger('active_table_id')
                ->nullable()
                ->storedAs("CASE WHEN status = 'open' AND type = 'dine_in' THEN table_id ELSE NULL END");

            $table->unique('active_table_id');

            $table->index(['status', 'type']);
            $table->index(['type', 'created_at']);
            $table->index('created_at');
            $table->index('payment_status');
            $table->index('customer_phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
