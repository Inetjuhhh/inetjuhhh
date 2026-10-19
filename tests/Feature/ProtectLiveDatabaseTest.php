<?php

use App\Console\Commands\ApplySubjects;
use App\Console\Commands\ImportWordPress;
use App\Models\Blog;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Database\Console\Seeds\SeedCommand;
use Illuminate\Support\Facades\DB;

// As if this were the live server: the app boots with APP_ENV=production
beforeEach(function () {
    app()->detectEnvironment(fn () => 'production');
    (new AppServiceProvider(app()))->boot();

    $this->blog = Blog::create([
        'title' => 'Echte blog', 'slug' => 'echte-blog', 'excerpt' => 'Intro', 'status' => 'published',
        'placed_by_id' => User::factory()->create()->id, 'content' => ['type' => 'doc', 'content' => []],
    ]);
});

// The protection is static: switch it off again, so other tests can use these commands
afterEach(function () {
    DB::prohibitDestructiveCommands(false);
    SeedCommand::prohibit(false);
    ImportWordPress::prohibit(false);
    ApplySubjects::prohibit(false);
});

it('refuses commands that empty the database on the live server, also with --force', function (string $command) {
    $this->artisan($command, ['--force' => true])->assertFailed();

    expect(Blog::find($this->blog->id))->not->toBeNull();
})->with(['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback', 'db:wipe', 'db:seed']);

it('refuses the WordPress import and the subjects script on the live server', function () {
    $this->artisan('wordpress:import')->assertFailed();
    $this->artisan('blogs:subjects')->assertFailed();
});

it('still allows only looking with --dry-run', function () {
    $this->artisan('blogs:subjects', ['--dry-run' => true])->assertSuccessful();
});

it('still runs new migrations on the live server', function () {
    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
});
