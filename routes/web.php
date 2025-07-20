<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VideoController;

// use Livewire\Volt\Volt;

// Route::get('/', function () {
//     return view('welcome');
// })->name('home');

// Route::view('dashboard', 'dashboard')
//     ->middleware(['auth', 'verified'])
//     ->name('dashboard');

// Route::middleware(['auth'])->group(function () {
//     Route::redirect('settings', 'settings/profile');

//     Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
//     Volt::route('settings/password', 'settings.password')->name('settings.password');
//     Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
// });

// require __DIR__.'/auth.php';



Route::get('/', [VideoController::class, 'index']);
Route::post('/upload', [VideoController::class, 'upload'])->name('video.upload');
Route::get('/videos/{filename}', function ($filename) {
    $path = public_path("videos/processed/{$filename}");
    if (!file_exists($path)) abort(404);
    return response()->file($path);
});
