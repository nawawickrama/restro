<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The catering catalogue: the designed artwork a customer is shown.
 *
 * Distinct from `catering_items`, which is the working list of dishes a
 * booking sheet is built from. This is the brochure — a category such as
 * "Wedding packages" holding the pages of artwork that describe it, published
 * at a public address so it can be sent to somebody or put behind a QR code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catering_catalogues', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // The public address of this category, kept stable so a link that
            // has been shared or printed keeps working after a rename.
            $table->string('slug')->unique();

            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('catering_catalogue_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catering_catalogue_id')->constrained()->cascadeOnDelete();

            // Relative path on the public disk.
            $table->string('path');
            $table->string('caption')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['catering_catalogue_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catering_catalogue_images');
        Schema::dropIfExists('catering_catalogues');
    }
};
