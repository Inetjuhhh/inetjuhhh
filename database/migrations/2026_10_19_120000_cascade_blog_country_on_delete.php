<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a blog (or a country) failed: blog_country was the only link table without "cascade", so the
 * database refused to delete a blog that still had countries. Now its rows go along, like tags and comments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_country', function (Blueprint $table) {
            $table->dropForeign(['blog_id']);
            $table->dropForeign(['country_id']);

            $table->foreign('blog_id')->references('id')->on('blogs')->cascadeOnDelete();
            $table->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('blog_country', function (Blueprint $table) {
            $table->dropForeign(['blog_id']);
            $table->dropForeign(['country_id']);

            $table->foreign('blog_id')->references('id')->on('blogs');
            $table->foreign('country_id')->references('id')->on('countries');
        });
    }
};
