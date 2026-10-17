<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Response;
use App\Notifications\NewCommentNotification;
use App\Support\SpamGuard;
use App\Support\Visitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class CommentController extends Controller
{
    public function store(Request $request, Blog $blog): RedirectResponse
    {
        abort_unless($blog->isPublished(), 404);

        $back = fn (string $message) => redirect()
            ->to(route('blogs.show', $blog) . '#reacties')
            ->with('comment_status', $message);

        // Bots get the normal answer, so they can't tell they were caught
        if (SpamGuard::isBot($request)) {
            return $back('Bedankt! Je reactie verschijnt zodra ik hem heb gelezen.');
        }

        try {
            $data = $this->validateComment($request);
        } catch (ValidationException $exception) {
            // Back to the form, not to the top of the page
            throw $exception->redirectTo(route('blogs.show', $blog) . '#reacties');
        }

        $email = mb_strtolower(trim($data['email']));
        $status = match (true) {
            SpamGuard::looksLikeSpam($data['response'], $data['name']) => Response::SPAM,
            Response::isTrustedEmail($email) => Response::APPROVED,
            default => Response::PENDING,
        };

        $comment = $blog->responses()->create([
            'name' => trim($data['name']),
            'email' => $email,
            'response' => trim($data['response']),
            'notify_on_reply' => (bool) ($data['notify_on_reply'] ?? false),
            'status' => $status,
            'approved_at' => $status === Response::APPROVED ? now() : null,
            'ip_hash' => Visitor::ipHash($request),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);

        // A mail per comment, but not a full inbox when a spam wave gets through: the rest wait in the admin
        if ($status !== Response::SPAM && RateLimiter::attempt('comment-mails', config('blog.comments.mails_per_hour'), fn () => true, 3600)) {
            Notification::route('mail', config('blog.admin_emails'))->notify(new NewCommentNotification($comment));
        }

        return match ($status) {
            Response::APPROVED => redirect()
                ->to(route('blogs.show', $blog) . '#reactie-' . $comment->id)
                ->with('comment_status', 'Bedankt voor je reactie!'),
            // Spam gets the same answer as a waiting comment
            default => $back('Bedankt! Je reactie verschijnt zodra ik hem heb gelezen.'),
        };
    }

    /**
     * Validation, the per-IP limit and the Turnstile check.
     *
     * @throws ValidationException
     */
    private function validateComment(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190'],
            'response' => ['required', 'string', 'min:2', 'max:' . config('blog.comments.max_length')],
            'notify_on_reply' => ['nullable', 'boolean'],
        ], [], [
            'name' => 'naam',
            'email' => 'e-mailadres',
            'response' => 'reactie',
        ]);

        $limiter = 'comments:' . Visitor::ipHash($request);
        if (RateLimiter::tooManyAttempts($limiter, config('blog.comments.per_ten_minutes'))) {
            throw ValidationException::withMessages([
                'response' => 'Je hebt net al een paar reacties geplaatst. Probeer het over een paar minuten nog eens.',
            ]);
        }

        if (! SpamGuard::passesTurnstile($request)) {
            throw ValidationException::withMessages([
                'response' => 'We konden niet controleren dat je geen robot bent. Probeer het nog eens.',
            ]);
        }

        RateLimiter::hit($limiter, 600);

        return $data;
    }

    /**
     * The "Goedkeuren" button in the notification mail (a signed link, valid for 14 days).
     */
    public function approve(Response $comment): RedirectResponse
    {
        $comment->approve();

        return redirect()->to(route('blogs.show', $comment->blog) . '#reactie-' . $comment->id)
            ->with('comment_status', 'De reactie van ' . $comment->name . ' is goedgekeurd en staat online.');
    }
}
