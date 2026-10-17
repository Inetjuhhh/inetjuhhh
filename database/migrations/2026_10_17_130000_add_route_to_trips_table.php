<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The planned route of a trip that is still to come: a list of stops [{name, lat, lng}], entered in
 * the admin (Reizen) and shown as a dashed line on the travel map.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->json('route')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('route');
        });
    }
};
