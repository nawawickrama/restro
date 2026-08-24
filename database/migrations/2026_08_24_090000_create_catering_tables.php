<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catering: what the kitchen has been booked to cook, for whom, and on what day.
 *
 * Kept apart from the till's menu on purpose. A catering sheet lists "Malu
 * Ambul Thiyal, 5 kg" — a bulk quantity quoted per kilo — while the restaurant
 * menu sells a plate over the counter. The same words describe different
 * things, so they get their own catalogue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catering_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // The unit this dish is normally ordered in; a booking may override
            // it, because the same curry can go out by the kilo or by the tray.
            $table->string('default_unit', 20);

            // Nullable: a kitchen often books the job first and prices it later.
            $table->decimal('price', 12, 2)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('name');
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('catering_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();

            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            // Snapshots, exactly as on an order: renaming or deleting a customer
            // record must never rewrite a booking sheet the kitchen worked from.
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 32)->nullable();

            $table->unsignedInteger('pax')->nullable();
            $table->date('event_date');
            $table->time('event_time')->nullable();
            $table->string('venue')->nullable();

            $table->string('status', 20)->default('enquiry');
            $table->text('note')->nullable();
            $table->decimal('total', 12, 2)->default(0);

            $table->timestamps();

            // The calendar asks for one month at a time, newest first within a
            // day, and the list filters by status over a range.
            $table->index(['event_date', 'status']);
            $table->index(['status', 'event_date']);
            $table->index('customer_phone');
        });

        Schema::create('catering_booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catering_booking_id')->constrained()->cascadeOnDelete();

            // Kept for reporting; the name and price below are the record.
            $table->foreignId('catering_item_id')->nullable()
                ->constrained('catering_items')->nullOnDelete();

            $table->string('name');

            // Three decimals so half a litre and 2.5 kg both survive.
            $table->decimal('quantity', 10, 3);
            $table->string('unit', 20);

            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('line_total', 12, 2)->default(0);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('catering_booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catering_booking_items');
        Schema::dropIfExists('catering_bookings');
        Schema::dropIfExists('catering_items');
    }
};
