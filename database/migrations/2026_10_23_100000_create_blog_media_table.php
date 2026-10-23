<?php

use App\Support\PhotoBlogs;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which photos of the media library are in which blog (featured photo, photo and gallery blocks),
 * so the library can be filtered by trip, country and year. Kept up to date when a blog is saved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_media', function (Blueprint $table) {
            $table->foreignId('blog_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('curator')->cascadeOnDelete();
            $table->primary(['blog_id', 'media_id']);
            $table->index('media_id');
        });

        PhotoBlogs::syncAll();
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_media');
    }
};
