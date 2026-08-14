<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // A payment always belongs to an order; it never stands alone.
            $table->foreignId('order_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();

            $table->string('method', 20);
            $table->decimal('amount', 12, 2);

            // Cash only: what the customer handed over and what went back.
            $table->decimal('tendered', 12, 2)->nullable();
            $table->decimal('change_amount', 12, 2)->default(0);

            $table->string('reference')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index('order_id');
            $table->index(['method', 'paid_at']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
