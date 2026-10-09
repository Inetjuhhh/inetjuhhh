<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/', [BlogController::class, 'index'])->name('blogs.index');
Route::get('/blogs/country/{country_id}', [BlogController::class, 'blogCountry'])->name('blogs.blogCountry');
Route::get('/blogs/preview/{blog:id}', [BlogController::class, 'preview'])->middleware('signed')->name('blogs.preview');
Route::get('/blogs/{slug}', [BlogController::class, 'show'])->name('blogs.show');


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
