<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubscriberController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/', [BlogController::class, 'index'])->name('blogs.index');
Route::get('/kaart', [BlogController::class, 'map'])->name('blogs.map');
Route::get('/blogs/country/{country_id}', [BlogController::class, 'blogCountry'])->name('blogs.blogCountry');
Route::get('/blogs/preview/{blog:id}', [BlogController::class, 'preview'])->middleware('signed')->name('blogs.preview');
Route::get('/blogs/{slug}', [BlogController::class, 'show'])->name('blogs.show');

// Hearts and comments, without an account
Route::post('/blogs/{blog:slug}/hartje', [LikeController::class, 'toggle'])
    ->middleware('throttle:likes')->name('blogs.like');
Route::post('/blogs/{blog:slug}/reacties', [CommentController::class, 'store'])
    ->middleware('throttle:20,1')->name('comments.store');
Route::get('/reacties/{comment}/goedkeuren', [CommentController::class, 'approve'])
    ->middleware('signed')->name('comments.approve');

// An email for every new blog: sign up, confirm from the mail, unsubscribe from any mail
Route::post('/nieuwsbrief', [SubscriberController::class, 'store'])
    ->middleware('throttle:6,1')->name('subscribers.store');
Route::get('/nieuwsbrief/bevestigen/{subscriber}', [SubscriberController::class, 'confirm'])
    ->middleware('signed')->name('subscribers.confirm');
Route::get('/nieuwsbrief/uitschrijven/{token}', [SubscriberController::class, 'unsubscribe'])
    ->name('subscribers.unsubscribe');
// Also posted to directly by mail apps (one-click unsubscribe), so without a CSRF token
Route::post('/nieuwsbrief/uitschrijven/{token}', [SubscriberController::class, 'destroy'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
    ->middleware('throttle:20,1')->name('subscribers.destroy');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
