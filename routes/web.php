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

Route::get('abc', function () {
    echo "cc";
});
Route::post('/upload', [VideoController::class, 'upload'])->name('video.upload');
Route::get('/videos/{filename}', [VideoController::class, 'download'])->name('video.download');
