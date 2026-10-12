<?php

use App\Filament\Resources\SubscriberResource\Pages\ManageSubscribers;
use App\Mail\ConfirmSubscription;
use App\Mail\NewBlogPublished;
use App\Models\Blog;
use App\Models\Subscriber;
use App\Models\User;
use App\Support\SpamGuard;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    $this->author = User::factory()->create(['name' => 'Ine']);
});

function newBlog(array $attributes = []): Blog
{
    return Blog::create([
        'title' => 'Poon Hill bij zonsopkomst', 'slug' => 'poon-hill', 'excerpt' => 'Om vier uur op pad.',
        'status' => 'published', 'placed_by_id' => test()->author->id,
        'content' => ['type' => 'doc', 'content' => []],
        ...$attributes,
    ]);
}

/**
 * The sign-up form as a real reader sends it: opened a little while ago.
 */
function signUp(array $fields = []): \Illuminate\Testing\TestResponse
{
    $started = SpamGuard::startedToken();
    test()->travel(10)->seconds();

    return test()->from('/blogs/poon-hill')->post('/nieuwsbrief', [
        'name' => 'Femke',
        'email' => 'Femke@Example.com',
        'form' => 'blog',
        SpamGuard::STARTED => $started,
        ...$fields,
    ]);
}

function confirmed(string $email = 'femke@example.com'): Subscriber
{
    return Subscriber::create(['name' => 'Femke', 'email' => $email, 'confirmed_at' => now()]);
}

it('sends a confirmation mail and only puts the reader on the list after the click', function () {
    signUp()
        ->assertRedirect('/blogs/poon-hill#nieuwsbrief-blog')
        ->assertSessionHas('newsletter_status_blog');

    $subscriber = Subscriber::sole();
    expect($subscriber->email)->toBe('femke@example.com')->and($subscriber->isConfirmed())->toBeFalse();
    Mail::assertSent(ConfirmSubscription::class, fn ($mail) => $mail->hasTo('femke@example.com'));

    $this->get($subscriber->confirmUrl())->assertOk()->assertSee('Je staat op de lijst');
    expect($subscriber->fresh()->isConfirmed())->toBeTrue();
});

it('only confirms through the signed link from the mail', function () {
    $subscriber = Subscriber::create(['name' => 'Femke', 'email' => 'femke@example.com']);

    $this->get("/nieuwsbrief/bevestigen/{$subscriber->id}")->assertForbidden();
    expect($subscriber->fresh()->isConfirmed())->toBeFalse();
});

it('gives the same answer for an address that is already on the list, without mailing it', function () {
    confirmed();

    signUp()->assertSessionHas('newsletter_status_blog');

    Mail::assertNothingSent();
    expect(Subscriber::count())->toBe(1);
});

it('ignores bots', function () {
    signUp([SpamGuard::HONEYPOT => 'https://spam.example'])->assertSessionHas('newsletter_status_blog');

    expect(Subscriber::count())->toBe(0);
    Mail::assertNothingSent();
});

it('shows mistakes at the form that was used', function () {
    signUp(['email' => 'geen-adres', 'form' => 'footer'])
        ->assertRedirect('/blogs/poon-hill#nieuwsbrief-footer')
        ->assertSessionHasErrors(['email'], null, 'newsletter_footer');
});

it('mails confirmed subscribers once when a blog goes online', function () {
    confirmed();
    Subscriber::create(['name' => 'Niet bevestigd', 'email' => 'wacht@example.com']);

    $blog = newBlog();

    Mail::assertQueued(NewBlogPublished::class, 1);
    Mail::assertQueued(NewBlogPublished::class, fn ($mail) => $mail->hasTo('femke@example.com') && $mail->blog->is($blog));

    // Fixing a typo later doesn't send it again
    $blog->update(['title' => 'Poon Hill bij zonsopgang']);
    Mail::assertQueued(NewBlogPublished::class, 1);
});

it('waits with drafts until they are published', function () {
    confirmed();

    $blog = newBlog(['status' => 'draft']);
    Mail::assertNothingQueued();

    $blog->update(['status' => 'published']);
    Mail::assertQueued(NewBlogPublished::class, 1);
});

it('mails about a planned blog once its date has come', function () {
    confirmed();
    newBlog(['published_at' => now()->addDay()]);

    $this->artisan('blogs:notify-subscribers');
    Mail::assertNothingQueued();

    $this->travel(25)->hours();
    $this->artisan('blogs:notify-subscribers');
    $this->artisan('blogs:notify-subscribers');
    Mail::assertQueued(NewBlogPublished::class, 1);
});

it('has a link to the blog and an unsubscribe link in the mail', function () {
    $subscriber = confirmed();
    $mail = new NewBlogPublished(newBlog(), $subscriber);

    $mail->assertHasSubject('Nieuw verhaal: Poon Hill bij zonsopkomst')
        ->assertSeeInHtml(route('blogs.show', 'poon-hill'))
        ->assertSeeInHtml($subscriber->unsubscribeUrl())
        ->assertSeeInHtml('Hoi Femke');

    expect($mail->headers()->text['List-Unsubscribe'])->toBe("<{$subscriber->unsubscribeUrl()}>");
});

it('only unsubscribes after the button is clicked', function () {
    $subscriber = confirmed();

    // Mail programs that open every link to check it must not unsubscribe anyone
    $this->get($subscriber->unsubscribeUrl())->assertOk()->assertSee('Ja, schrijf me uit');
    expect(Subscriber::count())->toBe(1);

    $this->post(route('subscribers.destroy', $subscriber->token), ['_token' => csrf_token()])
        ->assertOk()->assertSee('Je bent uitgeschreven');
    expect(Subscriber::count())->toBe(0);
});

it('supports one-click unsubscribe from mail apps', function () {
    $subscriber = confirmed();

    $this->post(route('subscribers.destroy', $subscriber->token))->assertNoContent();
    expect(Subscriber::count())->toBe(0);
});

it('removes sign-ups that were never confirmed after 30 days', function () {
    Subscriber::create(['name' => 'Oud', 'email' => 'oud@example.com']);
    confirmed();

    $this->travel(31)->days();
    $this->artisan('model:prune', ['--model' => [Subscriber::class]]);

    expect(Subscriber::pluck('email')->all())->toBe(['femke@example.com']);
});

it('shows the sign-up form under a blog and in the footer', function () {
    newBlog();

    $this->get('/blogs/poon-hill')
        ->assertOk()
        ->assertSee('id="nieuwsbrief-blog"', false)
        ->assertSee('id="nieuwsbrief-footer"', false);
});

it('lists subscribers in the admin and adds someone by hand as confirmed', function () {
    $this->actingAs($this->author);
    $waiting = Subscriber::create(['name' => 'Wacht', 'email' => 'wacht@example.com']);

    Livewire::test(ManageSubscribers::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$waiting])
        ->callAction('create', ['name' => 'Oma', 'email' => 'Oma@Example.com'])
        ->assertHasNoActionErrors();

    expect(Subscriber::where('email', 'oma@example.com')->first()?->isConfirmed())->toBeTrue();
    Mail::assertNothingSent();
});
