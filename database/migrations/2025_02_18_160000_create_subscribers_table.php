<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Readers who get an email when a new blog is published
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('email', 190)->unique();
            // Secret for the unsubscribe link in every mail
            $table->string('token', 64)->unique();
            // Null until the reader clicked the link in the confirmation mail
            $table->timestamp('confirmed_at')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};
