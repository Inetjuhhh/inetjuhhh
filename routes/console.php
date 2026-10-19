<?php

use App\Models\Response;
use App\Models\Subscriber;
use App\Models\Blog;
use App\Support\SiteCache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('block:make', function () {
    $this->comment('Creating a new block...');

    $name = $this->ask('What is the name of the block?');
    $label = $this->ask('How should the block be labeled? i.e. Video, Crossword, etc.');

    // Convert the name to PascalCase for the class name
    $className = Str::studly($name);
    $id = Str::kebab($name);

    // Check if the class already exists
    $classPath = app_path("Filament/Blocks/{$className}Block.php");
    if (File::exists($classPath)) {
        $this->error("A block named '{$className}' already exists.");
        return;
    }

    // Create the class file
    $classContent = <<<PHP
    <?php

    namespace App\Filament\Blocks;

    use Filament\Actions\Action;
    use Filament\Forms\Components\TextInput;
    use Filament\Support\Icons\Heroicon;

    class {$className}Block extends BlogBlock
    {
        public static function getId(): string
        {
            return '{$id}';
        }

        public static function getLabel(): string
        {
            return '{$label}';
        }

        public static function getIcon(): Heroicon
        {
            return Heroicon::OutlinedSquares2x2;
        }

        public static function configureEditorAction(Action \$action): Action
        {
            return \$action
                ->modalHeading('{$label}')
                ->schema([
                    TextInput::make('name')->required(),
                ]);
        }

        public static function toPreviewHtml(array \$config): string
        {
            return view('filament.blocks.simple-preview', [
                'icon' => '🧩',
                'title' => '{$label}',
                'text' => \$config['name'] ?? '',
            ])->render();
        }

        protected static function render(array \$config): string
        {
            return view('blocks.{$id}', ['name' => \$config['name'] ?? ''])->render();
        }
    }

    PHP;

    File::ensureDirectoryExists(dirname($classPath));
    File::put($classPath, $classContent);
    $this->info("Created block class: {$classPath}");

    // Create the view for the website
    $viewPath = resource_path("views/blocks/{$id}.blade.php");
    File::ensureDirectoryExists(dirname($viewPath));
    File::put($viewPath, "<div class=\"not-prose my-8\">{{ \$name }}</div>\n");
    $this->info("Created view: {$viewPath}");

    $this->comment("Block '{$className}' created successfully. Add it to BlogBlock::all() to show it in the editor!");
})->purpose('Create a new block for the blog editor');

// Delete comments marked as spam after 30 days (see Response::prunable). Needs the scheduler:
// a cron entry running `php artisan schedule:run` every minute on the server.
Schedule::command('model:prune', ['--model' => [Response::class]])->daily();

// A planned blog goes online by itself when its date comes, without anyone saving it: then the menu
// and the map are refreshed, so it shows up there right away. (Mailing subscribers is a button in the admin.)
Artisan::command('blogs:check-planned', function () {
    // Everything that went online since the previous check, also when the scheduler was off for a while
    $since = Cache::get('blogs:check-planned:last', now()->subHour());
    $wentOnline = Blog::published()->where('published_at', '>', $since)->exists();
    Cache::forever('blogs:check-planned:last', now());

    if ($wentOnline) {
        SiteCache::forget();
    }

    $this->info($wentOnline ? 'Menu and map refreshed' : 'Nothing went online');
})->purpose('Refresh the menu and map when a planned blog goes online');

Schedule::command('blogs:check-planned')->everyFiveMinutes();

// Sign-ups for the new-blog mail that were never confirmed are removed after 30 days
Schedule::command('model:prune', ['--model' => [Subscriber::class]])->daily();

// A copy of the database every night, kept 14 days (BackupDatabase). At 03:30, when nobody writes a blog;
// if it fails, the error is in storage/logs
Schedule::command('site:backup')->dailyAt('03:30')->withoutOverlapping();

// Photo descriptions sent to Claude as batches (media:describe --batch): collected as soon as they're ready
Schedule::command('media:describe', ['--collect'])
    ->everyFifteenMinutes()
    ->when(fn () => filled(\App\Models\Setting::get(\App\Console\Commands\DescribePhotos::PENDING)))
    ->withoutOverlapping();

// A pretend error, to see that the error mail arrives (ErrorAlert; only does something on the live site)
Artisan::command('site:test-alert', function () {
    if (! app()->isProduction()) {
        $this->warn('Dit werkt alleen op de server (APP_ENV=production): op je laptop zie je fouten meteen op je scherm.');

        return;
    }

    \App\Support\ErrorAlert::send(new RuntimeException('Dit is een test van de foutmelding. Niets aan de hand!'));
    $this->info('Verstuurd naar ' . implode(', ', config('blog.admin_emails')) . ' (of al eens in het afgelopen uur: dan wacht hij een uur).');
})->purpose('Send a pretend error mail, to check that error alerts arrive');

// The backup outside Hetzner (OffsiteBackup): after the nightly copy on the server, only when it is set up
Schedule::command('site:offsite-backup')
    ->dailyAt('04:00')
    ->when(fn () => \App\Console\Commands\OffsiteBackup::isConfigured())
    ->withoutOverlapping();
