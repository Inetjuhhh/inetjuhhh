<?php

namespace App\View\Components;

use App\Support\Avatar;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * The site's page layout. Besides the title, a page can say what it is about: that becomes the
 * description for Google and the preview when someone shares the page (WhatsApp, Facebook, …).
 */
class AppLayout extends Component
{
    public const DESCRIPTION = 'Reisverhalen, familie en lifestyle van Ine, Bas en Sam. Life is short, so enjoy it!';

    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        // A full address of a photo for the share preview; without one, the avatar
        public ?string $image = null,
        // "article" for a blog, "website" for the other pages
        public string $type = 'website',
        public ?string $publishedAt = null,
        // Pages that search engines should leave out of their results (a preview of a draft)
        public bool $noindex = false,
        // Where the page sits on the site, for Google (Home › Reizen › this story): [name => address, …] after Home
        public array $breadcrumbs = [],
    ) {
        $this->description ??= self::DESCRIPTION;
        $this->image ??= Avatar::url(512);
    }

    /**
     * The path for Google as structured data (schema.org BreadcrumbList), starting at Home. Every step has
     * its address, the last one too: Google calls a step without one invalid. Null on the home page itself.
     */
    public function breadcrumbData(): ?array
    {
        if ($this->breadcrumbs === []) {
            return null;
        }

        $steps = ['Home' => route('blogs.index')] + $this->breadcrumbs;

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (string $name, string $url, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $name,
                'item' => $url,
            ], array_keys($steps), $steps, range(0, count($steps) - 1)),
        ];
    }

    public function render(): View
    {
        return view('layouts.app');
    }
}
