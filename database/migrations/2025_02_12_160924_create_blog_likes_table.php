<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hearts: one per visitor (cookie) per blog, no account needed
        Schema::create('blog_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_id')->constrained()->cascadeOnDelete();
            $table->string('visitor_hash', 64);
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['blog_id', 'visitor_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_likes');
    }
};
