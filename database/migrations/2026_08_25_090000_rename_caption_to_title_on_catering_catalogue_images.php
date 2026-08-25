<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each image in a catalogue category is a package in its own right — Weddings
 * holds Package 1, Package 2, Package 3 — rather than a page of one long
 * brochure. That makes the text against it a name, not a caption.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catering_catalogue_images', function (Blueprint $table) {
            $table->renameColumn('caption', 'title');
        });
    }

    public function down(): void
    {
        Schema::table('catering_catalogue_images', function (Blueprint $table) {
            $table->renameColumn('title', 'caption');
        });
    }
};
