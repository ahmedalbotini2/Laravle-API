<?php

use App\Http\Controllers\Api\AiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| AI Bridge Routes
|--------------------------------------------------------------------------
*/

Route::prefix('ai')->group(function () {
    Route::post('/analyze', [AiController::class, 'analyze']);
});
