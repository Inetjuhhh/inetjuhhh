<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
        });

        // A blog belongs to at most one trip; deleting a trip keeps its blogs
        Schema::table('blogs', function (Blueprint $table) {
            $table->foreignId('trip_id')->nullable()->after('placed_by_id')
                ->constrained('trips')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trip_id');
        });

        Schema::dropIfExists('trips');
    }
};
