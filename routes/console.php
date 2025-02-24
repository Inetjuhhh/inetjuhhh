<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

Artisan::command('block:make', function () {
    $this->comment('Creating a new block...');

    $name = $this->ask('What is the name of the block?');
    $category = $this->ask('What is the category of the block? i.e. Games, Embeds, etc.');
    $icon = $this->ask('What is the icon of the block? i.e. heroicon-o-film, heroicon-o-newspaper, etc.');
    if (!Str::startsWith($icon, 'heroicon-')) {
        $icon = 'heroicon-o-film';

    }
    $label = $this->ask('How should the block be labeled? i.e. Video, Crossword, etc.');

    // Convert the name to PascalCase for the class name
    $className = Str::studly($name);

    // Check if the class already exists
    $classPath = app_path("Blocks/{$className}Block.php");
    if (File::exists($classPath)) {
        $this->error("A block named '{$className}' already exists.");
        return;
    }

    // Create the class file
    $classContent = "<?php\n" .
        "namespace App\\Blocks;\n" .
        "use Filament\\Forms\\Components\\TextInput;\n" .
        "use FilamentTiptapEditor\\TiptapBlock;\n\n" .
        "class {$className}Block extends TiptapBlock\n" .
        "{\n" .
        "    public string \$preview = \"tiptapblocks.preview." . Str::kebab($name) . "\";\n" .
        "    public string \$rendered = \"tiptapblocks.rendered." . Str::kebab($name) . "\";\n" .
        "    public string \$category = '{$category}';\n" .

        "    public string \$width = 'xl';\n" .
        "    public bool \$slideOver = true;\n" .
        "    public ?string \$icon = '{$icon}';\n\n" .
        "    public function getLabel(): string\n" .
        "    {\n" .
        "        return '{$label}';\n" .
        "    }\n\n" .
        "    public function getFormSchema(): array\n" .
        "    {\n" .
        "        return [\n" .
        "            TextInput::make('name'),\n" .
        "        ];\n" .
        "    }\n" .
        "}\n";

    File::ensureDirectoryExists(app_path('Blocks'));
    File::put($classPath, $classContent);
    $this->info("Created block class: {$classPath}");

    // Create the view files
    $viewPaths = [
        resource_path("views/tiptapblocks/rendered/" . Str::kebab($name) . ".blade.php"),
        resource_path("views/tiptapblocks/preview/" . Str::kebab($name) . ".blade.php"),
    ];

    foreach ($viewPaths as $viewPath) {
        File::ensureDirectoryExists(dirname($viewPath));
        File::put($viewPath, "<div>{{ \$name }}</div>");
        $this->info("Created view: {$viewPath}");
    }

    $this->comment("Block '{$className}' created successfully. Be sure to register it in the BlockServiceProvider!");
})->purpose('Create a new block');

