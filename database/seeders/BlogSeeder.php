<?php

namespace Database\Seeders;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Country;
use App\Models\Response;
use App\Models\Tag;
use App\Models\Trip;
use App\Models\User;
use Awcodes\Curator\Models\Media;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class BlogSeeder extends Seeder
{
    /**
     * Trips the seeded blogs can belong to (by name, with 'trip' => '...' in a blog's data file).
     */
    private const TRIPS = [
        'Thailand 2015' => [
            'starts_on' => '2015-02-02',
            'ends_on' => '2015-03-01',
            'description' => 'Onze eerste grote reis samen: vier weken backpacken van Bangkok via de bergen in het noorden naar de eilanden in de Golf van Thailand.',
        ],
        'India & Nepal 2016' => [
            'starts_on' => '2016-10-08',
            'ends_on' => '2016-11-19',
            'description' => 'Zes weken India en Nepal: van het gekkenhuis van Delhi naar de ghats van Varanasi, en tien dagen lopen naar Annapurna Base Camp.',
        ],
        'Thailand & Cambodja 2017' => [
            'starts_on' => '2017-11-04',
            'ends_on' => '2017-12-02',
            'description' => 'Vier weken over land van Bangkok naar de tempels van Angkor, de geschiedenis van Phnom Penh en de peperplantages van Kampot.',
        ],
        'Indonesië 2018' => [
            'starts_on' => '2018-07-07',
            'ends_on' => '2018-08-11',
            'description' => 'Vijf weken eilandhoppen: de tempels en vulkanen van Java, de rijstvelden van Bali en een boottocht naar de komodovaranen.',
        ],
        'Peru 2019' => [
            'starts_on' => '2019-04-20',
            'ends_on' => '2019-05-18',
            'description' => 'Vier weken Peru: eten in Lima, condors in de Colca Canyon, een nacht op het Titicacameer en de Inca Trail naar Machu Picchu.',
        ],
        'Normandië 2020' => [
            'starts_on' => '2020-08-08',
            'ends_on' => '2020-08-22',
            'description' => 'Onze huwelijksreis in het coronajaar: twee weken met de auto langs de kliffen, kaasboerderijen en stranden van Normandië.',
        ],
        'IJsland 2021' => [
            'starts_on' => '2021-06-19',
            'ends_on' => '2021-07-03',
            'description' => 'Een babymoon met een buik van 28 weken: twee weken IJsland, met watervallen, gletsjers en een vulkaan die gewoon aan het uitbarsten was.',
        ],
        'Denemarken 2021' => [
            'starts_on' => '2021-10-23',
            'ends_on' => '2021-11-06',
            'description' => 'De eerste reis met Sam, toen zeven weken oud: een sommerhus aan de Deense westkust, Skagen en een paar dagen Kopenhagen.',
        ],
        'Vietnam 2022' => [
            'starts_on' => '2022-11-05',
            'ends_on' => '2022-12-03',
            'description' => 'Vier weken Vietnam met een dreumes van veertien maanden, van Hanoi via Ninh Binh en Hoi An naar de Mekongdelta.',
        ],
        'Noorwegen & Zweden 2023' => [
            'starts_on' => '2023-07-01',
            'ends_on' => '2023-07-29',
            'description' => 'Vier weken met de camper door Scandinavië: Preikestolen, de fjorden, de Lofoten onder de middernachtzon en terug door Zweden.',
        ],
        'Spanje 2024' => [
            'starts_on' => '2024-04-27',
            'ends_on' => '2024-05-18',
            'description' => 'Drie weken Andalusië in de lente, met een bijna-driejarige: Sevilla, het Alhambra, de witte dorpen en de stranden van Cabo de Gata.',
        ],
        'Thailand 2025' => [
            'starts_on' => '2025-01-11',
            'ends_on' => '2025-02-08',
            'description' => 'Tien jaar na onze eerste reis terug in Thailand, nu met z\'n drieën: Bangkok, Chiang Mai, de jungle van Khao Sok en de eilanden.',
        ],
        'Costa Rica 2026' => [
            'starts_on' => '2026-12-19',
            'ends_on' => '2027-01-16',
            'description' => 'Onze volgende reis: vier weken Costa Rica rond kerst en oud en nieuw, met vulkanen, regenwoud, luiaards en schildpadden.',
        ],
    ];

    // Where downloaded photos are kept, so seeding again does not download them again
    private const IMAGE_DIRECTORY = 'reizen';

    private const IMAGE_WIDTH = 1920;

    /**
     * Seeds realistic travel blogs from database/seeders/data/blogs.
     * Keyed on slug, so running it again updates the posts instead of duplicating them.
     */
    public function run(): void
    {
        $author = User::whereIn('email', config('blog.admin_emails'))->first()
            ?? User::where('email', 'inetjuhhh@gmail.com')->first()
            ?? User::first();

        $trips = collect(self::TRIPS)->map(fn (array $trip, string $name) => Trip::updateOrCreate(['name' => $name], $trip));

        // Photos per blog (slug): the first is the cover, the others are used in the text as "img:1", "img:2", ...
        $photos = require __DIR__ . '/data/images.php';

        $files = glob(__DIR__ . '/data/blogs/*.php');
        $this->command?->getOutput()->progressStart(count($files));

        foreach ($files as $file) {
            $data = require $file;
            $date = Carbon::parse($data['published_at']);

            $images = collect($photos[$data['slug']] ?? [])
                ->map(fn (array $image, int $i) => $this->importImage($data['slug'] . '-' . ($i + 1), $image));

            $blog = Blog::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'title' => $data['title'],
                    'excerpt' => $data['excerpt'],
                    'content' => $this->toTiptap($data['body'], $images->all()),
                    'featured_image_id' => $images->first()?->id,
                    'status' => 'published',
                    'published_at' => $date,
                    'placed_by_id' => $author->id,
                    // Coordinates are given, so seeding never needs the online place lookup
                    'location_name' => $data['location']['name'] ?? null,
                    'latitude' => $data['location']['lat'] ?? null,
                    'longitude' => $data['location']['lng'] ?? null,
                    'trip_id' => isset($data['trip']) ? $trips[$data['trip']]->id : null,
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

            $this->seedResponses($blog, $date, $data['responses'] ?? []);

            $this->command?->getOutput()->progressAdvance();
        }

        $this->command?->getOutput()->progressFinish();
    }

    private function seedResponses(Blog $blog, Carbon $date, array $responses): void
    {
        $blog->responses()->delete();
        $lastReaderComment = null;

        foreach ($responses as $response) {
            $responseDate = $date->copy()->addDays($response['days_after'])->setTime(rand(8, 22), rand(0, 59));
            // Comments by the blog owner answer the reader comment just above them
            $isAuthor = $response['email'] === 'ine@inetjuhhh.nl';

            $model = new Response([
                'parent_id' => $isAuthor ? $lastReaderComment?->id : null,
                'name' => $response['name'],
                'email' => $response['email'],
                'response' => $response['text'],
                'status' => Response::APPROVED,
                'approved_at' => $responseDate,
                'is_author' => $isAuthor,
            ]);
            $model->timestamps = false;
            $model->created_at = $responseDate;
            $model->updated_at = $responseDate;
            $blog->responses()->save($model);

            if (! $isAuthor) {
                $lastReaderComment = $model;
            }
        }
    }

    /**
     * Puts a photo from Wikimedia Commons in the media library. Downloaded once; when the
     * download fails (offline), the blog is seeded without that photo.
     *
     * @param  array{file: string, alt: string, credit?: string}  $image
     */
    private function importImage(string $name, array $image): ?Media
    {
        $disk = Storage::disk('public');
        $path = self::IMAGE_DIRECTORY . "/{$name}.jpg";

        if (! $disk->exists($path)) {
            $contents = $this->download($image['file']);

            if ($contents === null) {
                $this->command?->warn(" Foto niet gedownload: {$image['file']}");

                return null;
            }

            $disk->put($path, $contents);
        }

        [$width, $height] = @getimagesize($disk->path($path)) ?: [null, null];

        $media = Media::firstOrNew(['disk' => 'public', 'path' => $path]);
        $media->forceFill([
            'directory' => self::IMAGE_DIRECTORY,
            'visibility' => 'public',
            'name' => $name,
            'width' => $width,
            'height' => $height,
            'size' => $disk->size($path),
            'type' => 'image/jpeg',
            'ext' => 'jpg',
            'alt' => $image['alt'],
            'title' => $image['alt'],
            'description' => isset($image['credit']) ? "Foto: {$image['credit']}, via Wikimedia Commons ({$image['file']})" : null,
        ])->save();

        return $media;
    }

    private function download(string $file): ?string
    {
        $url = 'https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode(str_replace(' ', '_', $file));

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            try {
                $response = Http::withUserAgent('InetjuhhhBlogSeeder/1.0 (local development seeder)')
                    ->timeout(60)
                    ->get($url, ['width' => self::IMAGE_WIDTH]);
            } catch (\Throwable) {
                $response = null;
            }

            if ($response?->successful() && str_starts_with((string) $response->header('Content-Type'), 'image/')) {
                // Wikimedia asks to keep the request rate low
                usleep(300_000);

                return $response->body();
            }

            // Too many requests: wait a bit longer each time
            sleep($attempt * 3);
        }

        return null;
    }

    /**
     * Converts simple Markdown (## headings, paragraphs, - lists, 1. lists, > quotes, **bold**, *italic*, @block lines)
     * into the TipTap JSON document the Filament editor stores.
     *
     * @param  array<int, Media|null>  $images
     */
    private function toTiptap(string $markdown, array $images = []): array
    {
        $blocks = preg_split('/\n\s*\n/', trim(str_replace("\r\n", "\n", $markdown)));
        $content = [];

        foreach ($blocks as $block) {
            $lines = array_map('trim', explode("\n", trim($block)));

            if (str_starts_with($lines[0], '@block ')) {
                // Editor block, e.g. @block {"id": "photo", "config": {"media": "img:1", "caption": "..."}}
                $block = json_decode(substr(implode(' ', $lines), 7), true, flags: JSON_THROW_ON_ERROR);

                if (isset($block['config']['media'])) {
                    $block['config']['media'] = $this->mediaIds($block['config']['media'], $images);

                    // The photo could not be downloaded: leave the block out
                    if ($block['config']['media'] === null || $block['config']['media'] === []) {
                        continue;
                    }
                }

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

    /**
     * "img:2" (photo block) or ["img:1", "img:2"] (gallery) to the ids of the imported photos.
     */
    private function mediaIds(string|array $reference, array $images): int|array|null
    {
        $resolve = fn (string $ref) => ($images[(int) substr($ref, 4)] ?? null)?->id;

        return is_array($reference)
            ? array_values(array_filter(array_map($resolve, $reference)))
            : $resolve($reference);
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
