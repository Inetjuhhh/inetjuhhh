<?php

use App\Filament\Resources\ResponseResource\Pages\ListResponses;
use App\Models\Blog;
use App\Models\Response;
use App\Models\User;
use App\Notifications\CommentReplyNotification;
use App\Notifications\NewCommentNotification;
use App\Support\SpamGuard;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();

    $this->blog = Blog::create([
        'title' => 'Poon Hill', 'slug' => 'poon-hill', 'excerpt' => 'Intro', 'status' => 'published',
        'placed_by_id' => User::factory()->create()->id, 'content' => ['type' => 'doc', 'content' => []],
    ]);
});

/**
 * A comment as a real reader sends it: the form was opened a little while ago.
 */
function sendComment(array $fields = []): \Illuminate\Testing\TestResponse
{
    $started = SpamGuard::startedToken();
    test()->travel(10)->seconds();

    return test()->post('/blogs/poon-hill/reacties', [
        'name' => 'Femke',
        'email' => 'Femke@Example.com',
        'response' => 'Wat een mooi verhaal!',
        SpamGuard::STARTED => $started,
        ...$fields,
    ]);
}

it('gives a heart once per browser, without an account', function () {
    // A first heart gives the browser its random id
    $this->postJson('/blogs/poon-hill/hartje', ['liked' => true])
        ->assertOk()
        ->assertJson(['liked' => true, 'count' => 1])
        ->assertCookie('visitor_id');

    // Another browser counts separately, and can take its heart back
    $browser = (string) str()->uuid();
    $this->withCredentials()->withCookie('visitor_id', $browser)->postJson('/blogs/poon-hill/hartje', ['liked' => true])->assertJson(['liked' => true, 'count' => 2]);
    $this->withCredentials()->withCookie('visitor_id', $browser)->postJson('/blogs/poon-hill/hartje', ['liked' => false])->assertJson(['liked' => false, 'count' => 1]);

    // The page remembers the heart of a returning browser
    $this->withCredentials()->withCookie('visitor_id', $browser)->postJson('/blogs/poon-hill/hartje', ['liked' => true]);
    $this->withCookie('visitor_id', $browser)->get('/blogs/poon-hill')->assertSee('liked: true', false);
});

it('keeps the heart when an older tab sends it again', function () {
    $browser = (string) str()->uuid();
    $like = fn () => $this->withCredentials()->withCookie('visitor_id', $browser)->postJson('/blogs/poon-hill/hartje', ['liked' => true]);

    $like()->assertJson(['liked' => true, 'count' => 1]);
    // A tab opened before the first heart still shows an empty one; clicking it must not undo it
    $like()->assertJson(['liked' => true, 'count' => 1]);
});

it('needs to know whether the heart is given or taken back', function () {
    $this->postJson('/blogs/poon-hill/hartje')->assertUnprocessable();
});

it('does not place a cookie just for reading a blog', function () {
    $this->get('/blogs/poon-hill')->assertOk()->assertCookieMissing('visitor_id');
});

it('limits how fast hearts can be clicked', function () {
    foreach (range(1, 30) as $i) {
        $this->postJson('/blogs/poon-hill/hartje', ['liked' => true]);
    }

    $this->postJson('/blogs/poon-hill/hartje', ['liked' => true])->assertTooManyRequests();
});

it('holds a first comment for approval and tells the owner', function () {
    sendComment()->assertRedirect('/blogs/poon-hill#reacties')->assertSessionHas('comment_status');

    $comment = Response::sole();
    expect($comment->status)->toBe(Response::PENDING)
        ->and($comment->email)->toBe('femke@example.com')
        ->and($comment->ip_hash)->not->toBeNull();

    $this->get('/blogs/poon-hill')->assertDontSee('Wat een mooi verhaal!');
    Notification::assertSentOnDemand(NewCommentNotification::class);
});

it('publishes comments from someone who was approved before', function () {
    sendComment();
    Response::sole()->approve();

    sendComment(['response' => 'Ik kom nog eens terug!']);

    expect(Response::latest('id')->first()->status)->toBe(Response::APPROVED);
    $this->get('/blogs/poon-hill')->assertSee('Ik kom nog eens terug!');
});

it('never shows the email address', function () {
    sendComment();
    Response::sole()->approve();

    $this->get('/blogs/poon-hill')->assertSee('Femke')->assertDontSee('femke@example.com');
});

