<?php

namespace Database\Seeders;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Country;
use App\Models\Response;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class BlogSeeder extends Seeder
{
    /**
     * Seeds realistic travel blogs from database/seeders/data/blogs.
     * Keyed on slug, so running it again updates the posts instead of duplicating them.
     */
    public function run(): void
    {
        $author = User::where('email', 'ine@inetjuhhh.nl')->first() ?? User::first();

        foreach (glob(__DIR__ . '/data/blogs/*.php') as $file) {
            $data = require $file;
            $date = Carbon::parse($data['published_at']);

            $blog = Blog::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'title' => $data['title'],
                    'excerpt' => $data['excerpt'],
                    'content' => $this->toTiptap($data['body']),
                    'status' => 'published',
                    'published_at' => $date,
                    'placed_by_id' => $author->id,
                ]
            );
            // The frontend sorts and shows dates by created_at
            $blog->timestamps = false;
            $blog->forceFill(['created_at' => $date, 'updated_at' => $date])->save();

            $blog->categories()->sync(
                Category::whereIn('name', $data['categories'])->pluck('id')
            );
            $blog->countries()->sync(
                collect($data['countries'])->map(fn ($name) => Country::firstOrCreate(['name' => $name])->id)
            );
            $blog->tags()->sync(
                collect($data['tags'])->map(fn ($name) => Tag::firstOrCreate(['name' => $name])->id)
            );

            $blog->responses()->delete();
            foreach ($data['responses'] ?? [] as $response) {
                $responseDate = $date->copy()->addDays($response['days_after'])->setTime(rand(8, 22), rand(0, 59));

                $model = new Response([
                    'name' => $response['name'],
                    'email' => $response['email'],
                    'response' => $response['text'],
                ]);
                $model->timestamps = false;
                $model->created_at = $responseDate;
                $model->updated_at = $responseDate;
                $blog->responses()->save($model);
            }
        }
    }

    /**
     * Converts simple Markdown (## headings, paragraphs, - lists, 1. lists, > quotes, **bold**, *italic*, @block lines)
     * into the TipTap JSON document the Filament editor stores.
     */
    private function toTiptap(string $markdown): array
    {
        $blocks = preg_split('/\n\s*\n/', trim(str_replace("\r\n", "\n", $markdown)));
        $content = [];

        foreach ($blocks as $block) {
            $lines = array_map('trim', explode("\n", trim($block)));

            if (str_starts_with($lines[0], '@block ')) {
                // Editor block, e.g. @block {"id": "tip", "config": {"type": "tip", "text": "..."}}
                $block = json_decode(substr(implode(' ', $lines), 7), true, flags: JSON_THROW_ON_ERROR);
                $content[] = ['type' => 'customBlock', 'attrs' => ['id' => $block['id'], 'config' => $block['config']]];
            } elseif (preg_match('/^(#{2,3}) (.+)$/', $lines[0], $m)) {
                $content[] = [
                    'type' => 'heading',
                    'attrs' => ['textAlign' => 'start', 'level' => strlen($m[1])],
                    'content' => $this->inline($m[2]),
                ];
            } elseif (str_starts_with($lines[0], '- ') || preg_match('/^\d+\. /', $lines[0])) {
                $ordered = ! str_starts_with($lines[0], '- ');
                $content[] = [
                    'type' => $ordered ? 'orderedList' : 'bulletList',
                    'content' => array_map(fn ($line) => [
                        'type' => 'listItem',
                        'content' => [$this->paragraph(preg_replace('/^(- |\d+\. )/', '', $line))],
                    ], $lines),
                ];
            } elseif (str_starts_with($lines[0], '> ')) {
                $text = implode(' ', array_map(fn ($line) => ltrim(substr($line, 1)), $lines));
                $content[] = ['type' => 'blockquote', 'content' => [$this->paragraph($text)]];
            } else {
                $content[] = $this->paragraph(implode(' ', $lines));
            }
        }

        return ['type' => 'doc', 'content' => $content];
    }

    private function paragraph(string $text): array
    {
        return [
            'type' => 'paragraph',
            'attrs' => ['textAlign' => 'start', 'class' => null, 'style' => null],
            'content' => $this->inline($text),
        ];
    }

    private function inline(string $text): array
    {
        $parts = preg_split('/(\*\*[^*]+\*\*|\*[^*]+\*)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        return array_map(function ($part) {
            if (preg_match('/^\*\*(.+)\*\*$/', $part, $m)) {
                return ['type' => 'text', 'text' => $m[1], 'marks' => [['type' => 'bold']]];
            }
            if (preg_match('/^\*(.+)\*$/', $part, $m)) {
                return ['type' => 'text', 'text' => $m[1], 'marks' => [['type' => 'italic']]];
            }

            return ['type' => 'text', 'text' => $part];
        }, $parts);
    }
}
