<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The trip a photo belongs to, chosen when uploading or afterwards in the media library.
 * Empty (all photos so far): the trip of the blog the photo is in (see PhotoTrips).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curator', function (Blueprint $table) {
            $table->foreignId('trip_id')->nullable()->after('fingerprint')->constrained('trips')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('curator', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trip_id');
        });
    }
};
