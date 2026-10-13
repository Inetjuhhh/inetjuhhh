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
        Schema::create('responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_id')->references('id')->on('blogs')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('responses')->cascadeOnDelete();
            $table->text('response');
            $table->string('name');
            $table->string('email');
            $table->string('status')->default('pending')->index();
            $table->boolean('is_author')->default(false);
            $table->boolean('notify_on_reply')->default(false);
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('approved_at')->nullable();
            // The comment's id in the old WordPress blog, so running the import again updates instead of duplicates
            $table->unsignedBigInteger('wordpress_comment_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('responses');
    }
};
