<?php

use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', fn () => view('welcome'));

Route::post('/search', [ScheduleController::class, 'search'])->name('schedule.search');