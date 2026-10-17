<?php

namespace App\Http\Controllers;

use App\Mail\ConfirmSubscription;
use App\Models\Subscriber;
use App\Support\SpamGuard;
use App\Support\Visitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Signing up for an email about every new blog (with a confirmation mail), and unsubscribing.
 */
class SubscriberController extends Controller
{
    // The forms under a blog and in the footer; the answer is shown at the form that was used
    public const FORMS = ['blog', 'footer'];

    public function store(Request $request): RedirectResponse
    {
        $form = in_array($request->input('form'), self::FORMS, true) ? $request->input('form') : 'footer';
        $back = fn () => redirect()->to(url()->previous() . "#nieuwsbrief-{$form}");

        // Everyone gets the same answer: bots learn nothing, and nobody can find out who is on the list
        $done = fn () => $back()->with("newsletter_status_{$form}", 'Bijna klaar! Je krijgt een mail met een link om je inschrijving te bevestigen.');

        if (SpamGuard::isBot($request)) {
            return $done();
        }

        try {
            $data = $request->validateWithBag("newsletter_{$form}", [
                'name' => ['required', 'string', 'max:80'],
                'email' => ['required', 'email', 'max:190'],
                'form' => ['nullable', Rule::in(self::FORMS)],
            ], [], [
                'name' => 'naam',
                'email' => 'e-mailadres',
            ]);
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(url()->previous() . "#nieuwsbrief-{$form}");
        }

        if (! SpamGuard::passesTurnstile($request)) {
            throw ValidationException::withMessages([
                'email' => 'We konden niet controleren dat je geen robot bent. Probeer het nog eens.',
            ])->errorBag("newsletter_{$form}")->redirectTo(url()->previous() . "#nieuwsbrief-{$form}");
        }

        // One person signs up once or twice; more from the same address is someone filling in strangers
        $limiter = 'newsletter:' . Visitor::ipHash($request);
        if (RateLimiter::tooManyAttempts($limiter, config('blog.newsletter.per_hour'))) {
            return $done();
        }
        RateLimiter::hit($limiter, 3600);

        $subscriber = Subscriber::firstOrNew(['email' => mb_strtolower(trim($data['email']))]);

        if (! $subscriber->isConfirmed()) {
            $subscriber->fill(['name' => trim($data['name']), 'ip_hash' => Visitor::ipHash($request)])->save();
            $this->sendConfirmation($subscriber);
        }

        return $done();
    }

    /**
     * The link in the confirmation mail (signed, valid for 7 days).
     */
    public function confirm(Subscriber $subscriber): View
    {
        $subscriber->confirmed_at ??= now();
        $subscriber->save();

        return view('subscribers.confirmed', ['subscriber' => $subscriber]);
    }

    /**
     * The unsubscribe link in every mail: a page with a button, so link checkers in mail
     * programs that open every link can't unsubscribe anyone by accident.
     */
    public function unsubscribe(string $token): View
    {
        return view('subscribers.unsubscribe', [
            'subscriber' => Subscriber::where('token', $token)->first(),
            'token' => $token,
        ]);
    }

    /**
     * The button on that page, and the one-click unsubscribe from mail apps (List-Unsubscribe-Post).
     */
    public function destroy(Request $request, string $token): View|Response
    {
        Subscriber::where('token', $token)->delete();

        // Mail apps post here themselves and only need a 2xx answer
        if (! $request->has('_token')) {
            return response()->noContent();
        }

        return view('subscribers.unsubscribed');
    }

    private function sendConfirmation(Subscriber $subscriber): void
    {
        // Someone filling in the same address over and over gets at most 3 mails an hour
        $key = 'newsletter-confirm:' . sha1($subscriber->email);

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return;
        }

        RateLimiter::hit($key, 3600);

        try {
            Mail::to($subscriber->email, $subscriber->name)->send(new ConfirmSubscription($subscriber));
        } catch (Throwable $e) {
            // The sign-up is saved; a mail server hiccup shouldn't show the reader an error page
            report($e);
        }
    }
}