it('silently drops comments from bots', function () {
    sendComment([SpamGuard::HONEYPOT => 'https://spam.example'])->assertSessionHas('comment_status');

    // Sent within a second of opening the form
    $this->post('/blogs/poon-hill/reacties', [
        'name' => 'Bot', 'email' => 'bot@example.com', 'response' => 'Hallo',
        SpamGuard::STARTED => SpamGuard::startedToken(),
    ])->assertSessionHas('comment_status');

    // A made-up timestamp
    $this->post('/blogs/poon-hill/reacties', [
        'name' => 'Bot', 'email' => 'bot@example.com', 'response' => 'Hallo', SpamGuard::STARTED => '0',
    ]);

    expect(Response::count())->toBe(0);
    Notification::assertNothingSent();
});

it('files comments with too many links or blocked words as spam', function () {
    sendComment(['response' => 'Kijk op https://a.example en https://b.example']);
    sendComment(['email' => 'x@example.com', 'response' => 'Cheap viagra here']);

    expect(Response::pluck('status')->unique()->all())->toBe([Response::SPAM]);
    Notification::assertNothingSent();
});

it('recognises the usual spam tricks, but not a normal reaction', function (string $text, string $name, bool $spam) {
    expect(SpamGuard::looksLikeSpam($text, $name))->toBe($spam);
})->with([
    'forum link' => ['Great post [url=https://pills.example]click[/url]', 'John', true],
    'html link' => ['Nice <a href="https://pills.example">site</a>', 'John', true],
    'link as name' => ['Great article', 'best-pills.com', true],
    'Russian' => ['Отличная статья, спасибо за информацию', 'Ivan', true],
    'Chinese' => ['非常好的文章，谢谢分享', 'Li', true],
    'Dutch, with one link' => ['Wat mooi! Wij sliepen hier ook: https://www.booking.com', 'Femke', false],
    'English' => ['Lovely story, we went to Nepal last year too!', 'Sarah', false],
    'Dutch with a Japanese word' => ['Wat een mooie foto van de 寿司, we hebben zin gekregen in Japan!', 'Bas', false],
]);

it('stops mailing about new comments after the hourly limit, but keeps them', function () {
    config(['blog.comments.mails_per_hour' => 2, 'blog.comments.per_ten_minutes' => 10]);

    foreach (range(1, 3) as $i) {
        sendComment(['email' => "lezer{$i}@example.com", 'response' => "Reactie {$i}"]);
    }

    expect(Response::where('status', Response::PENDING)->count())->toBe(3);
    Notification::assertSentOnDemandTimes(NewCommentNotification::class, 2);
});

it('limits how many comments one visitor can send', function () {
    foreach (range(1, 3) as $i) {
        sendComment(['response' => "Reactie {$i}"]);
    }

    sendComment(['response' => 'Nog een'])->assertSessionHasErrors('response');
    expect(Response::count())->toBe(3);
});

it('asks Cloudflare Turnstile when it is switched on', function () {
    config(['services.turnstile.secret_key' => 'secret']);
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

    sendComment()->assertSessionHasErrors('response');
    expect(Response::count())->toBe(0);
});

it('approves a comment from the link in the mail', function () {
    sendComment();
    $comment = Response::sole();

    $this->get(URL::temporarySignedRoute('comments.approve', now()->addDay(), ['comment' => $comment->id]))
        ->assertRedirect('/blogs/poon-hill#reactie-' . $comment->id);
    expect($comment->fresh()->status)->toBe(Response::APPROVED);

    // Without the signature the link does nothing
    $this->get("/reacties/{$comment->id}/goedkeuren")->assertForbidden();
});

it('lets the owner reply from the admin and mails the reader who asked for it', function () {
    sendComment(['notify_on_reply' => '1']);
    $comment = Response::sole();

    $this->actingAs(User::first());
    Livewire::test(ListResponses::class)
        ->callTableAction('reply', $comment, ['response' => 'Dank je wel!'])
        ->assertHasNoTableActionErrors();

    $reply = $comment->replies()->sole();
    expect($comment->fresh()->status)->toBe(Response::APPROVED)
        ->and($reply->is_author)->toBeTrue();

    Notification::assertSentOnDemand(CommentReplyNotification::class);
    $this->get('/blogs/poon-hill')->assertSee('Dank je wel!')->assertSee('Schrijver');
});

it('cleans up old spam', function () {
    sendComment(['response' => 'Cheap viagra']);
    $this->travel(31)->days();

    $this->artisan('model:prune', ['--model' => [Response::class]]);

    expect(Response::count())->toBe(0);
});
