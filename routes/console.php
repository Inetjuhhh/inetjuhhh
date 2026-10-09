<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
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
