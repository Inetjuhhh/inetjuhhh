<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('blogs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt');
            $table->string('location_name')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('featured_image_id')->nullable()->constrained('curator')->nullOnDelete();
            $table->json('content');
            // The words of the content without the editor's markup, for the search bar
            $table->longText('search_text')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->dateTime('published_at')->nullable();
            $table->foreignId('placed_by_id')->references('id')->on('users')->constrained();
            // A blog belongs to at most one trip; deleting a trip keeps its blogs
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            // When subscribers were mailed about this blog; set once, so they never get it twice
            $table->timestamp('subscribers_notified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blogs');
    }
};
