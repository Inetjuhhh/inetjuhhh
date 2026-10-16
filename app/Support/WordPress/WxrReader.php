<?php

namespace App\Support\WordPress;

use RuntimeException;
use SimpleXMLElement;

/**
 * Reads a WordPress export file (WXR: an RSS feed with WordPress additions) into plain arrays.
 */
class WxrReader
{
    /** @var array<int, array<string, mixed>> */
    public array $posts = [];

    /** @var array<int, array{id: int, url: string, title: string, alt: string, mime: string}> keyed by WordPress id */
    public array $attachments = [];

    /** @var array<string, array<string, mixed>> The fixed pages ("Over mij en ons" …), keyed by slug */
    public array $pages = [];

    public int $removedCharacters = 0;

    public function __construct(string $path)
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Export file not found: {$path}");
        }

        // WordPress copies characters XML doesn't allow from video metadata into the file;
        // a strict XML reader refuses the whole file because of them, so they go first
        $xml = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', file_get_contents($path), -1, $this->removedCharacters);

        $feed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_PARSEHUGE);

        if ($feed === false) {
            throw new RuntimeException('The export file is not valid XML.');
        }

        $ns = $feed->getNamespaces(true);

        foreach ($feed->channel->item as $item) {
            $wp = $item->children($ns['wp']);

            match ((string) $wp->post_type) {
                'post' => $this->posts[] = $this->post($item, $wp, $ns),
                'attachment' => $this->attachments[(int) $wp->post_id] = $this->attachment($item, $wp),
                'page' => $this->pages[urldecode((string) $wp->post_name)] = $this->post($item, $wp, $ns),
                default => null,
            };
        }
    }

    private function post(SimpleXMLElement $item, SimpleXMLElement $wp, array $ns): array
    {
        $categories = $tags = [];
        foreach ($item->category as $term) {
            if ((string) $term['domain'] === 'post_tag') {
                $tags[] = html_entity_decode((string) $term);
            } elseif ((string) $term['domain'] === 'category') {
                $categories[] = html_entity_decode((string) $term);
            }
        }

        $comments = [];
        foreach ($wp->comment as $comment) {
            $comments[] = [
                'id' => (int) $comment->comment_id,
                'parent' => (int) $comment->comment_parent,
                'author' => (string) $comment->comment_author,
                'email' => (string) $comment->comment_author_email,
                'date_gmt' => (string) $comment->comment_date_gmt,
                'content' => (string) $comment->comment_content,
                'approved' => (string) $comment->comment_approved,
                'by_author' => (int) $comment->comment_user_id > 0,
            ];
        }

        return [
            'id' => (int) $wp->post_id,
            'title' => html_entity_decode((string) $item->title, ENT_QUOTES | ENT_HTML5),
            'slug' => urldecode((string) $wp->post_name),
            'link' => (string) $item->link,
            'status' => (string) $wp->status,
            'date' => (string) $wp->post_date,
            'date_gmt' => (string) $wp->post_date_gmt,
            'content' => (string) $item->children($ns['content'])->encoded,
            'excerpt' => trim((string) $item->children($ns['excerpt'])->encoded),
            'categories' => $categories,
            'tags' => $tags,
            'thumbnail_id' => (int) $this->meta($wp, '_thumbnail_id'),
            'comments' => $comments,
        ];
    }

    private function attachment(SimpleXMLElement $item, SimpleXMLElement $wp): array
    {
        return [
            'id' => (int) $wp->post_id,
            'url' => (string) $wp->attachment_url,
            'title' => html_entity_decode((string) $item->title, ENT_QUOTES | ENT_HTML5),
            'alt' => (string) $this->meta($wp, '_wp_attachment_image_alt'),
            'mime' => strtolower(pathinfo(parse_url((string) $wp->attachment_url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)),
        ];
    }

    private function meta(SimpleXMLElement $wp, string $key): ?string
    {
        foreach ($wp->postmeta as $meta) {
            if ((string) $meta->meta_key === $key) {
                return (string) $meta->meta_value;
            }
        }

        return null;
    }
}
