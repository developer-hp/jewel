<?php

use App\Http\Controllers\LandingController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
|
| Just the shop's front page. Everything behind the sign-in screen lives in
| routes/admin.php, which bootstrap/app.php registers behind the configurable
| admin prefix.
|
*/

// No middleware beyond the web group: it is the one route a customer reaches.
// When the landing page is switched off it redirects the way it always did.
Route::get('/', LandingController::class)->name('landing');
