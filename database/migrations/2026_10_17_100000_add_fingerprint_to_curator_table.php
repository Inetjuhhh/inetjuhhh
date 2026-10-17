<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A fingerprint of what a photo looks like (see PhotoFingerprint), to notice a photo that is
 * already in the media library when it is uploaded again. Existing photos: php artisan media:fingerprint
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curator', function (Blueprint $table) {
            $table->char('fingerprint', 16)->nullable()->after('size');
        });
    }

    public function down(): void
    {
        Schema::table('curator', function (Blueprint $table) {
            $table->dropColumn('fingerprint');
        });
    }
};
