<?php

use App\Http\Controllers\V1\CurrencyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('v1', [CurrencyController::class, 'handle'])->middleware('auth.token');
Route::post('v1', [CurrencyController::class, 'handle'])->middleware('auth.token');
