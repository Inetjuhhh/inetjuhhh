<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where readers of a blog come from, counted per day: their country and (roughly) city, and the
 * way they came to the story (Google, Facebook, the newsletter, ...). Only totals, nothing about
 * the reader: the address is used for the lookup and then forgotten (see ViewCounter).
 *
 * The first day in this table is the day the new counting started (after 15 seconds of reading).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_view_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            // Empty instead of null, so the unique index also holds when the country or city is unknown
            $table->char('country', 2)->default('');
            $table->string('city', 100)->default('');
            $table->string('source', 20)->default('direct');
            $table->unsignedInteger('views')->default(0);
            $table->unique(['blog_id', 'date', 'country', 'city', 'source'], 'blog_view_stats_unique');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_view_stats');
    }
};
