<?php

use Illuminate\Http\Request;
use App\Http\Controllers\AuthorController;
use App\Http\Controllers\TopsisController;
use App\Http\Controllers\BaselineTopsisController;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// 1. Mock API SINTA (Dipisah endpoint-nya)
Route::prefix('v3/{env}/{uniq}')->group(function () {
    
    // Endpoint Profil Author
    Route::post('author/profile/{type}/{id}', [AuthorController::class, 'profile']);

    // Endpoint Dokumen/Publikasi yang dipisah
    Route::post('author/scopus/{type}/{id}', [AuthorController::class, 'scopus']);
    Route::post('author/garuda/{type}/{id}', [AuthorController::class, 'garuda']);
    Route::post('author/google/{type}/{id}', [AuthorController::class, 'google']);
});

// 2. Endpoint API Gateway TOPSIS
Route::post('topsis/recommendation', [TopsisController::class, 'generateSlrRecommendation']);
Route::post('topsis/validate', [TopsisController::class, 'validateScores']);

// 3. Endpoint pembanding: TOPSIS Data-to-Compute (baseline)
Route::post('topsis/baseline-recommendation', [BaselineTopsisController::class, 'generateSlrRecommendation']);