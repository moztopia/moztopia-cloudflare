<?php

declare(strict_types=1);

use App\Http\Controllers\ExampleController;
use Illuminate\Support\Facades\Route;

Route::get('/', ExampleController::class)->defaults('page', 'visitor')->name('visitor');
Route::get('/ui', ExampleController::class)->defaults('page', 'ui')->name('ui');
Route::get('/request', ExampleController::class)->defaults('page', 'request')->name('request');
