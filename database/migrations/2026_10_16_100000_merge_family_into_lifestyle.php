<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Family and Lifestyle become one category, "Lifestyle", and categories get a slug for their page
 * (/categorie/lifestyle). Blogs that were in both keep one link to Lifestyle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        $family = DB::table('categories')->where('name', 'Family')->value('id');

        if ($family) {
            $lifestyle = DB::table('categories')->where('name', 'Lifestyle')->value('id')
                ?? DB::table('categories')->insertGetId(['name' => 'Lifestyle', 'created_at' => now(), 'updated_at' => now()]);

            $alreadyLifestyle = DB::table('blog_subcategory')->where('category_id', $lifestyle)->pluck('blog_id');

            DB::table('blog_subcategory')->where('category_id', $family)->whereIn('blog_id', $alreadyLifestyle)->delete();
            DB::table('blog_subcategory')->where('category_id', $family)->update(['category_id' => $lifestyle]);
            DB::table('categories')->where('id', $family)->delete();
        }

        foreach (DB::table('categories')->get(['id', 'name']) as $category) {
            DB::table('categories')->where('id', $category->id)->update(['slug' => Str::slug($category->name)]);
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        // Which blogs were Family can't be told apart afterwards; the backup from before has that
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
