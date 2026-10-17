<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The photo of a trip's card on the Reizen page, chosen in the admin from the featured photos of its stories.
 * Empty: the featured photo of the story halfway through the trip (see Trip::coverMedia).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('cover_image_id')->nullable()->after('kind')->constrained('curator')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cover_image_id');
        });
    }
};
