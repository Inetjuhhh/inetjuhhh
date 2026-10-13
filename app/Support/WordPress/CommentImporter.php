<?php

namespace App\Support\WordPress;

use App\Models\Blog;
use App\Models\Response;
use Illuminate\Support\Carbon;

/**
 * Brings a post's WordPress comments over as reactions.
 *
 * WordPress allows replies to replies to replies; this blog shows reader comments with only the
 * owner's replies underneath. So the owner's replies go under the top comment of their thread,
 * and a reader answering another reader becomes a comment of its own, in date order.
 * Nobody is mailed: imported comments never ask for a reply notification.
 */
class CommentImporter
{
    /** @var array<string, int> */
    public array $stats = ['approved' => 0, 'pending' => 0, 'replies' => 0, 'skipped' => 0];

    /**
     * @param  list<string>  $ownerEmails  Addresses that count as the blog owner
     */
    public function __construct(private array $ownerEmails, private bool $withPending = true)
    {
        $this->ownerEmails = array_map('mb_strtolower', $ownerEmails);
    }

    /**
     * What would come along, without writing anything (for the dry run).
     */
    public function count(array $comments): void
    {
        foreach ($comments as $comment) {
            match ($this->status($comment)) {
                null => $this->stats['skipped']++,
                default => $this->isOwner($comment) && $comment['parent'] ? $this->stats['replies']++ : $this->stats[$this->status($comment)]++,
            };
        }
    }

    public function import(Blog $blog, array $comments): void
    {
        $byId = collect($comments)->keyBy('id');
        $saved = [];   // WordPress comment id → reaction id

        foreach (collect($comments)->sortBy('date_gmt') as $comment) {
            $status = $this->status($comment);
            if ($status === null) {
                $this->stats['skipped']++;

                continue;
            }

            $owner = $this->isOwner($comment);
            $root = $this->rootOf($comment, $byId);
            // Only the owner's replies sit under a comment, and always under the top one of the thread
            $parentId = $owner && $root && isset($saved[$root]) ? $saved[$root] : null;

            $reaction = Response::firstOrNew(['wordpress_comment_id' => $comment['id']]);
            $date = Carbon::parse($comment['date_gmt'], 'UTC');

            $reaction->fill([
                'blog_id' => $blog->id,
                'parent_id' => $parentId,
                'name' => trim($comment['author']) ?: 'Anoniem',
                'email' => mb_strtolower(trim($comment['email'])),
                'response' => $this->text($comment['content']),
                'status' => $status,
                'is_author' => $owner,
                'notify_on_reply' => false,
                'approved_at' => $status === Response::APPROVED ? $date : null,
            ]);
            $reaction->created_at = $date;
            $reaction->updated_at = $date;
            $reaction->save();

            $saved[$comment['id']] = $reaction->id;
            $parentId ? $this->stats['replies']++ : $this->stats[$status]++;
        }
    }

    /**
     * Approved, waiting (when wanted), or null for spam and the trash.
     */
    private function status(array $comment): ?string
    {
        return match ($comment['approved']) {
            '1' => Response::APPROVED,
            '0' => $this->withPending ? Response::PENDING : null,
            default => null,
        };
    }

    private function isOwner(array $comment): bool
    {
        return $comment['by_author'] || in_array(mb_strtolower(trim($comment['email'])), $this->ownerEmails, true);
    }

    /**
     * The top comment of a thread (null when the comment is the top one itself).
     */
    private function rootOf(array $comment, $byId): ?int
    {
        $root = null;
        $current = $comment;
        for ($guard = 0; $current['parent'] && $byId->has($current['parent']) && $guard < 50; $guard++) {
            $current = $byId[$current['parent']];
            $root = $current['id'];
        }

        return $root;
    }

    // WordPress keeps a little HTML in comments; here they are plain text with their line breaks
    private function text(string $html): string
    {
        $text = preg_replace('#<br\s*/?>|</p>#i', "\n", $html);

        return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5));
    }
}
