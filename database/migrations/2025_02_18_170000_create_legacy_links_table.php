<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Links to the old WordPress blog and where they go now (filled by the import, used for years)
        Schema::create('legacy_links', function (Blueprint $table) {
            $table->id();
            // 'post' (old blog link → blog) or 'upload' (old photo → media library item)
            $table->string('type', 10);
            // The old blog's slug, or the photo's path in uploads/ without its size ("2019/01/foto")
            $table->string('old_key');
            $table->unsignedBigInteger('target_id');
            $table->timestamps();

            $table->unique(['type', 'old_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_links');
    }
};
