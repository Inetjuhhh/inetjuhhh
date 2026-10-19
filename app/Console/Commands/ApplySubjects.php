<?php

namespace App\Console\Commands;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Tag;
use App\Support\SiteCache;
use Illuminate\Console\Command;
use Illuminate\Console\Prohibitable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gives the stories their subjects from config/subjects.php: old WordPress tags renamed to a theme or
 * removed, themes per story added, a few stories moved to the right category and leftovers taken offline.
 * Runs again without harm; the WordPress import runs it at the end.
 *
 *   php artisan blogs:subjects --dry-run   shows what would change
 *   php artisan blogs:subjects             applies it
 */
class ApplySubjects extends Command
{
    // Refused on the live server (AppServiceProvider): it would undo subjects and categories set in the admin
    use Prohibitable;

    protected $signature = 'blogs:subjects {--dry-run : Only show what would change}';

    protected $description = 'Give the stories their subjects (themes) from config/subjects.php';

    public function handle(): int
    {
        $config = config('subjects');
        $rename = collect($config['tags'])->mapWithKeys(fn (string $to, string $from) => [mb_strtolower($from) => $to]);
        $remove = collect($config['remove'])->map(fn (string $name) => mb_strtolower($name))->flip();
        $stats = ['tags changed' => 0, 'categories changed' => 0, 'taken offline' => 0, 'not found' => 0];

        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && $this->isProhibited()) {
            return self::FAILURE;
        }

        DB::beginTransaction();

        foreach (Blog::with(['tags', 'categories'])->get() as $blog) {
            $subjects = $blog->tags->pluck('name')
                ->reject(fn (string $name) => $remove->has(mb_strtolower($name)))
                ->map(fn (string $name) => $rename[mb_strtolower($name)] ?? $name)
                ->concat($config['stories'][$blog->slug] ?? [])
                ->unique(fn (string $name) => mb_strtolower($name))
                ->values();

            if ($this->differs($blog->tags->pluck('name'), $subjects)) {
                $stats['tags changed']++;
                $blog->tags()->sync($subjects->map(fn (string $name) => $this->tag($name)->id));
            }

            if ($move = $config['categories'][$blog->slug] ?? null) {
                $ids = $blog->categories->pluck('id')
                    ->when(isset($move['remove']), fn ($ids) => $ids->diff([$this->category($move['remove'])->id]))
                    ->when(isset($move['add']), fn ($ids) => $ids->push($this->category($move['add'])->id))
                    ->unique()->values();

                if ($ids->sort()->values()->all() !== $blog->categories->pluck('id')->sort()->values()->all()) {
                    $stats['categories changed']++;
                    $blog->categories()->sync($ids);
                }
            }

            if (in_array($blog->slug, $config['drafts'], true) && $blog->status === 'published') {
                $stats['taken offline']++;
                $blog->update(['status' => 'draft']);
            }
        }

        $stats['not found'] = collect(array_keys($config['stories']))->diff(Blog::pluck('slug'))->count();

        // Tags no story uses any more (the removed places …) go
        $unused = Tag::doesntHave('blogs')->count();
        Tag::doesntHave('blogs')->delete();

        if ($dryRun) {
            DB::rollBack();
            $this->warn('Dry run: nothing was changed.');
        } else {
            DB::commit();
            // Related stories are picked by subject, and the menu by category
            SiteCache::forget();
        }

        $this->table(['', 'stories'], collect($stats)->map(fn ($n, $what) => [$what, $n])->push(['unused tags removed', $unused])->values());

        return self::SUCCESS;
    }

    private function differs(Collection $old, Collection $new): bool
    {
        return $old->sort()->values()->all() !== $new->sort()->values()->all();
    }

    /** @var array<string, Tag> */
    private array $tags = [];

    private function tag(string $name): Tag
    {
        if (! isset($this->tags[mb_strtolower($name)])) {
            $tag = Tag::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first() ?? Tag::create(['name' => $name]);

            // "familie" from WordPress becomes the theme "Familie"
            if ($tag->name !== $name) {
                $tag->update(['name' => $name]);
            }

            $this->tags[mb_strtolower($name)] = $tag;
        }

        return $this->tags[mb_strtolower($name)];
    }

    private function category(string $name): Category
    {
        return Category::firstOrCreate(['name' => $name]);
    }
}
