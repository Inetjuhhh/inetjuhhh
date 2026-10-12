<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Comments: the existing `responses` table gets moderation, replies and spam details
        Schema::table('responses', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('blog_id')
                ->constrained('responses')->cascadeOnDelete();
            $table->string('status')->default('pending')->after('email')->index();
            $table->boolean('is_author')->default(false)->after('status');
            $table->boolean('notify_on_reply')->default(false)->after('is_author');
            $table->string('ip_hash', 64)->nullable()->after('notify_on_reply');
            $table->string('user_agent')->nullable()->after('ip_hash');
            $table->timestamp('approved_at')->nullable()->after('user_agent');
        });

        // Comments that already exist were written before moderation existed
        DB::table('responses')->update(['status' => 'approved', 'approved_at' => DB::raw('created_at')]);

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

        Schema::table('responses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['status', 'is_author', 'notify_on_reply', 'ip_hash', 'user_agent', 'approved_at']);
        });
    }
};
