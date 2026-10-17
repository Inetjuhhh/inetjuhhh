<?php

use App\Filament\Resources\BlogResource\Pages\CreateBlog;
use App\Models\Blog;
use App\Models\Tag;
use App\Models\User;
use App\Support\TagSuggester;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function suggestTagsAction(): TestAction
{
    return TestAction::make('suggestTags')->schemaComponent('tags');
}

it('adds the suggested subjects to the tags already chosen', function () {
    $nature = Tag::create(['name' => 'Natuur']);
    $food = Tag::create(['name' => 'Eten']);
    $sam = Tag::create(['name' => 'Sam']);

    // The real call goes to Claude; here we only check what the form does with the answer
    $this->mock(TagSuggester::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('suggest')
            ->withArgs(fn ($title, $intro, $text) => $title === 'Wandelen in de jungle' && str_contains($text, 'apen'))
            ->andReturn(['Natuur', 'Eten']);
    });

    Livewire::test(CreateBlog::class)
        ->fillForm([
            'title' => 'Wandelen in de jungle',
            'content' => ['type' => 'doc', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'We zagen apen en aten rijst.']]],
            ]],
            'tags' => [(string) $sam->id],
        ])
        ->callAction(suggestTagsAction())
        ->assertNotified('Voorgesteld: Natuur, Eten')
        ->assertSchemaStateSet(['tags' => [(string) $sam->id, (string) $nature->id, (string) $food->id]]);
});

it('explains that the key is missing instead of calling Claude', function () {
    config(['services.anthropic.key' => null]);

    Livewire::test(CreateBlog::class)
        ->fillForm(['title' => 'Een verhaal'])
        ->callAction(suggestTagsAction())
        ->assertNotified('Geen AI-sleutel ingesteld')
        ->assertSchemaStateSet(['tags' => []]);
});

it('offers the existing subjects, the most used first', function () {
    $blog = Blog::create(['title' => 'A', 'slug' => 'a', 'excerpt' => '', 'status' => 'draft', 'placed_by_id' => $this->user->id, 'content' => ['type' => 'doc', 'content' => []]]);
    Tag::create(['name' => 'Weinig']);
    Tag::create(['name' => 'Vaak'])->blogs()->attach($blog);

    expect(app(TagSuggester::class)->existingTags()->all())->toBe(['Vaak' => 1, 'Weinig' => 0]);
});
