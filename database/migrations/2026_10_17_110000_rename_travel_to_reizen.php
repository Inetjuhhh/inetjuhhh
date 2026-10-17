<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The travel category is called "Reizen" on the site (menu, heading, /categorie/reizen).
 * /categorie/travel keeps working: it redirects (routes/web.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('categories')->where('slug', 'travel')->update(['name' => 'Reizen', 'slug' => 'reizen']);
    }

    public function down(): void
    {
        DB::table('categories')->where('slug', 'reizen')->update(['name' => 'Travel', 'slug' => 'travel']);
    }
};
