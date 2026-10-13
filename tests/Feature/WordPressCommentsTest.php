<?php

use App\Models\Blog;
use App\Models\Response;
use App\Models\User;
use App\Support\WordPress\CommentImporter;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Mail::fake();
    Notification::fake();

    $this->blog = Blog::create([
        'title' => 'De andersom-jetlag', 'slug' => 'de-andersom-jetlag', 'excerpt' => 'Intro', 'status' => 'published',
        'placed_by_id' => User::factory()->create()->id, 'content' => ['type' => 'doc', 'content' => []],
    ]);
});

function wpComment(int $id, string $author, string $text, int $parent = 0, string $approved = '1', bool $byAuthor = false, string $email = 'lezer@example.com'): array
{
    return ['id' => $id, 'parent' => $parent, 'author' => $author, 'email' => $email, 'date_gmt' => "2015-08-0{$id} 10:00:00",
        'content' => $text, 'approved' => $approved, 'by_author' => $byAuthor];
}

it('puts your replies under the top comment of their thread', function () {
    (new CommentImporter(['ine@inetjuhhh.nl']))->import($this->blog, [
        wpComment(1, 'Papa', 'Heel leuk verhaal!'),
        wpComment(2, 'Ine', 'Dankjewel!', parent: 1, byAuthor: true),
        wpComment(3, 'Papa', 'Graag gedaan', parent: 2),
        wpComment(4, 'Ine', 'Tot snel', parent: 3, email: 'ine@inetjuhhh.nl'),
    ]);

    $papa = Response::where('wordpress_comment_id', 1)->first();

    // Both of your replies sit under Papa's first comment, however deep they were in WordPress
    expect($papa->replies()->pluck('response')->all())->toBe(['Dankjewel!', 'Tot snel'])
        // A reader answering in the thread becomes a comment of its own
        ->and(Response::where('wordpress_comment_id', 3)->value('parent_id'))->toBeNull()
        ->and($this->blog->comments()->count())->toBe(2);
});

it('keeps waiting comments waiting, and leaves spam behind', function () {
    $importer = new CommentImporter([]);
    $importer->import($this->blog, [
        wpComment(1, 'Fien', 'Mooi!'),
        wpComment(2, 'Kees', 'Wacht nog', approved: '0'),
        wpComment(3, 'Bot', 'Koop nu', approved: 'spam'),
    ]);

    expect(Response::pluck('status', 'name')->all())->toBe(['Fien' => 'approved', 'Kees' => 'pending'])
        ->and($importer->stats['skipped'])->toBe(1);
});

it('never mails anyone and makes no duplicates on a second run', function () {
    $comments = [wpComment(1, 'Papa', 'Leuk!'), wpComment(2, 'Ine', 'Dank je!', parent: 1, byAuthor: true)];

    (new CommentImporter([]))->import($this->blog, $comments);
    (new CommentImporter([]))->import($this->blog, $comments);

    expect(Response::count())->toBe(2);
    Notification::assertNothingSent();
});

it('turns the little HTML of a WordPress comment into plain text', function () {
    (new CommentImporter([]))->import($this->blog, [wpComment(1, 'Fien', 'Echt <strong>prachtig</strong>!<br />Liefs &amp; groetjes')]);

    expect(Response::value('response'))->toBe("Echt prachtig!\nLiefs & groetjes");
});
